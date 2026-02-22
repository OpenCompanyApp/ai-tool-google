<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsAddBullets;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsCreate;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsDeleteRange;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsFormatText;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsGet;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsGetStructure;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsInsertImage;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsInsertPageBreak;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsInsertTable;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsInsertText;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsRemoveBullets;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsReplaceAll;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsSearchText;
use OpenCompany\AiToolGoogle\Tools\GoogleDocsSetHeading;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleDocsToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_docs';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'documents, writing, text, editing, formatting, docs',
            'description' => 'Create, read, and edit Google Docs',
            'icon' => 'ph:file-doc',
            'logo' => 'simple-icons:googledocs',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Docs',
            'description' => 'Create, read, and edit documents with formatting, tables, and images',
            'icon' => 'ph:file-doc',
            'logo' => 'simple-icons:googledocs',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/docs.googleapis.com',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_docs',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Docs" to authorize.'];
        }

        try {
            // Verify the token works by fetching user info
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                $email = $response->json('email') ?? $connectedEmail;
                $emailInfo = $email ? " ({$email})" : '';

                return [
                    'success' => true,
                    'message' => "Google Docs connected{$emailInfo}.",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Google API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_docs_get' => [
                'class' => GoogleDocsGet::class,
                'type' => 'read',
                'name' => 'Docs Get',
                'description' => 'Get document content as text or structured outline.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_get_structure' => [
                'class' => GoogleDocsGetStructure::class,
                'type' => 'read',
                'name' => 'Docs Get Structure',
                'description' => 'Get document structure with heading hierarchy and indexes.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_search_text' => [
                'class' => GoogleDocsSearchText::class,
                'type' => 'read',
                'name' => 'Docs Search Text',
                'description' => 'Find text occurrences with start/end indexes.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_create' => [
                'class' => GoogleDocsCreate::class,
                'type' => 'write',
                'name' => 'Docs Create',
                'description' => 'Create a new blank document.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_insert_text' => [
                'class' => GoogleDocsInsertText::class,
                'type' => 'write',
                'name' => 'Docs Insert Text',
                'description' => 'Insert text at a position or end of document.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_replace_all' => [
                'class' => GoogleDocsReplaceAll::class,
                'type' => 'write',
                'name' => 'Docs Replace All',
                'description' => 'Find and replace all occurrences of text.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_delete_range' => [
                'class' => GoogleDocsDeleteRange::class,
                'type' => 'write',
                'name' => 'Docs Delete Range',
                'description' => 'Delete content by index range.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_format_text' => [
                'class' => GoogleDocsFormatText::class,
                'type' => 'write',
                'name' => 'Docs Format Text',
                'description' => 'Apply formatting (bold, italic, font, color, etc.) to a text range.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_set_heading' => [
                'class' => GoogleDocsSetHeading::class,
                'type' => 'write',
                'name' => 'Docs Set Heading',
                'description' => 'Set paragraph style (heading level, title, subtitle).',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_add_bullets' => [
                'class' => GoogleDocsAddBullets::class,
                'type' => 'write',
                'name' => 'Docs Add Bullets',
                'description' => 'Add bullet or numbered list formatting.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_remove_bullets' => [
                'class' => GoogleDocsRemoveBullets::class,
                'type' => 'write',
                'name' => 'Docs Remove Bullets',
                'description' => 'Remove list formatting from a range.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_insert_table' => [
                'class' => GoogleDocsInsertTable::class,
                'type' => 'write',
                'name' => 'Docs Insert Table',
                'description' => 'Insert a table with specified rows and columns.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_insert_image' => [
                'class' => GoogleDocsInsertImage::class,
                'type' => 'write',
                'name' => 'Docs Insert Image',
                'description' => 'Insert an image from a URL.',
                'icon' => 'ph:file-doc',
            ],
            'google_docs_insert_page_break' => [
                'class' => GoogleDocsInsertPageBreak::class,
                'type' => 'write',
                'name' => 'Docs Insert Page Break',
                'description' => 'Insert a page break.',
                'icon' => 'ph:file-doc',
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
        $service = app(GoogleDocsService::class);

        return new $class($service);
    }
}
