<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GmailService;
use OpenCompany\AiToolGoogle\Tools\GmailManage;
use OpenCompany\AiToolGoogle\Tools\GmailRead;
use OpenCompany\AiToolGoogle\Tools\GmailSearch;
use OpenCompany\AiToolGoogle\Tools\GmailSend;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GmailToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'gmail';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'email, messages, drafts, send, search',
            'description' => 'Email management',
            'icon' => 'ph:envelope',
            'logo' => 'simple-icons:gmail',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Gmail',
            'description' => 'Email search, reading, sending, and management',
            'icon' => 'ph:envelope',
            'logo' => 'simple-icons:gmail',
            'category' => 'communication',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/gmail.googleapis.com',
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
                'hint' => 'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> &rarr; Credentials &rarr; OAuth 2.0 Client IDs. Enable the <strong>Gmail API</strong> first.',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=gmail',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Gmail" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/gmail/v1/users/me/profile');

            if ($response->successful()) {
                $email = $response->json('emailAddress') ?? $connectedEmail ?? 'unknown';
                $total = $response->json('messagesTotal') ?? 0;

                return [
                    'success' => true,
                    'message' => "Connected to Gmail as {$email}. {$total} total messages.",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Gmail API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'gmail_search' => [
                'class' => GmailSearch::class,
                'type' => 'read',
                'name' => 'Search Emails',
                'description' => 'Search and list email messages.',
                'icon' => 'ph:magnifying-glass',
            ],
            'gmail_read' => [
                'class' => GmailRead::class,
                'type' => 'read',
                'name' => 'Read Email',
                'description' => 'Get full email content.',
                'icon' => 'ph:envelope-open',
            ],
            'gmail_send' => [
                'class' => GmailSend::class,
                'type' => 'write',
                'name' => 'Send Email',
                'description' => 'Send emails or create/send drafts.',
                'icon' => 'ph:paper-plane-tilt',
            ],
            'gmail_manage' => [
                'class' => GmailManage::class,
                'type' => 'write',
                'name' => 'Manage Email',
                'description' => 'Labels, read/unread, trash, and archive.',
                'icon' => 'ph:tag',
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
        $service = app(GmailService::class);

        return new $class($service);
    }
}
