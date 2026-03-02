<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GmailService;
use OpenCompany\AiToolGoogle\Tools\GmailAddLabels;
use OpenCompany\AiToolGoogle\Tools\GmailArchive;
use OpenCompany\AiToolGoogle\Tools\GmailCreateDraft;
use OpenCompany\AiToolGoogle\Tools\GmailMarkRead;
use OpenCompany\AiToolGoogle\Tools\GmailMarkUnread;
use OpenCompany\AiToolGoogle\Tools\GmailRead;
use OpenCompany\AiToolGoogle\Tools\GmailRemoveLabels;
use OpenCompany\AiToolGoogle\Tools\GmailReply;
use OpenCompany\AiToolGoogle\Tools\GmailCountBySender;
use OpenCompany\AiToolGoogle\Tools\GmailListLabels;
use OpenCompany\AiToolGoogle\Tools\GmailSaveAttachment;
use OpenCompany\AiToolGoogle\Tools\GmailSearchEmails;
use OpenCompany\AiToolGoogle\Tools\GmailSendDraft;
use OpenCompany\AiToolGoogle\Tools\GmailSendEmail;
use OpenCompany\AiToolGoogle\Tools\GmailTrash;
use OpenCompany\AiToolGoogle\Tools\GmailUntrash;
use OpenCompany\IntegrationCore\Contracts\AgentFileStorage;
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
            'gmail_search_emails' => [
                'class' => GmailSearchEmails::class,
                'type' => 'read',
                'name' => 'Search Emails',
                'description' => 'Search email messages.',
                'icon' => 'ph:magnifying-glass',
            ],
            'gmail_count_by_sender' => [
                'class' => GmailCountBySender::class,
                'type' => 'read',
                'name' => 'Count by Sender',
                'description' => 'Count messages grouped by sender.',
                'icon' => 'ph:chart-bar',
            ],
            'gmail_list_labels' => [
                'class' => GmailListLabels::class,
                'type' => 'read',
                'name' => 'List Labels',
                'description' => 'List all mailbox labels.',
                'icon' => 'ph:tag',
            ],
            'gmail_read' => [
                'class' => GmailRead::class,
                'type' => 'read',
                'name' => 'Read Email',
                'description' => 'Get full email content.',
                'icon' => 'ph:envelope-open',
            ],
            'gmail_send_email' => [
                'class' => GmailSendEmail::class,
                'type' => 'write',
                'name' => 'Send Email',
                'description' => 'Send an email directly.',
                'icon' => 'ph:paper-plane-tilt',
            ],
            'gmail_create_draft' => [
                'class' => GmailCreateDraft::class,
                'type' => 'write',
                'name' => 'Create Draft',
                'description' => 'Create a draft email.',
                'icon' => 'ph:note',
            ],
            'gmail_send_draft' => [
                'class' => GmailSendDraft::class,
                'type' => 'write',
                'name' => 'Send Draft',
                'description' => 'Send a previously created draft.',
                'icon' => 'ph:paper-plane-tilt',
            ],
            'gmail_reply' => [
                'class' => GmailReply::class,
                'type' => 'write',
                'name' => 'Reply',
                'description' => 'Reply to an existing email thread.',
                'icon' => 'ph:arrow-bend-up-left',
            ],
            'gmail_mark_read' => [
                'class' => GmailMarkRead::class,
                'type' => 'write',
                'name' => 'Mark Read',
                'description' => 'Mark messages as read.',
                'icon' => 'ph:envelope-open',
            ],
            'gmail_mark_unread' => [
                'class' => GmailMarkUnread::class,
                'type' => 'write',
                'name' => 'Mark Unread',
                'description' => 'Mark messages as unread.',
                'icon' => 'ph:envelope',
            ],
            'gmail_trash' => [
                'class' => GmailTrash::class,
                'type' => 'write',
                'name' => 'Trash',
                'description' => 'Move messages to trash.',
                'icon' => 'ph:trash',
            ],
            'gmail_untrash' => [
                'class' => GmailUntrash::class,
                'type' => 'write',
                'name' => 'Untrash',
                'description' => 'Restore messages from trash.',
                'icon' => 'ph:arrow-counter-clockwise',
            ],
            'gmail_archive' => [
                'class' => GmailArchive::class,
                'type' => 'write',
                'name' => 'Archive',
                'description' => 'Archive messages (remove from inbox).',
                'icon' => 'ph:archive',
            ],
            'gmail_add_labels' => [
                'class' => GmailAddLabels::class,
                'type' => 'write',
                'name' => 'Add Labels',
                'description' => 'Add labels to messages.',
                'icon' => 'ph:tag',
            ],
            'gmail_remove_labels' => [
                'class' => GmailRemoveLabels::class,
                'type' => 'write',
                'name' => 'Remove Labels',
                'description' => 'Remove labels from messages.',
                'icon' => 'ph:tag',
            ],
            'gmail_save_attachment' => [
                'class' => GmailSaveAttachment::class,
                'type' => 'write',
                'name' => 'Save Attachment',
                'description' => 'Download and save an email attachment to workspace files.',
                'icon' => 'ph:paperclip',
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

        if ($class === GmailSaveAttachment::class) {
            $fileStorage = app()->bound(AgentFileStorage::class) ? app(AgentFileStorage::class) : null;
            $agent = $context['agent'] ?? null;

            if (! $fileStorage || ! $agent) {
                throw new \RuntimeException('GmailSaveAttachment requires AgentFileStorage and an agent context.');
            }

            return new $class($service, $fileStorage, $agent);
        }

        return new $class($service);
    }
}
