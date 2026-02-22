<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksListLists implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'List all Google Task lists. Returns IDs and titles. Start here to discover available lists.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Tasks integration is not configured.';
            }

            $maxResults = isset($request['maxResults']) ? (int) $request['maxResults'] : null;
            $pageToken = $request['pageToken'] ?? null;

            $result = $this->service->listTaskLists($maxResults, $pageToken);
            $items = $result['items'] ?? [];

            if (empty($items)) {
                return 'No task lists found.';
            }

            $lists = [];
            foreach ($items as $list) {
                $lists[] = [
                    'id' => $list['id'] ?? '',
                    'title' => $list['title'] ?? '',
                    'updated' => isset($list['updated']) ? substr((string) $list['updated'], 0, 10) : null,
                ];
            }

            $output = count($lists) . " task list(s):\n" . json_encode($lists, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

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
            'maxResults' => $schema
                ->integer()
                ->description('Maximum number of results (default: 100, max: 100).'),
            'pageToken' => $schema
                ->string()
                ->description('Page token for pagination (from previous response).'),
        ];
    }
}
