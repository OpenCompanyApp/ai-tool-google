<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsAddQuestion;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsAddSection;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsAddTextItem;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsCreate;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsDeleteItem;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsGet;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsGetResponse;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsListResponses;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsMoveItem;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsPublish;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsUpdateInfo;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsUpdateQuestion;
use OpenCompany\AiToolGoogle\Tools\GoogleFormsUpdateSettings;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleFormsToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_forms';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'forms, surveys, quizzes, questionnaires, feedback, polls',
            'description' => 'Create, manage, and read responses from Google Forms',
            'icon' => 'ph:list-checks',
            'logo' => 'simple-icons:googleforms',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Forms',
            'description' => 'Create surveys, quizzes, and forms with questions of any type, and read responses',
            'icon' => 'ph:list-checks',
            'logo' => 'simple-icons:googleforms',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/forms.googleapis.com',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_forms',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Forms" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                $email = $response->json('email') ?? $connectedEmail;
                $emailInfo = $email ? " ({$email})" : '';

                return [
                    'success' => true,
                    'message' => "Google Forms connected{$emailInfo}.",
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
            'google_forms_get' => [
                'class' => GoogleFormsGet::class,
                'type' => 'read',
                'name' => 'Forms Get',
                'description' => 'Get form structure and questions.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_list_responses' => [
                'class' => GoogleFormsListResponses::class,
                'type' => 'read',
                'name' => 'Forms List Responses',
                'description' => 'List form responses with question labels.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_get_response' => [
                'class' => GoogleFormsGetResponse::class,
                'type' => 'read',
                'name' => 'Forms Get Response',
                'description' => 'Get a single form response by ID.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_create' => [
                'class' => GoogleFormsCreate::class,
                'type' => 'write',
                'name' => 'Forms Create',
                'description' => 'Create a new form.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_update_info' => [
                'class' => GoogleFormsUpdateInfo::class,
                'type' => 'write',
                'name' => 'Forms Update Info',
                'description' => 'Update form title and description.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_update_settings' => [
                'class' => GoogleFormsUpdateSettings::class,
                'type' => 'write',
                'name' => 'Forms Update Settings',
                'description' => 'Update form settings (quiz mode, email collection).',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_add_question' => [
                'class' => GoogleFormsAddQuestion::class,
                'type' => 'write',
                'name' => 'Forms Add Question',
                'description' => 'Add a question to a form.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_update_question' => [
                'class' => GoogleFormsUpdateQuestion::class,
                'type' => 'write',
                'name' => 'Forms Update Question',
                'description' => 'Update a question in a form.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_delete_item' => [
                'class' => GoogleFormsDeleteItem::class,
                'type' => 'write',
                'name' => 'Forms Delete Item',
                'description' => 'Delete an item from a form.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_move_item' => [
                'class' => GoogleFormsMoveItem::class,
                'type' => 'write',
                'name' => 'Forms Move Item',
                'description' => 'Move an item within a form.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_add_section' => [
                'class' => GoogleFormsAddSection::class,
                'type' => 'write',
                'name' => 'Forms Add Section',
                'description' => 'Add a section/page break to a form.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_add_text_item' => [
                'class' => GoogleFormsAddTextItem::class,
                'type' => 'write',
                'name' => 'Forms Add Text Item',
                'description' => 'Add a static text block to a form.',
                'icon' => 'ph:list-checks',
            ],
            'google_forms_publish' => [
                'class' => GoogleFormsPublish::class,
                'type' => 'write',
                'name' => 'Forms Publish',
                'description' => 'Publish/unpublish form and manage response acceptance.',
                'icon' => 'ph:list-checks',
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
        $service = app(GoogleFormsService::class);

        return new $class($service);
    }
}