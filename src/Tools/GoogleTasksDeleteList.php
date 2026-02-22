<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksDeleteList implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'Delete a task list from Google Tasks.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Tasks integration is not configured.';
            }

            $listId = $request['listId'] ?? '';
            if (empty($listId)) {
                return 'Error: listId is required.';
            }

            $this->service->deleteTaskList($listId);

            return 'Task list deleted.';
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
            'listId' => $schema
                ->string()
                ->description('Task list ID to delete.')
                ->required(),
        ];
    }
}
