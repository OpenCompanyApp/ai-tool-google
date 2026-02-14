<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailManage implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Manage Gmail messages. Actions:
        - **mark_read**: Mark a message as read.
        - **mark_unread**: Mark a message as unread.
        - **trash**: Move a message to trash.
        - **untrash**: Remove a message from trash.
        - **archive**: Archive a message (remove from inbox).
        - **add_labels**: Add labels to a message.
        - **remove_labels**: Remove labels from a message.

        For batch operations on multiple messages, provide messageIds (comma-separated) instead of messageId.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $action = $request['action'] ?? '';
            if (empty($action)) {
                return 'Error: action is required (mark_read, mark_unread, trash, untrash, archive, add_labels, remove_labels).';
            }

            return match ($action) {
                'mark_read' => $this->modifyLabels($request, removeLabelIds: ['UNREAD']),
                'mark_unread' => $this->modifyLabels($request, addLabelIds: ['UNREAD']),
                'trash' => $this->trash($request),
                'untrash' => $this->untrash($request),
                'archive' => $this->modifyLabels($request, removeLabelIds: ['INBOX']),
                'add_labels' => $this->addLabels($request),
                'remove_labels' => $this->removeLabels($request),
                default => "Error: Unknown action '{$action}'. Use: mark_read, mark_unread, trash, untrash, archive, add_labels, remove_labels.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @param  array<int, string>  $addLabelIds
     * @param  array<int, string>  $removeLabelIds
     */
    private function modifyLabels(Request $request, array $addLabelIds = [], array $removeLabelIds = []): string
    {
        $messageIds = $this->getMessageIds($request);
        if ($messageIds === null) {
            return 'Error: messageId or messageIds is required.';
        }

        $data = [];
        if (! empty($addLabelIds)) {
            $data['addLabelIds'] = $addLabelIds;
        }
        if (! empty($removeLabelIds)) {
            $data['removeLabelIds'] = $removeLabelIds;
        }

        if (count($messageIds) === 1) {
            $this->service->modifyMessage($messageIds[0], $data);
        } else {
            $data['ids'] = $messageIds;
            $this->service->batchModifyMessages($data);
        }

        $action = $request['action'] ?? 'modified';
        $count = count($messageIds);

        return "{$count} message(s) {$action} successfully.";
    }

    private function trash(Request $request): string
    {
        $messageIds = $this->getMessageIds($request);
        if ($messageIds === null) {
            return 'Error: messageId or messageIds is required.';
        }

        foreach ($messageIds as $id) {
            $this->service->trashMessage($id);
        }

        $count = count($messageIds);

        return "{$count} message(s) moved to trash.";
    }

    private function untrash(Request $request): string
    {
        $messageIds = $this->getMessageIds($request);
        if ($messageIds === null) {
            return 'Error: messageId or messageIds is required.';
        }

        foreach ($messageIds as $id) {
            $this->service->untrashMessage($id);
        }

        $count = count($messageIds);

        return "{$count} message(s) removed from trash.";
    }

    private function addLabels(Request $request): string
    {
        $labelIds = $request['labelIds'] ?? '';
        if (empty($labelIds)) {
            return 'Error: labelIds is required for add_labels action.';
        }

        $labels = array_map('trim', explode(',', $labelIds));

        return $this->modifyLabels($request, addLabelIds: $labels);
    }

    private function removeLabels(Request $request): string
    {
        $labelIds = $request['labelIds'] ?? '';
        if (empty($labelIds)) {
            return 'Error: labelIds is required for remove_labels action.';
        }

        $labels = array_map('trim', explode(',', $labelIds));

        return $this->modifyLabels($request, removeLabelIds: $labels);
    }

    /**
     * Get message IDs from either messageId or messageIds parameter.
     *
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
            'action' => $schema
                ->string()
                ->description('Action: "mark_read", "mark_unread", "trash", "untrash", "archive", "add_labels", or "remove_labels".')
                ->required(),
            'messageId' => $schema
                ->string()
                ->description('Single message ID to act on.'),
            'messageIds' => $schema
                ->string()
                ->description('Comma-separated message IDs for batch operations.'),
            'labelIds' => $schema
                ->string()
                ->description('Comma-separated label IDs (required for add_labels, remove_labels).'),
        ];
    }
}
