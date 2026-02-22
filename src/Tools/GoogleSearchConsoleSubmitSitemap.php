<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleSubmitSitemap implements Tool
{
    public function __construct(
        private GoogleSearchConsoleService $service,
    ) {}

    public function description(): string
    {
        return 'Submit a new sitemap to Google Search Console.';
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
                return 'Error: sitemapUrl is required (e.g., "https://example.com/sitemap.xml").';
            }

            $this->service->submitSitemap($siteUrl, $sitemapUrl);

            return "Sitemap submitted: {$sitemapUrl}";
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
                ->description('Full sitemap URL (e.g., "https://example.com/sitemap.xml").')
                ->required(),
        ];
    }
}
