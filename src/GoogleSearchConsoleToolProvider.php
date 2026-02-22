<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleAddSite;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleDeleteSite;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleDeleteSitemap;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleGetSitemap;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleInspectUrl;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleListSitemaps;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleListSites;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsolePerformance;
use OpenCompany\AiToolGoogle\Tools\GoogleSearchConsoleSubmitSitemap;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleSearchConsoleToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_search_console';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'search, seo, indexing, sitemaps, performance, clicks, impressions',
            'description' => 'Search performance and indexing',
            'icon' => 'ph:chart-line-up',
            'logo' => 'simple-icons:googlesearchconsole',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Search Console',
            'description' => 'Search performance, URL indexing, and sitemap management',
            'icon' => 'ph:chart-line-up',
            'logo' => 'simple-icons:googlesearchconsole',
            'category' => 'analytics',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/searchconsole.googleapis.com',
        ];
    }

    public function configSchema(): array
    {
        return [
            [
                'key' => 'client_id',
                'type' => 'text',
                'label' => 'Client ID',
                'placeholder' => 'Your Google Cloud OAuth Client ID',
                'hint' => 'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> &rarr; Credentials &rarr; OAuth 2.0 Client IDs. Shared across all Google integrations &mdash; only needs to be entered once.',
                'required' => true,
            ],
            [
                'key' => 'client_secret',
                'type' => 'secret',
                'label' => 'Client Secret',
                'placeholder' => 'Your Google Cloud OAuth Client Secret',
                'required' => true,
            ],
            [
                'key' => 'access_token',
                'type' => 'oauth_connect',
                'label' => 'Google Account',
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_search_console',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Search Console" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/webmasters/v3/sites');

            if ($response->successful()) {
                $sites = $response->json('siteEntry') ?? [];
                $count = count($sites);
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return [
                    'success' => true,
                    'message' => "Search Console connected{$emailInfo}. {$count} verified " . ($count === 1 ? 'property' : 'properties') . '.',
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Search Console API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, string|array<int, string>> */
    public function validationRules(): array
    {
        return [
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'access_token' => 'nullable|string',
        ];
    }

    public function tools(): array
    {
        return [
            'google_search_console_list_sites' => [
                'class' => GoogleSearchConsoleListSites::class,
                'type' => 'read',
                'name' => 'List Sites',
                'description' => 'List all verified Search Console properties.',
                'icon' => 'ph:globe',
            ],
            'google_search_console_performance' => [
                'class' => GoogleSearchConsolePerformance::class,
                'type' => 'read',
                'name' => 'Search Performance',
                'description' => 'Query search performance data (clicks, impressions, CTR, position).',
                'icon' => 'ph:chart-line-up',
            ],
            'google_search_console_inspect_url' => [
                'class' => GoogleSearchConsoleInspectUrl::class,
                'type' => 'read',
                'name' => 'Inspect URL',
                'description' => 'Check a URL\'s indexing status.',
                'icon' => 'ph:magnifying-glass',
            ],
            'google_search_console_list_sitemaps' => [
                'class' => GoogleSearchConsoleListSitemaps::class,
                'type' => 'read',
                'name' => 'List Sitemaps',
                'description' => 'List all submitted sitemaps for a property.',
                'icon' => 'ph:list-bullets',
            ],
            'google_search_console_get_sitemap' => [
                'class' => GoogleSearchConsoleGetSitemap::class,
                'type' => 'read',
                'name' => 'Get Sitemap',
                'description' => 'Get details of a specific sitemap.',
                'icon' => 'ph:file-text',
            ],
            'google_search_console_submit_sitemap' => [
                'class' => GoogleSearchConsoleSubmitSitemap::class,
                'type' => 'write',
                'name' => 'Submit Sitemap',
                'description' => 'Submit a new sitemap.',
                'icon' => 'ph:file-arrow-up',
            ],
            'google_search_console_delete_sitemap' => [
                'class' => GoogleSearchConsoleDeleteSitemap::class,
                'type' => 'write',
                'name' => 'Delete Sitemap',
                'description' => 'Remove a sitemap.',
                'icon' => 'ph:trash',
            ],
            'google_search_console_add_site' => [
                'class' => GoogleSearchConsoleAddSite::class,
                'type' => 'write',
                'name' => 'Add Site',
                'description' => 'Add a new site property.',
                'icon' => 'ph:plus',
            ],
            'google_search_console_delete_site' => [
                'class' => GoogleSearchConsoleDeleteSite::class,
                'type' => 'write',
                'name' => 'Delete Site',
                'description' => 'Remove a site property.',
                'icon' => 'ph:trash',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function createTool(string $class, array $context = []): Tool
    {
        $service = app(GoogleSearchConsoleService::class);

        return new $class($service);
    }
}
