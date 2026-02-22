<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsInsertTable implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Insert a table into a Google Docs document. Specify rows and columns. Omit index or set to -1 to insert at end.';
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

            $rows = $request['rows'] ?? null;
            $columns = $request['columns'] ?? null;
            if ($rows === null || $columns === null) {
                return 'Error: rows and columns are required.';
            }

            $index = $request['index'] ?? -1;
            $atEnd = $index === -1;

            $insertTable = [
                'rows' => (int) $rows,
                'columns' => (int) $columns,
            ];

            if ($atEnd) {
                $insertTable['endOfSegmentLocation'] = ['segmentId' => ''];
            } else {
                $insertTable['location'] = ['index' => (int) $index];
            }

            $requests = [['insertTable' => $insertTable]];

            $this->service->batchUpdate((string) $documentId, $requests);

            $location = $atEnd ? 'end of document' : "index {$index}";

            return "Table ({$rows} rows x {$columns} columns) inserted at {$location}.";
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
            'rows' => $schema
                ->integer()
                ->description('Number of rows.')
                ->required(),
            'columns' => $schema
                ->integer()
                ->description('Number of columns.')
                ->required(),
            'index' => $schema
                ->integer()
                ->description('Insert position (1-based). Omit or -1 for end of document.'),
        ];
    }
}
