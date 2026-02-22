<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksUpdate implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'Update task fields in Google Tasks. At least one field to update is required.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Tasks integration is not configured.';
            }

            $taskId = $request['taskId'] ?? '';
            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }

            $listId = $request['listId'] ?? '@default';

            $data = [];

            if (isset($request['title'])) {
                $data['title'] = $request['title'];
            }

            if (isset($request['notes'])) {
                $data['notes'] = $request['notes'];
            }

            if (isset($request['due'])) {
                $due = $request['due'];
                $data['due'] = ! empty($due) ? $due . 'T00:00:00.000Z' : null;
            }

            if (isset($request['status'])) {
                $data['status'] = $request['status'];
            }

            if (empty($data)) {
                return 'Error: at least one field to update is required (title, notes, due, status).';
            }

            $task = $this->service->updateTask($listId, $taskId, $data);
            $result = GoogleTasksService::formatTask($task);

            return "Task updated: \"{$result['title']}\" — status: {$result['status']}" .
                (! empty($result['due']) ? ", due: {$result['due']}" : '');
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
            'taskId' => $schema
                ->string()
                ->description('Task ID to update.')
                ->required(),
            'listId' => $schema
                ->string()
                ->description('Task list ID (default: "@default").'),
            'title' => $schema
                ->string()
                ->description('New task title.'),
            'notes' => $schema
                ->string()
                ->description('Task notes/description (max 8192 chars).'),
            'due' => $schema
                ->string()
                ->description('Due date in YYYY-MM-DD format. Set empty string to clear.'),
            'status' => $schema
                ->string()
                ->description('Task status: "needsAction" (open) or "completed".'),
        ];
    }
}
