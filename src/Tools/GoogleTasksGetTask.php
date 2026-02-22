<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksGetTask implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'Get full details of a single Google Task by its ID.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Tasks integration is not configured.';
            }

            $listId = $request['listId'] ?? '@default';
            $taskId = $request['taskId'] ?? '';

            if (empty($taskId)) {
                return 'Error: taskId is required.';
            }

            $task = $this->service->getTask($listId, $taskId);

            return json_encode(GoogleTasksService::formatTask($task), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
                ->description('Task list ID. Use "@default" for the primary "My Tasks" list.'),
            'taskId' => $schema
                ->string()
                ->description('Task ID to retrieve.')
                ->required(),
        ];
    }
}
