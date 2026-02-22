<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleAddSite implements Tool
{
    public function __construct(
        private GoogleSearchConsoleService $service,
    ) {}

    public function description(): string
    {
        return 'Add a new site property to Google Search Console.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Search Console integration is not configured.';
            }

            $siteUrl = $request['siteUrl'] ?? '';
            if (empty($siteUrl)) {
                return 'Error: siteUrl is required (e.g., "https://example.com/" or "sc-domain:example.com").';
            }

            $this->service->addSite($siteUrl);

            return "Site property added: {$siteUrl}";
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
                ->description('Site property URL (e.g., "https://example.com/" for URL-prefix or "sc-domain:example.com" for domain property).')
                ->required(),
        ];
    }
}
