<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailSend implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Send emails or manage drafts. Actions:
        - **send**: Send an email directly.
        - **create_draft**: Create a draft email (not sent).
        - **send_draft**: Send a previously created draft by its ID.
        - **reply**: Reply to an existing message (maintains the thread).
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
                return 'Error: action is required (send, create_draft, send_draft, reply).';
            }

            return match ($action) {
                'send' => $this->sendEmail($request),
                'create_draft' => $this->createDraft($request),
                'send_draft' => $this->sendDraft($request),
                'reply' => $this->reply($request),
                default => "Error: Unknown action '{$action}'. Use: send, create_draft, send_draft, reply.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function sendEmail(Request $request): string
    {
        $to = $request['to'] ?? '';
        $subject = $request['subject'] ?? '';
        $body = $request['body'] ?? '';

        if (empty($to)) {
            return 'Error: to is required for send action.';
        }
        if (empty($subject)) {
            return 'Error: subject is required for send action.';
        }
        if (empty($body)) {
            return 'Error: body is required for send action.';
        }

        $raw = GmailService::buildRawMessage($to, $subject, $body, [
            'cc' => $request['cc'] ?? null,
            'bcc' => $request['bcc'] ?? null,
        ]);

        $result = $this->service->sendMessage(['raw' => $raw]);

        return "Email sent successfully.\n" . json_encode([
            'id' => $result['id'] ?? '',
            'threadId' => $result['threadId'] ?? '',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function createDraft(Request $request): string
    {
        $to = $request['to'] ?? '';
        $subject = $request['subject'] ?? '';
        $body = $request['body'] ?? '';

        if (empty($to)) {
            return 'Error: to is required for create_draft action.';
        }
        if (empty($subject)) {
            return 'Error: subject is required for create_draft action.';
        }

        $raw = GmailService::buildRawMessage($to, $subject, $body, [
            'cc' => $request['cc'] ?? null,
            'bcc' => $request['bcc'] ?? null,
        ]);

        $result = $this->service->createDraft([
            'message' => ['raw' => $raw],
        ]);

        return "Draft created successfully.\n" . json_encode([
            'draftId' => $result['id'] ?? '',
            'messageId' => $result['message']['id'] ?? '',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function sendDraft(Request $request): string
    {
        $draftId = $request['draftId'] ?? '';
        if (empty($draftId)) {
            return 'Error: draftId is required for send_draft action.';
        }

        $result = $this->service->sendDraft(['id' => $draftId]);

        return "Draft sent successfully.\n" . json_encode([
            'id' => $result['id'] ?? '',
            'threadId' => $result['threadId'] ?? '',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function reply(Request $request): string
    {
        $messageId = $request['messageId'] ?? '';
        $threadId = $request['threadId'] ?? '';
        $body = $request['body'] ?? '';

        if (empty($messageId)) {
            return 'Error: messageId is required for reply action.';
        }
        if (empty($threadId)) {
            return 'Error: threadId is required for reply action.';
        }
        if (empty($body)) {
            return 'Error: body is required for reply action.';
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
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "send", "create_draft", "send_draft", or "reply".')
                ->required(),
            'to' => $schema
                ->string()
                ->description('Recipient email address (required for send, create_draft; optional for reply — defaults to original sender).'),
            'subject' => $schema
                ->string()
                ->description('Email subject (required for send, create_draft; auto-set for reply).'),
            'body' => $schema
                ->string()
                ->description('Email body text (required for send, create_draft, reply).'),
            'cc' => $schema
                ->string()
                ->description('CC recipients (comma-separated emails).'),
            'bcc' => $schema
                ->string()
                ->description('BCC recipients (comma-separated emails).'),
            'draftId' => $schema
                ->string()
                ->description('Draft ID (required for send_draft).'),
            'messageId' => $schema
                ->string()
                ->description('Original message ID (required for reply).'),
            'threadId' => $schema
                ->string()
                ->description('Thread ID to reply in (required for reply).'),
        ];
    }
}
