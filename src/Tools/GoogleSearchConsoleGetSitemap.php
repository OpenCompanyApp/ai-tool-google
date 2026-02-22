<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleGetSitemap implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function description(): string
    {
        return <<<'MD'
        Get details of a specific sitemap in Google Search Console. Returns the sitemap's path, last submitted/downloaded dates, whether it's a sitemap index, and content type breakdown with submitted vs indexed URL counts.
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

            $sitemapUrl = $request['sitemapUrl'] ?? '';
            if (empty($sitemapUrl)) {
                return 'Error: sitemapUrl is required.';
            }

            $result = $this->service->getSitemap($siteUrl, $sitemapUrl);

            $output = [
                'path' => $result['path'] ?? $sitemapUrl,
                'lastSubmitted' => $result['lastSubmitted'] ?? '',
                'lastDownloaded' => $result['lastDownloaded'] ?? '',
                'isSitemapIndex' => $result['isSitemapIndex'] ?? false,
            ];

            $contents = $result['contents'] ?? [];
            if (! empty($contents)) {
                $output['contents'] = array_map(fn (array $c) => [
                    'type' => $c['type'] ?? '',
                    'submitted' => (int) ($c['submitted'] ?? 0),
                    'indexed' => (int) ($c['indexed'] ?? 0),
                ], $contents);
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
            'sitemapUrl' => $schema
                ->string()
                ->description('Full URL of the sitemap (e.g., "https://www.example.com/sitemap.xml").')
                ->required(),
        ];
    }
}
