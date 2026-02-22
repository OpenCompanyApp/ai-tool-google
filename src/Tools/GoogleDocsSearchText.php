<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsSearchText implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Find all occurrences of text in a Google Docs document with their start/end indexes. Useful before format_text or delete_range operations. The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit';
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

            $query = $request['query'] ?? '';
            if (empty($query)) {
                return 'Error: query is required.';
            }

            $matchCase = (bool) ($request['matchCase'] ?? false);

            $document = $this->service->getDocument((string) $documentId);
            $title = $document['title'] ?? 'Untitled';
            $docId = $document['documentId'] ?? $documentId;

            $occurrences = $this->service->findText($document, (string) $query, $matchCase);

            if (empty($occurrences)) {
                return "No occurrences of \"$query\" found in \"$title\" (id: $docId).";
            }

            $count = count($occurrences);
            $lines = ["{$count} " . ($count === 1 ? 'occurrence' : 'occurrences') . " of \"$query\" in \"$title\" (id: $docId):", ''];

            foreach ($occurrences as $i => $occurrence) {
                $num = $i + 1;
                $start = $occurrence['startIndex'];
                $end = $occurrence['endIndex'];
                $text = $occurrence['text'];
                $lines[] = "{$num}. [{$start}-{$end}] \"$text\"";
            }

            return implode("\n", $lines);
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
            'query' => $schema
                ->string()
                ->description('Text to search for.')
                ->required(),
            'matchCase' => $schema
                ->boolean()
                ->description('Case-sensitive search (default false).'),
        ];
    }
}
