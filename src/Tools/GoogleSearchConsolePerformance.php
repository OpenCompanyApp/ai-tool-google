<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsolePerformance implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function description(): string
    {
        return <<<'MD'
        Query Google Search Console search performance data (clicks, impressions, CTR, position). Common queries: "top pages by clicks" → dimensions=["page"]. "Top search queries" → dimensions=["query"]. "Traffic trend" → dimensions=["date"]. "Mobile vs desktop" → dimensions=["device"]. "Blog section" → dimensions=["page"], filters=[{dimension:"page", operator:"contains", value:"/blog/"}]. Combine dimensions: dimensions=["query","device"] for queries by device.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Search Console integration is not configured.';
            }

            $siteUrl = $request['siteUrl'] ?? '';
            if (empty($siteUrl)) {
                return 'Error: siteUrl is required. Use google_search_console_list_sites to find your property URL.';
            }

            // Default date range: last 28 days (30 days ago to 3 days ago for data freshness)
            $endDate = $request['endDate'] ?? date('Y-m-d', strtotime('-3 days'));
            $startDate = $request['startDate'] ?? date('Y-m-d', strtotime('-30 days'));

            $body = [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];

            // Dimensions
            $dimensions = $request['dimensions'] ?? [];
            if (is_array($dimensions) && ! empty($dimensions)) {
                $body['dimensions'] = array_values($dimensions);
            }

            // Filters
            $filters = $request['filters'] ?? [];
            if (is_array($filters) && ! empty($filters)) {
                $filterList = [];
                foreach ($filters as $filter) {
                    if (is_array($filter) && isset($filter['dimension'], $filter['value'])) {
                        $filterList[] = [
                            'dimension' => $filter['dimension'],
                            'operator' => $filter['operator'] ?? 'contains',
                            'expression' => $filter['value'],
                        ];
                    }
                }
                if (! empty($filterList)) {
                    $body['dimensionFilterGroups'] = [
                        ['filters' => $filterList],
                    ];
                }
            }

            // Row limit and offset
            $limit = isset($request['limit']) ? min((int) $request['limit'], 25000) : 1000;
            $body['rowLimit'] = $limit;

            $offset = (int) ($request['offset'] ?? 0);
            if ($offset > 0) {
                $body['startRow'] = $offset;
            }

            // Search type
            $type = $request['type'] ?? '';
            if ($type !== '' && is_string($type)) {
                $body['type'] = $type;
            }

            // Data state
            $dataState = $request['dataState'] ?? '';
            if ($dataState !== '' && is_string($dataState)) {
                $body['dataState'] = $dataState;
            }

            // Aggregation type
            $aggregationType = $request['aggregationType'] ?? '';
            if ($aggregationType !== '' && is_string($aggregationType)) {
                $body['aggregationType'] = $aggregationType;
            }

            $result = $this->service->queryAnalytics($siteUrl, $body);
            $rows = $result['rows'] ?? [];

            if (empty($rows)) {
                return json_encode([
                    'dateRange' => "{$startDate} to {$endDate}",
                    'rows' => 0,
                    'data' => [],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
            }

            $formatted = [];
            foreach ($rows as $row) {
                $entry = [];

                // Keys correspond to dimensions in order
                $keys = $row['keys'] ?? [];
                $dims = is_array($dimensions) ? array_values($dimensions) : [];
                foreach ($keys as $i => $key) {
                    $dimName = $dims[$i] ?? "dimension_{$i}";
                    $entry[$dimName] = $key;
                }

                $entry['clicks'] = (int) ($row['clicks'] ?? 0);
                $entry['impressions'] = (int) ($row['impressions'] ?? 0);
                $entry['ctr'] = round((float) ($row['ctr'] ?? 0), 4);
                $entry['position'] = round((float) ($row['position'] ?? 0), 1);

                $formatted[] = $entry;
            }

            $output = [
                'dateRange' => "{$startDate} to {$endDate}",
                'rows' => count($formatted),
                'data' => $formatted,
            ];

            if (isset($result['responseAggregationType'])) {
                $output['aggregationType'] = $result['responseAggregationType'];
            }

            return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'siteUrl' => $schema
                ->string()
                ->description('Site property URL (e.g., "sc-domain:example.com" or "https://www.example.com/"). Use google_search_console_list_sites to find it.')
                ->required(),
            'startDate' => $schema
                ->string()
                ->description('Start date (YYYY-MM-DD). Default: 30 days ago.'),
            'endDate' => $schema
                ->string()
                ->description('End date (YYYY-MM-DD). Default: 3 days ago.'),
            'dimensions' => $schema
                ->array()
                ->description('Dimensions to group by (array). Options: "query", "page", "country", "device", "date", "searchAppearance". Combine multiple.'),
            'filters' => $schema
                ->array()
                ->description('Filters: array of {dimension, operator, value}. Operators: "contains", "equals", "notContains", "notEquals".'),
            'limit' => $schema
                ->integer()
                ->description('Max rows (default 1000, max 25000).'),
            'offset' => $schema
                ->integer()
                ->description('Pagination offset (default 0).'),
            'type' => $schema
                ->string()
                ->description('Search type: "web" (default), "discover", "image", "video", "news", "googleNews".'),
            'dataState' => $schema
                ->string()
                ->description('Data state: "final" (default, reliable) or "all" (includes fresh/unprocessed).'),
            'aggregationType' => $schema
                ->string()
                ->description('Aggregation: "auto" (default), "byPage", or "byProperty".'),
        ];
    }
}
