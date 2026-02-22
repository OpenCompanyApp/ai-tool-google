<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleAnalyticsService;
use OpenCompany\AiToolGoogle\Tools\GoogleAnalyticsListProperties;
use OpenCompany\AiToolGoogle\Tools\GoogleAnalyticsMetadata;
use OpenCompany\AiToolGoogle\Tools\GoogleAnalyticsRealtime;
use OpenCompany\AiToolGoogle\Tools\GoogleAnalyticsReport;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleAnalyticsToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_analytics';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'analytics, traffic, visitors, pageviews, conversions, revenue',
            'description' => 'Website analytics and reporting',
            'icon' => 'ph:chart-bar',
            'logo' => 'simple-icons:googleanalytics',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Analytics',
            'description' => 'Website traffic, audience insights, and conversion reporting',
            'icon' => 'ph:chart-bar',
            'logo' => 'simple-icons:googleanalytics',
            'category' => 'analytics',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_analytics',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Analytics" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', [
                'pageSize' => '200',
            ]);

            if ($response->successful()) {
                $accounts = $response->json('accountSummaries') ?? [];
                $propertyCount = 0;
                foreach ($accounts as $account) {
                    $propertyCount += count($account['propertySummaries'] ?? []);
                }
                $accountCount = count($accounts);
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return [
                    'success' => true,
                    'message' => "Google Analytics connected{$emailInfo}. {$accountCount} " . ($accountCount === 1 ? 'account' : 'accounts') . ", {$propertyCount} " . ($propertyCount === 1 ? 'property' : 'properties') . '.',
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Analytics API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_analytics_list_properties' => [
                'class' => GoogleAnalyticsListProperties::class,
                'type' => 'read',
                'name' => 'List GA4 Properties',
                'description' => 'List all accessible GA4 properties with IDs and names.',
                'icon' => 'ph:list-bullets',
            ],
            'google_analytics_report' => [
                'class' => GoogleAnalyticsReport::class,
                'type' => 'read',
                'name' => 'Analytics Report',
                'description' => 'Run a GA4 analytics report with dimensions, metrics, filters, and date ranges.',
                'icon' => 'ph:chart-bar',
            ],
            'google_analytics_realtime' => [
                'class' => GoogleAnalyticsRealtime::class,
                'type' => 'read',
                'name' => 'Analytics Realtime',
                'description' => 'Run a GA4 realtime report showing activity in the last 30 minutes.',
                'icon' => 'ph:pulse',
            ],
            'google_analytics_metadata' => [
                'class' => GoogleAnalyticsMetadata::class,
                'type' => 'read',
                'name' => 'Analytics Metadata',
                'description' => 'List all available dimensions and metrics for a GA4 property.',
                'icon' => 'ph:info',
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
        $service = app(GoogleAnalyticsService::class);

        return new $class($service);
    }
}
