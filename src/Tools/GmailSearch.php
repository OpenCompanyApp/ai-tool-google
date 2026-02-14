<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailSearch implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Search Gmail messages or list labels. Actions:
        - **search**: Search messages using Gmail query syntax (e.g., "from:alice subject:meeting is:unread has:attachment after:2026-02-01"). Returns message summaries with headers.
        - **list_labels**: List all labels in the mailbox (INBOX, SENT, custom labels, etc.).
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
                return 'Error: action is required (search, list_labels).';
            }

            return match ($action) {
                'search' => $this->search($request),
                'list_labels' => $this->listLabels(),
                default => "Error: Unknown action '{$action}'. Use: search, list_labels.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function search(Request $request): string
    {
        $params = [];

        if (isset($request['query'])) {
            $params['q'] = $request['query'];
        }
        if (isset($request['maxResults'])) {
            $params['maxResults'] = (string) min((int) $request['maxResults'], 100);
        } else {
            $params['maxResults'] = '10';
        }
        if (isset($request['pageToken'])) {
            $params['pageToken'] = $request['pageToken'];
        }
        if (isset($request['labelIds'])) {
            $params['labelIds'] = $request['labelIds'];
        }

        // Get message IDs
        $result = $this->service->listMessages($params);
        $messageRefs = $result['messages'] ?? [];

        if (empty($messageRefs)) {
            return 'No messages found.';
        }

        // Fetch metadata for each message
        $messages = [];
        foreach ($messageRefs as $ref) {
            $msgId = $ref['id'] ?? '';
            if (empty($msgId)) {
                continue;
            }

            $msg = $this->service->getMessage($msgId, 'metadata');
            $payload = $msg['payload'] ?? [];

            $messages[] = [
                'id' => $msg['id'] ?? '',
                'threadId' => $msg['threadId'] ?? '',
                'from' => GmailService::getHeader($payload, 'From'),
                'to' => GmailService::getHeader($payload, 'To'),
                'subject' => GmailService::getHeader($payload, 'Subject'),
                'date' => GmailService::getHeader($payload, 'Date'),
                'snippet' => $msg['snippet'] ?? '',
                'labelIds' => $msg['labelIds'] ?? [],
            ];
        }

        $output = ['count' => count($messages), 'messages' => $messages];
        if (isset($result['nextPageToken'])) {
            $output['nextPageToken'] = $result['nextPageToken'];
        }
        if (isset($result['resultSizeEstimate'])) {
            $output['resultSizeEstimate'] = $result['resultSizeEstimate'];
        }

        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function listLabels(): string
    {
        $result = $this->service->listLabels();
        $labels = $result['labels'] ?? [];

        if (empty($labels)) {
            return 'No labels found.';
        }

        $formatted = array_map(fn (array $label) => [
            'id' => $label['id'] ?? '',
            'name' => $label['name'] ?? '',
            'type' => $label['type'] ?? '',
        ], $labels);

        return json_encode(['count' => count($formatted), 'labels' => $formatted], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "search" or "list_labels".')
                ->required(),
            'query' => $schema
                ->string()
                ->description('Gmail search query (e.g., "from:alice subject:meeting is:unread"). For search.'),
            'maxResults' => $schema
                ->integer()
                ->description('Max results to return (default: 10, max: 100). For search.'),
            'pageToken' => $schema
                ->string()
                ->description('Pagination token from previous response. For search.'),
            'labelIds' => $schema
                ->string()
                ->description('Comma-separated label IDs to filter by. For search.'),
        ];
    }
}
