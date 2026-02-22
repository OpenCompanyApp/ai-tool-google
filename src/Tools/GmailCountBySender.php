<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailCountBySender implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return 'Count all matching Gmail messages grouped by sender. Automatically paginates through ALL results (handles thousands of messages). Returns top senders sorted by count. Use for questions like "who sends me the most email?" or "count unread by sender".';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $params = ['maxResults' => '500'];

            if (isset($request['query'])) {
                $params['q'] = $request['query'];
            }
            if (isset($request['labelIds'])) {
                $params['labelIds'] = $request['labelIds'];
            }

            /** @var array<string, int> $senderCounts */
            $senderCounts = [];
            $totalProcessed = 0;
            $maxMessages = 10000; // Safety limit

            do {
                $result = $this->service->listMessages($params);
                $messageRefs = $result['messages'] ?? [];

                if (empty($messageRefs)) {
                    break;
                }

                // Fetch only From header for each message
                $ids = array_map(fn (array $ref) => $ref['id'] ?? '', $messageRefs);
                $ids = array_filter($ids, fn (string $id) => $id !== '');

                foreach ($ids as $msgId) {
                    $msg = $this->service->getMessage($msgId, 'metadata', ['From']);
                    $from = GmailService::getHeader($msg['payload'] ?? [], 'From');

                    if ($from !== '') {
                        $normalized = $this->normalizeFrom($from);
                        $senderCounts[$normalized] = ($senderCounts[$normalized] ?? 0) + 1;
                    }
                }

                $totalProcessed += count($ids);
                $params['pageToken'] = $result['nextPageToken'] ?? null;

            } while (! empty($params['pageToken']) && $totalProcessed < $maxMessages);

            if (empty($senderCounts)) {
                return 'No messages found.';
            }

            // Sort by count descending
            arsort($senderCounts);

            $topSenders = array_slice($senderCounts, 0, 50, true);
            $formatted = [];
            foreach ($topSenders as $sender => $count) {
                $formatted[] = ['sender' => $sender, 'count' => $count];
            }

            $output = [
                'totalMessages' => $totalProcessed,
                'uniqueSenders' => count($senderCounts),
                'topSenders' => $formatted,
            ];

            if ($totalProcessed >= $maxMessages) {
                $output['note'] = "Reached limit of {$maxMessages} messages. Results may be partial.";
            }

            return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)  ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Normalize a From header to just the email address.
     * "John Doe <john@example.com>" → "john@example.com"
     * "john@example.com" → "john@example.com"
     */
    private function normalizeFrom(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return strtolower($matches[1]);
        }

        return strtolower(trim($from));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('Gmail search query to filter messages (e.g., "is:unread", "after:2026-01-01").'),
            'labelIds' => $schema
                ->string()
                ->description('Comma-separated label IDs to filter by.'),
        ];
    }
}
