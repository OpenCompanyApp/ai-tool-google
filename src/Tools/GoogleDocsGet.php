<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsGet implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Get the content of a Google Docs document. Returns plain text by default, or a structured outline with character indexes when format is "structured". The document ID is the long string in the URL: docs.google.com/document/d/{documentId}/edit';
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

            $format = $request['format'] ?? 'text';
            $document = $this->service->getDocument((string) $documentId);

            $title = $document['title'] ?? 'Untitled';
            $docId = $document['documentId'] ?? $documentId;

            if ($format === 'structured') {
                return $this->formatStructuredOutput($document, $title, (string) $docId);
            }

            // Default: plain text
            $text = $this->service->extractText($document);

            if (trim($text) === '') {
                return "Document: \"$title\" (id: $docId)\n\nThis document is empty.";
            }

            return "Document: \"$title\" (id: $docId)\n\n$text";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function formatStructuredOutput(array $document, string $title, string $docId): string
    {
        $structure = $this->service->extractStructure($document);

        if (empty($structure)) {
            return "Document: \"$title\" (id: $docId)\n\nThis document is empty.";
        }

        $lines = ["Document: \"$title\" (id: $docId)", '', 'Structure:'];

        $maxIndex = 0;
        foreach ($structure as $item) {
            $startIndex = (int) $item['startIndex'];
            $endIndex = (int) $item['endIndex'];
            $type = (string) $item['type'];
            $text = (string) $item['text'];

            if ($endIndex > $maxIndex) {
                $maxIndex = $endIndex;
            }

            if ($type === 'TABLE') {
                $rows = (int) ($item['rows'] ?? 0);
                $columns = (int) ($item['columns'] ?? 0);
                $lines[] = "[{$startIndex}-{$endIndex}] TABLE: {$rows} rows x {$columns} columns";
            } else {
                // Truncate text preview to 80 chars
                $preview = mb_strlen($text) > 80 ? mb_substr($text, 0, 77) . '...' : $text;
                $lines[] = "[{$startIndex}-{$endIndex}] {$type}: \"$preview\"";
            }
        }

        $lines[] = '';
        $lines[] = "Total: {$maxIndex} characters";

        return implode("\n", $lines);
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
            'format' => $schema
                ->string()
                ->description('"text" (default, plain text) or "structured" (outline with character indexes for editing).'),
        ];
    }
}