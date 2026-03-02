<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;
use OpenCompany\IntegrationCore\Contracts\AgentFileStorage;

class GmailSaveAttachment implements Tool
{
    public function __construct(
        private GmailService $service,
        private AgentFileStorage $fileStorage,
        private object $agent,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Download an email attachment and save it to workspace files.
        Requires a messageId and attachmentId (both returned by gmail_read).
        The file is saved under the agent's folder and can be browsed in the Files page.
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

            $attachmentId = $request['attachmentId'] ?? '';
            if (empty($attachmentId)) {
                return 'Error: attachmentId is required.';
            }

            $filename = $request['filename'] ?? '';
            if (empty($filename)) {
                return 'Error: filename is required.';
            }

            $mimeType = $request['mimeType'] ?? 'application/octet-stream';

            $bytes = $this->service->getAttachment($messageId, $attachmentId);

            if (empty($bytes)) {
                return 'Error: Attachment is empty or could not be downloaded.';
            }

            $result = $this->fileStorage->saveFile($this->agent, $filename, $bytes, $mimeType, 'gmail');

            return json_encode([
                'filename' => $filename,
                'path' => $result['path'],
                'url' => $result['url'],
                'size' => strlen($bytes),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'messageId' => $schema
                ->string()
                ->description('Gmail message ID containing the attachment.')
                ->required(),
            'attachmentId' => $schema
                ->string()
                ->description('Attachment ID from the gmail_read response.')
                ->required(),
            'filename' => $schema
                ->string()
                ->description('Filename to save as (e.g. "invoice.pdf"). Use the filename from gmail_read.')
                ->required(),
            'mimeType' => $schema
                ->string()
                ->description('MIME type of the attachment (e.g. "application/pdf"). Use the mimeType from gmail_read.'),
        ];
    }
}
