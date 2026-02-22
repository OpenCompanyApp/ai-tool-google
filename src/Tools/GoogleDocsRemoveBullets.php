<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsRemoveBullets implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Remove bullet or numbered list formatting from a range in a Google Docs document.';
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

            $startIndex = $request['startIndex'] ?? null;
            $endIndex = $request['endIndex'] ?? null;
            if ($startIndex === null || $endIndex === null) {
                return 'Error: startIndex and endIndex are required.';
            }

            $requests = [
                ['deleteParagraphBullets' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return "Bullets removed from range [{$startIndex}-{$endIndex}].";
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
            'startIndex' => $schema
                ->integer()
                ->description('Start index of the range.')
                ->required(),
            'endIndex' => $schema
                ->integer()
                ->description('End index of the range.')
                ->required(),
        ];
    }
}
