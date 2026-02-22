<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksListTasks implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'List tasks in a Google Task list. Use "@default" as listId for the primary "My Tasks" list. Supports filtering by completion status and due date range.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Tasks integration is not configured.';
            }

            $listId = $request['listId'] ?? '@default';

            $params = [];

            if (isset($request['showCompleted'])) {
                $params['showCompleted'] = (bool) $request['showCompleted'];
            }

            if (isset($request['showHidden'])) {
                $params['showHidden'] = (bool) $request['showHidden'];
            }

            if (isset($request['dueMin'])) {
                $params['dueMin'] = $request['dueMin'];
            }

            if (isset($request['dueMax'])) {
                $params['dueMax'] = $request['dueMax'];
            }

            if (isset($request['maxResults'])) {
                $params['maxResults'] = (int) $request['maxResults'];
            }

            if (isset($request['pageToken'])) {
                $params['pageToken'] = $request['pageToken'];
            }

            $result = $this->service->listTasks($listId, $params);
            $items = $result['items'] ?? [];

            if (empty($items)) {
                return 'No tasks found in this list.';
            }

            $tasks = array_map(
                fn (array $task) => GoogleTasksService::formatTask($task),
                $items
            );

            $output = count($tasks) . " task(s):\n" . json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $nextPageToken = $result['nextPageToken'] ?? null;
            if ($nextPageToken) {
                $output .= "\n\nMore results available. Use pageToken: \"{$nextPageToken}\"";
            }

            return $output;
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
            'showCompleted' => $schema
                ->boolean()
                ->description('Include completed tasks (default: false).'),
            'showHidden' => $schema
                ->boolean()
                ->description('Include hidden tasks (default: false).'),
            'dueMin' => $schema
                ->string()
                ->description('Filter tasks with due date on or after this date (YYYY-MM-DD).'),
            'dueMax' => $schema
                ->string()
                ->description('Filter tasks with due date on or before this date (YYYY-MM-DD).'),
            'maxResults' => $schema
                ->integer()
                ->description('Maximum number of results (default: 100, max: 100).'),
            'pageToken' => $schema
                ->string()
                ->description('Page token for pagination (from previous response).'),
        ];
    }
}
