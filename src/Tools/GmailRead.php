<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailRead implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Read the full content of a Gmail message by its ID.
        Returns headers (From, To, Subject, Date), the decoded text body, and a list of attachments.
        Use gmail_search first to find message IDs, then use this tool to read the full content.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $messageId = $request['messageId'] ?? '';
            if (empty($messageId)) {
                return 'Error: messageId is required.';
            }

            $format = $request['format'] ?? 'full';
            $msg = $this->service->getMessage($messageId, $format);
            $payload = $msg['payload'] ?? [];

            $output = [
                'id' => $msg['id'] ?? '',
                'threadId' => $msg['threadId'] ?? '',
                'from' => GmailService::getHeader($payload, 'From'),
                'to' => GmailService::getHeader($payload, 'To'),
                'cc' => GmailService::getHeader($payload, 'Cc'),
                'subject' => GmailService::getHeader($payload, 'Subject'),
                'date' => GmailService::getHeader($payload, 'Date'),
                'labelIds' => $msg['labelIds'] ?? [],
                'snippet' => $msg['snippet'] ?? '',
            ];

            // Extract body
            if ($format === 'full') {
                $body = GmailService::extractBody($payload);
                $output['body'] = $body;
            }

            // List attachments
            $attachments = $this->extractAttachments($payload);
            if (! empty($attachments)) {
                $output['attachments'] = $attachments;
            }

            // Remove empty values
            $output = array_filter($output, fn ($v) => $v !== '' && $v !== []);

            return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Extract attachment metadata from message payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, string>>
     */
    private function extractAttachments(array $payload): array
    {
        $attachments = [];
        $parts = $payload['parts'] ?? [];

        foreach ($parts as $part) {
            $filename = $part['filename'] ?? '';
            if ($filename !== '' && ! empty($part['body']['attachmentId'])) {
                $attachments[] = [
                    'filename' => $filename,
                    'mimeType' => $part['mimeType'] ?? '',
                    'size' => (string) ($part['body']['size'] ?? 0),
                    'attachmentId' => $part['body']['attachmentId'],
                ];
            }

            // Recurse into nested parts
            if (! empty($part['parts'])) {
                $nested = $this->extractAttachments($part);
                $attachments = array_merge($attachments, $nested);
            }
        }

        return $attachments;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'messageId' => $schema
                ->string()
                ->description('Gmail message ID to read.')
                ->required(),
            'format' => $schema
                ->string()
                ->description('Response format: "full" (default, includes body), "metadata" (headers only), "minimal" (IDs only).'),
        ];
    }
}
