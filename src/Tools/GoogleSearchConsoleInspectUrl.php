<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleInspectUrl implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function description(): string
    {
        return <<<'MD'
        Check a URL's indexing status in Google Search Console. Returns: index verdict, coverage state, last crawl time, robots.txt state, indexing state, rich results, mobile usability, and AMP status.
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
                return 'Error: siteUrl is required.';
            }

            $url = $request['url'] ?? '';
            if (empty($url)) {
                return 'Error: url is required (full URL to inspect).';
            }

            $result = $this->service->inspectUrl($siteUrl, $url);
            $inspection = $result['inspectionResult'] ?? $result;

            $output = [
                'url' => $url,
            ];

            // Index status
            $indexStatus = $inspection['indexStatusResult'] ?? [];
            if (! empty($indexStatus)) {
                $output['indexStatus'] = [
                    'verdict' => $indexStatus['verdict'] ?? '',
                    'coverageState' => $indexStatus['coverageState'] ?? '',
                    'robotsTxtState' => $indexStatus['robotsTxtState'] ?? '',
                    'indexingState' => $indexStatus['indexingState'] ?? '',
                    'lastCrawlTime' => $indexStatus['lastCrawlTime'] ?? '',
                    'pageFetchState' => $indexStatus['pageFetchState'] ?? '',
                    'crawledAs' => $indexStatus['crawledAs'] ?? '',
                ];

                // Remove empty values
                $output['indexStatus'] = array_filter($output['indexStatus'], fn ($v) => $v !== '');
            }

            // Mobile usability
            $mobile = $inspection['mobileUsabilityResult'] ?? [];
            if (! empty($mobile)) {
                $output['mobileUsability'] = [
                    'verdict' => $mobile['verdict'] ?? '',
                ];
                if (! empty($mobile['issues'])) {
                    $output['mobileUsability']['issues'] = $mobile['issues'];
                }
            }

            // Rich results
            $rich = $inspection['richResultsResult'] ?? [];
            if (! empty($rich)) {
                $output['richResults'] = [
                    'verdict' => $rich['verdict'] ?? '',
                ];
                $items = $rich['detectedItems'] ?? [];
                if (! empty($items)) {
                    $output['richResults']['detectedItems'] = array_map(
                        fn (array $item) => $item['richResultType'] ?? 'unknown',
                        $items
                    );
                }
            }

            // AMP
            $amp = $inspection['ampResult'] ?? [];
            if (! empty($amp)) {
                $output['amp'] = [
                    'verdict' => $amp['verdict'] ?? '',
                ];
            }

            // Link to Search Console UI
            if (isset($inspection['inspectionResultLink'])) {
                $output['inspectionLink'] = $inspection['inspectionResultLink'];
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
                ->description('Site property URL (e.g., "sc-domain:example.com" or "https://www.example.com/").')
                ->required(),
            'url' => $schema
                ->string()
                ->description('Full URL to inspect (e.g., "https://www.example.com/page").')
                ->required(),
        ];
    }
}
