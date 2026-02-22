<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleAnalyticsService;

class GoogleAnalyticsReport implements Tool
{
    public function __construct(
        private GoogleAnalyticsService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Run a GA4 analytics report. Returns rows of dimension/metric data for the specified date range.

        Common dimensions: sessionSource, sessionMedium, sessionDefaultChannelGroup (traffic source); pagePath, pageTitle, landingPage (pages); country, city (geo); deviceCategory, browser, operatingSystem (device); date, dateHour, month (time); newVsReturning (user); eventName (events).

        Common metrics: sessions, totalUsers, newUsers, activeUsers (traffic); screenPageViews, bounceRate, averageSessionDuration, engagementRate, sessionsPerUser (engagement); eventCount, conversions (events); purchaseRevenue, totalRevenue (e-commerce).

        Dates: YYYY-MM-DD or relative: "today", "yesterday", "7daysAgo", "28daysAgo", "30daysAgo", "90daysAgo", "365daysAgo".
        Filter operators: exact, contains, begins_with, ends_with, regex, in_list.
        Metric filter operators: equal, less_than, greater_than, less_than_or_equal, greater_than_or_equal.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Analytics integration is not configured.';
            }

            $propertyId = $request['propertyId'] ?? '';
            if (empty($propertyId)) {
                return 'Error: propertyId is required. Use google_analytics_list_properties to find your GA4 property ID.';
            }

            $metrics = $request['metrics'] ?? [];
            if (! is_array($metrics) || empty($metrics)) {
                return 'Error: metrics is required (array of metric names, e.g., ["sessions", "totalUsers"]).';
            }

            /** @var array<string, mixed> $params */
            $params = [
                'dimensions' => $request['dimensions'] ?? [],
                'metrics' => $metrics,
                'startDate' => $request['startDate'] ?? '28daysAgo',
                'endDate' => $request['endDate'] ?? 'yesterday',
                'compareStartDate' => $request['compareStartDate'] ?? '',
                'compareEndDate' => $request['compareEndDate'] ?? '',
                'filters' => $request['filters'] ?? [],
                'metricFilters' => $request['metricFilters'] ?? [],
                'orderBy' => $request['orderBy'] ?? '',
                'orderDirection' => $request['orderDirection'] ?? 'desc',
                'limit' => $request['limit'] ?? 10,
                'offset' => $request['offset'] ?? 0,
            ];

            $body = $this->service->buildReportBody($params);
            $result = $this->service->runReport((string) $propertyId, $body);

            return $this->formatReportResponse($result, $params);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Format a report response as a compact text table.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $params
     */
    private function formatReportResponse(array $result, array $params): string
    {
        $rows = $result['rows'] ?? [];
        $dimensionHeaders = $result['dimensionHeaders'] ?? [];
        $metricHeaders = $result['metricHeaders'] ?? [];

        $startDate = $params['startDate'] ?? '28daysAgo';
        $endDate = $params['endDate'] ?? 'yesterday';
        $compareStart = $params['compareStartDate'] ?? '';
        $compareEnd = $params['compareEndDate'] ?? '';
        $hasComparison = $compareStart !== '' && $compareEnd !== '';
        $limit = (int) ($params['limit'] ?? 10);

        // Header
        $header = "Report: {$startDate} to {$endDate}";
        if ($hasComparison) {
            $header .= " vs {$compareStart} to {$compareEnd}";
        }

        if (empty($rows)) {
            return "{$header}\n\nNo data found for this query.";
        }

        $dimNames = array_map(fn (array $h) => $h['name'] ?? '', $dimensionHeaders);
        $metricNames = array_map(fn (array $h) => $h['name'] ?? '', $metricHeaders);

        // No dimensions — aggregate-only format
        if (empty($dimNames)) {
            $lines = [$header, ''];
            $row = $rows[0];
            $metricValues = $row['metricValues'] ?? [];

            if ($hasComparison && count($rows) >= 1) {
                // With comparison, each metric has two values (current period at index 0)
                foreach ($metricNames as $i => $name) {
                    $current = $metricValues[$i]['value'] ?? '0';
                    $lines[] = "{$name}: " . $this->formatNumber($current);
                }

                // Check for second date range row
                if (isset($rows[0]['metricValues']) && count($result['rows'] ?? []) > 0) {
                    $this->appendComparisonTotals($lines, $result, $metricNames);
                }
            } else {
                foreach ($metricNames as $i => $name) {
                    $value = $metricValues[$i]['value'] ?? '0';
                    $lines[] = "{$name}: " . $this->formatNumber($value);
                }
            }

            return implode("\n", $lines);
        }

        // With dimensions — table format
        $data = [];
        foreach ($rows as $row) {
            $entry = [];
            $dimValues = $row['dimensionValues'] ?? [];
            foreach ($dimNames as $i => $name) {
                $entry[$name] = $dimValues[$i]['value'] ?? '';
            }
            $metricValues = $row['metricValues'] ?? [];
            foreach ($metricNames as $i => $name) {
                $entry[$name] = $metricValues[$i]['value'] ?? '0';
            }
            $data[] = $entry;
        }

        // Build text table
        $allCols = array_merge($dimNames, $metricNames);
        $widths = [];
        foreach ($allCols as $col) {
            $widths[$col] = mb_strlen($col);
        }
        foreach ($data as $row) {
            foreach ($allCols as $col) {
                $val = in_array($col, $metricNames, true)
                    ? $this->formatNumber($row[$col] ?? '0')
                    : ($row[$col] ?? '');
                $len = mb_strlen($val);
                if ($len > $widths[$col]) {
                    $widths[$col] = $len;
                }
            }
        }

        // Cap column widths at 40 characters
        foreach ($widths as $col => $w) {
            if ($w > 40) {
                $widths[$col] = 40;
            }
        }

        $lines = [$header, ''];

        // Header row
        $headerParts = [];
        $sepParts = [];
        foreach ($allCols as $col) {
            $isMetric = in_array($col, $metricNames, true);
            $w = $widths[$col];
            $headerParts[] = $isMetric ? str_pad($col, $w, ' ', STR_PAD_LEFT) : str_pad($col, $w);
            $sepParts[] = str_repeat('-', $w);
        }
        $lines[] = implode(' | ', $headerParts);
        $lines[] = implode('-|-', $sepParts);

        // Data rows
        foreach ($data as $row) {
            $parts = [];
            foreach ($allCols as $col) {
                $isMetric = in_array($col, $metricNames, true);
                $w = $widths[$col];
                $val = $isMetric
                    ? $this->formatNumber($row[$col] ?? '0')
                    : ($row[$col] ?? '');
                if (mb_strlen($val) > $w) {
                    $val = mb_substr($val, 0, $w - 1) . '~';
                }
                $parts[] = $isMetric ? str_pad($val, $w, ' ', STR_PAD_LEFT) : str_pad($val, $w);
            }
            $lines[] = implode(' | ', $parts);
        }

        // Totals
        $totals = $result['totals'] ?? [];
        if (! empty($totals)) {
            $totalRow = $totals[0] ?? [];
            $totalMetrics = $totalRow['metricValues'] ?? [];
            $totalParts = [];
            foreach ($metricNames as $i => $name) {
                $totalParts[] = "{$name}=" . $this->formatNumber($totalMetrics[$i]['value'] ?? '0');
            }
            $lines[] = '';
            $lines[] = 'Totals: ' . implode('  ', $totalParts);
        }

        $lines[] = count($data) . ' rows returned (limit: ' . $limit . ')';

        return implode("\n", $lines);
    }

    /**
     * Append comparison totals when two date ranges are used.
     *
     * @param  array<int, string>  $lines
     * @param  array<string, mixed>  $result
     * @param  array<int, string>  $metricNames
     */
    private function appendComparisonTotals(array &$lines, array $result, array $metricNames): void
    {
        $totals = $result['totals'] ?? [];
        if (count($totals) < 2) {
            return;
        }

        $currentTotals = $totals[0]['metricValues'] ?? [];
        $previousTotals = $totals[1]['metricValues'] ?? [];

        $lines[] = '';
        $lines[] = 'Comparison:';
        foreach ($metricNames as $i => $name) {
            $current = (float) ($currentTotals[$i]['value'] ?? 0);
            $previous = (float) ($previousTotals[$i]['value'] ?? 0);
            $change = $previous > 0
                ? round(($current - $previous) / $previous * 100, 1)
                : ($current > 0 ? 100.0 : 0.0);
            $sign = $change >= 0 ? '+' : '';
            $lines[] = "  {$name}: " . $this->formatNumber((string) $current) . ' vs ' . $this->formatNumber((string) $previous) . " ({$sign}{$change}%)";
        }
    }

    /**
     * Format a numeric value for display.
     */
    private function formatNumber(string $value): string
    {
        // Percentage values (bounceRate, engagementRate, etc.)
        if (str_contains($value, '.') && (float) $value <= 1.0 && (float) $value >= 0.0 && strlen($value) <= 8) {
            // Could be a rate (0-1 range) — keep as-is with rounding
            $float = (float) $value;
            if ($float === 0.0 || $float === 1.0) {
                return $value;
            }

            return (string) round($float, 4);
        }

        // Regular floats
        if (str_contains($value, '.')) {
            return (string) round((float) $value, 2);
        }

        // Integers with thousands separator
        if (is_numeric($value)) {
            $int = (int) $value;
            if (abs($int) >= 1000) {
                return number_format($int);
            }
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'propertyId' => $schema
                ->string()
                ->description('GA4 property ID (numeric, e.g., "123456789"). Use google_analytics_list_properties to find it.')
                ->required(),
            'metrics' => $schema
                ->array()
                ->description('Metric names to measure (e.g., ["sessions", "totalUsers"]).')
                ->required(),
            'dimensions' => $schema
                ->array()
                ->description('Dimension names to group by (e.g., ["country", "pagePath"]). Optional — omit for aggregate totals.'),
            'startDate' => $schema
                ->string()
                ->description('Start date: YYYY-MM-DD or relative ("7daysAgo", "28daysAgo", "yesterday", "today"). Default: "28daysAgo".'),
            'endDate' => $schema
                ->string()
                ->description('End date: YYYY-MM-DD or relative. Default: "yesterday".'),
            'compareStartDate' => $schema
                ->string()
                ->description('Comparison period start date (for period-over-period).'),
            'compareEndDate' => $schema
                ->string()
                ->description('Comparison period end date.'),
            'filters' => $schema
                ->array()
                ->description('Dimension filters: [{dimension, operator, value}]. Operators: exact, contains, begins_with, ends_with, regex, in_list.'),
            'metricFilters' => $schema
                ->array()
                ->description('Metric filters: [{metric, operator, value}]. Operators: equal, less_than, greater_than, less_than_or_equal, greater_than_or_equal.'),
            'orderBy' => $schema
                ->string()
                ->description('Dimension or metric name to sort by.'),
            'orderDirection' => $schema
                ->string()
                ->description('"asc" or "desc" (default "desc").'),
            'limit' => $schema
                ->integer()
                ->description('Max rows to return (default 10).'),
            'offset' => $schema
                ->integer()
                ->description('Pagination offset (default 0).'),
        ];
    }
}
