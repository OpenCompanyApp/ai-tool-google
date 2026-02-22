<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksClearCompleted;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksComplete;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksCreate;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksCreateList;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksDelete;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksDeleteList;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksMove;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksGetTask;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksListLists;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksListTasks;
use OpenCompany\AiToolGoogle\Tools\GoogleTasksUpdate;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleTasksToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_tasks';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'tasks, to-do, checklist, reminders',
            'description' => 'Task management',
            'icon' => 'ph:check-square',
            'logo' => 'simple-icons:googletasks',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Tasks',
            'description' => 'Task lists, to-dos, and checklist management',
            'icon' => 'ph:check-square',
            'logo' => 'simple-icons:googletasks',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/tasks.googleapis.com',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_tasks',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Tasks" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://tasks.googleapis.com/tasks/v1/users/@me/lists', [
                'maxResults' => '100',
            ]);

            if ($response->successful()) {
                $items = $response->json('items') ?? [];
                $count = count($items);
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return [
                    'success' => true,
                    'message' => "Connected to Google Tasks{$emailInfo}. {$count} task " . ($count === 1 ? 'list' : 'lists') . '.',
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Tasks API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_tasks_list_lists' => [
                'class' => GoogleTasksListLists::class,
                'type' => 'read',
                'name' => 'List Task Lists',
                'description' => 'List all task lists.',
                'icon' => 'ph:list-checks',
            ],
            'google_tasks_list_tasks' => [
                'class' => GoogleTasksListTasks::class,
                'type' => 'read',
                'name' => 'List Tasks',
                'description' => 'List tasks in a task list.',
                'icon' => 'ph:list-checks',
            ],
            'google_tasks_get_task' => [
                'class' => GoogleTasksGetTask::class,
                'type' => 'read',
                'name' => 'Get Task',
                'description' => 'Get full details of a single task.',
                'icon' => 'ph:list-checks',
            ],
            'google_tasks_create' => [
                'class' => GoogleTasksCreate::class,
                'type' => 'write',
                'name' => 'Create Task',
                'description' => 'Create a new task.',
                'icon' => 'ph:plus',
            ],
            'google_tasks_update' => [
                'class' => GoogleTasksUpdate::class,
                'type' => 'write',
                'name' => 'Update Task',
                'description' => 'Update task fields.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_tasks_complete' => [
                'class' => GoogleTasksComplete::class,
                'type' => 'write',
                'name' => 'Complete Task',
                'description' => 'Mark a task as completed.',
                'icon' => 'ph:check',
            ],
            'google_tasks_delete' => [
                'class' => GoogleTasksDelete::class,
                'type' => 'write',
                'name' => 'Delete Task',
                'description' => 'Delete a task.',
                'icon' => 'ph:trash',
            ],
            'google_tasks_move' => [
                'class' => GoogleTasksMove::class,
                'type' => 'write',
                'name' => 'Move Task',
                'description' => 'Reorder or reparent a task.',
                'icon' => 'ph:arrows-left-right',
            ],
            'google_tasks_clear_completed' => [
                'class' => GoogleTasksClearCompleted::class,
                'type' => 'write',
                'name' => 'Clear Completed',
                'description' => 'Remove all completed tasks from a list.',
                'icon' => 'ph:broom',
            ],
            'google_tasks_create_list' => [
                'class' => GoogleTasksCreateList::class,
                'type' => 'write',
                'name' => 'Create List',
                'description' => 'Create a new task list.',
                'icon' => 'ph:list-plus',
            ],
            'google_tasks_delete_list' => [
                'class' => GoogleTasksDeleteList::class,
                'type' => 'write',
                'name' => 'Delete List',
                'description' => 'Delete a task list.',
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
        $service = app(GoogleTasksService::class);

        return new $class($service);
    }
}
