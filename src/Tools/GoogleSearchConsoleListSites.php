<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleListSites implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function description(): string
    {
        return <<<'MD'
        List all verified Google Search Console sites/properties with their permission levels. Use this first to discover available properties before querying performance data or inspecting URLs. Returns each site's URL (e.g., "sc-domain:example.com" or "https://www.example.com/") and your permission level.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Search Console integration is not configured.';
            }

            $result = $this->service->listSites();
            $sites = $result['siteEntry'] ?? [];

            if (empty($sites)) {
                return 'No verified sites found.';
            }

            $formatted = array_map(fn (array $site) => [
                'siteUrl' => $site['siteUrl'] ?? '',
                'permissionLevel' => $site['permissionLevel'] ?? '',
            ], $sites);

            return json_encode([
                'count' => count($formatted),
                'sites' => $formatted,
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
        return [];
    }
}
