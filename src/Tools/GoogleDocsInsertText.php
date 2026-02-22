<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsInsertText implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Insert text into a Google Docs document at a specific position or at the end. Omit index or set to -1 to append at end.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Docs integration is not configured.';
            }

            $documentId = $request['documentId'] ?? '';
            if (empty($documentId)) {
                return 'Error: documentId is required.';
            }

            $text = $request['text'] ?? '';
            if ($text === '') {
                return 'Error: text is required.';
            }

            $index = $request['index'] ?? -1;
            $atEnd = $index === -1;

            if ($atEnd) {
                $requests = [
                    ['insertText' => [
                        'endOfSegmentLocation' => ['segmentId' => ''],
                        'text' => (string) $text,
                    ]],
                ];
            } else {
                $requests = [
                    ['insertText' => [
                        'location' => ['index' => (int) $index],
                        'text' => (string) $text,
                    ]],
                ];
            }

            $this->service->batchUpdate((string) $documentId, $requests);

            $location = $atEnd ? 'end of document' : "index {$index}";

            return "Text inserted at {$location}.";
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
            'documentId' => $schema
                ->string()
                ->description('Google Docs document ID (from the URL).')
                ->required(),
            'text' => $schema
                ->string()
                ->description('Text to insert.')
                ->required(),
            'index' => $schema
                ->integer()
                ->description('Insert position (1-based). Omit or -1 for end of document.'),
        ];
    }
}
