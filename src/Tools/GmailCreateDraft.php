<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailCreateDraft implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return 'Create a draft email in Gmail (not sent).';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $to = $request['to'] ?? '';
            $subject = $request['subject'] ?? '';
            $body = $request['body'] ?? '';

            if (empty($to)) {
                return 'Error: to is required.';
            }
            if (empty($subject)) {
                return 'Error: subject is required.';
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
            'to' => $schema
                ->string()
                ->description('Recipient email address.')
                ->required(),
            'subject' => $schema
                ->string()
                ->description('Email subject.')
                ->required(),
            'body' => $schema
                ->string()
                ->description('Email body text.'),
            'cc' => $schema
                ->string()
                ->description('CC recipients (comma-separated emails).'),
            'bcc' => $schema
                ->string()
                ->description('BCC recipients (comma-separated emails).'),
        ];
    }
}
