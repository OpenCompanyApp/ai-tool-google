<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailReply implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return 'Reply to an existing Gmail message (maintains the thread).';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $messageId = $request['messageId'] ?? '';
            $threadId = $request['threadId'] ?? '';
            $body = $request['body'] ?? '';

            if (empty($messageId)) {
                return 'Error: messageId is required.';
            }
            if (empty($threadId)) {
                return 'Error: threadId is required.';
            }
            if (empty($body)) {
                return 'Error: body is required.';
            }

            // Fetch original message to get headers for reply
            $original = $this->service->getMessage($messageId, 'metadata');
            $payload = $original['payload'] ?? [];
            $originalFrom = GmailService::getHeader($payload, 'From');
            $originalSubject = GmailService::getHeader($payload, 'Subject');
            $originalMessageId = GmailService::getHeader($payload, 'Message-ID');

            // Reply to the sender
            $to = $request['to'] ?? $originalFrom;

            // Add Re: prefix if not present
            $subject = $originalSubject;
            if (! str_starts_with(strtolower($subject), 're:')) {
                $subject = "Re: {$subject}";
            }

            $raw = GmailService::buildRawMessage($to, $subject, $body, [
                'cc' => $request['cc'] ?? null,
                'bcc' => $request['bcc'] ?? null,
                'inReplyTo' => $originalMessageId,
                'references' => $originalMessageId,
            ]);

            $result = $this->service->sendMessage([
                'raw' => $raw,
                'threadId' => $threadId,
            ]);

            return "Reply sent successfully.\n" . json_encode([
                'id' => $result['id'] ?? '',
                'threadId' => $result['threadId'] ?? '',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            'messageId' => $schema
                ->string()
                ->description('Original message ID to reply to.')
                ->required(),
            'threadId' => $schema
                ->string()
                ->description('Thread ID to reply in.')
                ->required(),
            'body' => $schema
                ->string()
                ->description('Reply body text.')
                ->required(),
            'to' => $schema
                ->string()
                ->description('Recipient email address (defaults to original sender).'),
            'cc' => $schema
                ->string()
                ->description('CC recipients (comma-separated emails).'),
            'bcc' => $schema
                ->string()
                ->description('BCC recipients (comma-separated emails).'),
        ];
    }
}
