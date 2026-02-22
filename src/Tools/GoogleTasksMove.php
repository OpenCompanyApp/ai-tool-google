<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksMove implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'Reorder or reparent a Google Task. Use parent to set a new parent (empty string moves to top level), and previous to position after a sibling.';
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

            $parent = isset($request['parent']) ? (string) $request['parent'] : null;
            $previous = isset($request['previous']) ? (string) $request['previous'] : null;

            if ($parent === null && $previous === null) {
                return 'Error: at least one of parent or previous is required.';
            }

            // Empty string for parent means "move to top level" -- pass it to the API
            $task = $this->service->moveTask($listId, $taskId, $parent, $previous);
            $result = GoogleTasksService::formatTask($task);

            $description = "Task moved: \"{$result['title']}\"";
            if ($parent !== null && $parent !== '') {
                $description .= " — now subtask of {$parent}";
            } elseif ($parent === '') {
                $description .= ' — moved to top level';
            }

            return $description;
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
                ->description('Task ID to move.')
                ->required(),
            'listId' => $schema
                ->string()
                ->description('Task list ID (default: "@default").'),
            'parent' => $schema
                ->string()
                ->description('New parent task ID. Empty string moves to top level.'),
            'previous' => $schema
                ->string()
                ->description('Sibling task ID to insert after.'),
        ];
    }
}
