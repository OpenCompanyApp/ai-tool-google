<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksCreate implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'Create a task in Google Tasks. Use "@default" as listId for the primary "My Tasks" list.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Tasks integration is not configured.';
            }

            $title = $request['title'] ?? '';
            if (empty($title)) {
                return 'Error: title is required.';
            }

            $listId = $request['listId'] ?? '@default';

            $data = ['title' => $title];

            if (! empty($request['notes'])) {
                $data['notes'] = $request['notes'];
            }

            if (! empty($request['due'])) {
                $data['due'] = $request['due'] . 'T00:00:00.000Z';
            }

            $parent = ! empty($request['parent']) ? $request['parent'] : null;

            $task = $this->service->createTask($listId, $data, $parent);

            $result = GoogleTasksService::formatTask($task);

            return "Task created: \"{$result['title']}\" (ID: {$result['id']})" .
                (! empty($result['due']) ? " — due {$result['due']}" : '') .
                ($parent ? ' — subtask' : '');
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
            'title' => $schema
                ->string()
                ->description('Task title.')
                ->required(),
            'listId' => $schema
                ->string()
                ->description('Task list ID (default: "@default" for primary "My Tasks" list).'),
            'notes' => $schema
                ->string()
                ->description('Task notes/description (max 8192 chars).'),
            'due' => $schema
                ->string()
                ->description('Due date in YYYY-MM-DD format.'),
            'parent' => $schema
                ->string()
                ->description('Parent task ID to create as subtask.'),
        ];
    }
}
