<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleListSitemaps implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function description(): string
    {
        return <<<'MD'
        List all submitted sitemaps for a Google Search Console property. Returns each sitemap's path, last submitted/downloaded dates, whether it's a sitemap index, and content type counts (submitted vs indexed URLs).
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

            $result = $this->service->listSitemaps($siteUrl);
            $sitemaps = $result['sitemap'] ?? [];

            if (empty($sitemaps)) {
                return 'No sitemaps found.';
            }

            $formatted = [];
            foreach ($sitemaps as $sitemap) {
                $entry = [
                    'path' => $sitemap['path'] ?? '',
                    'lastSubmitted' => $sitemap['lastSubmitted'] ?? '',
                    'lastDownloaded' => $sitemap['lastDownloaded'] ?? '',
                    'isSitemapIndex' => $sitemap['isSitemapIndex'] ?? false,
                ];

                $contents = $sitemap['contents'] ?? [];
                if (! empty($contents)) {
                    $entry['contents'] = array_map(fn (array $c) => array_filter([
                        'type' => $c['type'] ?? '',
                        'submitted' => (int) ($c['submitted'] ?? 0),
                        'indexed' => (int) ($c['indexed'] ?? 0),
                    ], fn ($v) => $v !== '' && $v !== 0), $contents);
                }

                $formatted[] = $entry;
            }

            return json_encode([
                'count' => count($formatted),
                'sitemaps' => $formatted,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
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
        ];
    }
}
