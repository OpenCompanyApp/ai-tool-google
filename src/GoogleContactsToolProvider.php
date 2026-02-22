<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;
use OpenCompany\AiToolGoogle\Tools\GoogleContactsCreate;
use OpenCompany\AiToolGoogle\Tools\GoogleContactsDelete;
use OpenCompany\AiToolGoogle\Tools\GoogleContactsGet;
use OpenCompany\AiToolGoogle\Tools\GoogleContactsList;
use OpenCompany\AiToolGoogle\Tools\GoogleContactsListGroups;
use OpenCompany\AiToolGoogle\Tools\GoogleContactsSearchContacts;
use OpenCompany\AiToolGoogle\Tools\GoogleContactsUpdate;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleContactsToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_contacts';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'contacts, address book, people, phone, email lookup',
            'description' => 'Contact management',
            'icon' => 'ph:address-book',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Contacts',
            'description' => 'Contact search, lookup, and management',
            'icon' => 'ph:address-book',
            'category' => 'communication',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/people.googleapis.com',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_contacts',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Contacts" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://people.googleapis.com/v1/people/me/connections', [
                'personFields' => 'names',
                'pageSize' => '1',
            ]);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $total = $data['totalPeople'] ?? $data['totalItems'] ?? 0;
                $email = $connectedEmail ?? 'your account';

                return [
                    'success' => true,
                    'message' => "Connected to Google Contacts ({$email}). {$total} contacts.",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'People API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_contacts_search_contacts' => [
                'class' => GoogleContactsSearchContacts::class,
                'type' => 'read',
                'name' => 'Search Contacts',
                'description' => 'Fuzzy search contacts by name, email, or phone.',
                'icon' => 'ph:magnifying-glass',
            ],
            'google_contacts_get' => [
                'class' => GoogleContactsGet::class,
                'type' => 'read',
                'name' => 'Get Contact',
                'description' => 'Get full details of a single contact.',
                'icon' => 'ph:user',
            ],
            'google_contacts_list' => [
                'class' => GoogleContactsList::class,
                'type' => 'read',
                'name' => 'List Contacts',
                'description' => 'List all contacts with pagination.',
                'icon' => 'ph:address-book',
            ],
            'google_contacts_list_groups' => [
                'class' => GoogleContactsListGroups::class,
                'type' => 'read',
                'name' => 'List Groups',
                'description' => 'List all contact groups/labels with member counts.',
                'icon' => 'ph:users-three',
            ],
            'google_contacts_create' => [
                'class' => GoogleContactsCreate::class,
                'type' => 'write',
                'name' => 'Create Contact',
                'description' => 'Create a new contact.',
                'icon' => 'ph:user-plus',
            ],
            'google_contacts_update' => [
                'class' => GoogleContactsUpdate::class,
                'type' => 'write',
                'name' => 'Update Contact',
                'description' => 'Update an existing contact.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_contacts_delete' => [
                'class' => GoogleContactsDelete::class,
                'type' => 'write',
                'name' => 'Delete Contact',
                'description' => 'Permanently delete a contact.',
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
        $service = app(GoogleContactsService::class);

        return new $class($service);
    }
}
