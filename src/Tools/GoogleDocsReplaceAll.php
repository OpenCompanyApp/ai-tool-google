<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsReplaceAll implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Find and replace all occurrences of text in a Google Docs document. No indexes needed — this is the simplest way to edit text.';
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

            $find = $request['find'] ?? '';
            if (empty($find)) {
                return 'Error: find is required.';
            }

            $replace = $request['replace'] ?? '';
            $matchCase = (bool) ($request['matchCase'] ?? true);

            $requests = [
                ['replaceAllText' => [
                    'containsText' => [
                        'text' => (string) $find,
                        'matchCase' => $matchCase,
                    ],
                    'replaceText' => (string) $replace,
                ]],
            ];

            $result = $this->service->batchUpdate((string) $documentId, $requests);

            // Extract replacement count from response
            $replies = $result['replies'] ?? [];
            $count = 0;
            foreach ($replies as $reply) {
                $count += (int) ($reply['replaceAllText']['occurrencesChanged'] ?? 0);
            }

            if ($count === 0) {
                return "No occurrences of \"$find\" found.";
            }

            return "Replaced {$count} " . ($count === 1 ? 'occurrence' : 'occurrences') . " of \"$find\" with \"$replace\".";
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
            'find' => $schema
                ->string()
                ->description('Text to find.')
                ->required(),
            'replace' => $schema
                ->string()
                ->description('Replacement text.')
                ->required(),
            'matchCase' => $schema
                ->boolean()
                ->description('Case-sensitive match (default true).'),
        ];
    }
}
