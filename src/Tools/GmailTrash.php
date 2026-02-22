<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailTrash implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return 'Move one or more Gmail messages to trash. Provide messageIds (comma-separated) for batch operations.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $messageIds = $this->getMessageIds($request);
            if ($messageIds === null) {
                return 'Error: messageId or messageIds is required.';
            }

            foreach ($messageIds as $id) {
                $this->service->trashMessage($id);
            }

            $count = count($messageIds);

            return "{$count} message(s) moved to trash.";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function getMessageIds(Request $request): ?array
    {
        if (isset($request['messageIds'])) {
            $ids = array_map('trim', explode(',', $request['messageIds']));

            return array_filter($ids, fn (string $id) => $id !== '');
        }

        if (isset($request['messageId']) && $request['messageId'] !== '') {
            return [$request['messageId']];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'messageId' => $schema
                ->string()
                ->description('Single message ID to trash.'),
            'messageIds' => $schema
                ->string()
                ->description('Comma-separated message IDs for batch operations.'),
        ];
    }
}
