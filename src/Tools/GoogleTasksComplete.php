<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksComplete implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'Mark a Google Task as completed.';
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

            $task = $this->service->updateTask($listId, $taskId, [
                'status' => 'completed',
            ]);

            $title = $task['title'] ?? '';

            return "Task completed: \"{$title}\"";
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
                ->description('Task ID to complete.')
                ->required(),
            'listId' => $schema
                ->string()
                ->description('Task list ID (default: "@default").'),
        ];
    }
}
