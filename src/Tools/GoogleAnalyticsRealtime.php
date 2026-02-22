<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleAnalyticsService;

class GoogleAnalyticsRealtime implements Tool
{
    public function __construct(
        private GoogleAnalyticsService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Run a GA4 realtime report showing activity in the last 30 minutes.

        Common dimensions: country, city, deviceCategory, unifiedScreenName (page/screen), platform.
        Common metrics: activeUsers, screenPageViews, eventCount, conversions.
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

            /** @var array<string, mixed> $params */
            $params = [
                'dimensions' => $request['dimensions'] ?? [],
                'metrics' => $request['metrics'] ?? ['activeUsers'],
                'limit' => $request['limit'] ?? 10,
            ];

            $body = $this->service->buildRealtimeBody($params);
            $result = $this->service->runRealtimeReport((string) $propertyId, $body);

            return $this->formatRealtimeResponse($result, $params);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Format a realtime report response.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $params
     */
    private function formatRealtimeResponse(array $result, array $params): string
    {
        $rows = $result['rows'] ?? [];
        $dimensionHeaders = $result['dimensionHeaders'] ?? [];
        $metricHeaders = $result['metricHeaders'] ?? [];

        $header = 'Realtime Report (last 30 minutes)';

        if (empty($rows)) {
            return "{$header}\n\nNo active users right now.";
        }

        $dimNames = array_map(fn (array $h) => $h['name'] ?? '', $dimensionHeaders);
        $metricNames = array_map(fn (array $h) => $h['name'] ?? '', $metricHeaders);

        // No dimensions — aggregate
        if (empty($dimNames)) {
            $lines = [$header, ''];
            $metricValues = $rows[0]['metricValues'] ?? [];
            foreach ($metricNames as $i => $name) {
                $value = $metricValues[$i]['value'] ?? '0';
                $lines[] = "{$name}: " . $this->formatNumber($value);
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

        foreach ($widths as $col => $w) {
            if ($w > 40) {
                $widths[$col] = 40;
            }
        }

        $lines = [$header, ''];

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

        $lines[] = count($data) . ' rows returned';

        // Totals
        $totals = $result['totals'] ?? [];
        if (! empty($totals)) {
            $totalRow = $totals[0] ?? [];
            $totalMetrics = $totalRow['metricValues'] ?? [];
            $totalParts = [];
            foreach ($metricNames as $i => $name) {
                $totalParts[] = "{$name}=" . $this->formatNumber($totalMetrics[$i]['value'] ?? '0');
            }
            $lines[] = 'Totals: ' . implode('  ', $totalParts);
        }

        return implode("\n", $lines);
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
                ->description('Metric names (default: ["activeUsers"]). Common: activeUsers, screenPageViews, eventCount, conversions.'),
            'dimensions' => $schema
                ->array()
                ->description('Dimension names to group by (e.g., ["country", "unifiedScreenName"]). Optional — omit for aggregate totals.'),
            'limit' => $schema
                ->integer()
                ->description('Max rows to return (default 10).'),
        ];
    }
}
