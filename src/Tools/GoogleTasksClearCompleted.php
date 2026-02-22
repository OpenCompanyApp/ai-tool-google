<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;

class GoogleTasksClearCompleted implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function description(): string
    {
        return 'Remove all completed tasks from a Google Tasks list. Warning: permanently deletes completed tasks.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Tasks integration is not configured.';
            }

            $listId = $request['listId'] ?? '@default';

            $this->service->clearCompleted($listId);

            return 'All completed tasks cleared from list.';
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
                ->description('Task list ID (default: "@default").'),
        ];
    }
}
