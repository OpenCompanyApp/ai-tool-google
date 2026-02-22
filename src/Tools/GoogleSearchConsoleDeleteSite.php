<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleDeleteSite implements Tool
{
    public function __construct(
        private GoogleSearchConsoleService $service,
    ) {}

    public function description(): string
    {
        return 'Remove a site property from Google Search Console.';
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

            $this->service->deleteSite($siteUrl);

            return "Site property removed: {$siteUrl}";
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
                ->description('Site property URL to remove.')
                ->required(),
        ];
    }
}
