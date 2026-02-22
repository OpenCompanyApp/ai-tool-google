<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsFind implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Search for text within a Google Spreadsheet. Searches all sheets by default, or specify a sheet name to narrow the search. Returns match count and number of sheets containing matches.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Sheets integration is not configured.';
            }

            $spreadsheetId = $request['spreadsheetId'] ?? '';
            if (empty($spreadsheetId)) {
                return 'Error: spreadsheetId is required.';
            }

            $query = $request['query'] ?? '';
            if (empty($query)) {
                return 'Error: query is required for find.';
            }

            $findRequest = [
                'find' => $query,
                'replacement' => $query, // Replace with itself = no-op, but returns match count
                'matchCase' => (bool) ($request['matchCase'] ?? false),
                'matchEntireCell' => (bool) ($request['matchEntireCell'] ?? false),
                'searchByRegex' => false,
                'includeFormulas' => false,
            ];

            $sheetName = $request['sheet'] ?? '';
            if ($sheetName !== '' && is_string($sheetName)) {
                $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);
                $findRequest['sheetId'] = $sheetId;
            } else {
                $findRequest['allSheets'] = true;
            }

            $result = $this->service->batchUpdate($spreadsheetId, [
                ['findReplace' => $findRequest],
            ]);

            $replies = $result['replies'] ?? [];
            $findResult = $replies[0]['findReplace'] ?? [];

            return json_encode([
                'query' => $query,
                'occurrencesChanged' => (int) ($findResult['occurrencesChanged'] ?? 0),
                'sheetsChanged' => (int) ($findResult['sheetsChanged'] ?? 0),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
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
            'spreadsheetId' => $schema
                ->string()
                ->description('Spreadsheet ID (from the URL).')
                ->required(),
            'query' => $schema
                ->string()
                ->description('Text to search for.')
                ->required(),
            'sheet' => $schema
                ->string()
                ->description('Sheet name to search in. Omit to search all sheets.'),
            'matchCase' => $schema
                ->boolean()
                ->description('Case-sensitive search. Default false.'),
            'matchEntireCell' => $schema
                ->boolean()
                ->description('Match entire cell content only. Default false.'),
        ];
    }
}
