<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsDeleteColumns implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Delete columns from a Google Sheets sheet/tab. Uses 0-based indexing.';
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

            $sheetName = $request['sheet'] ?? '';
            if (empty($sheetName)) {
                return 'Error: sheet (sheet name) is required.';
            }

            $startIndex = (int) ($request['startIndex'] ?? 0);
            $count = max(1, (int) ($request['count'] ?? 1));

            $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

            $this->service->batchUpdate($spreadsheetId, [
                ['deleteDimension' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => $startIndex,
                        'endIndex' => $startIndex + $count,
                    ],
                ]],
            ]);

            return "{$count} column(s) deleted at index {$startIndex} in '{$sheetName}'.";
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
            'sheet' => $schema
                ->string()
                ->description('Sheet/tab name.')
                ->required(),
            'startIndex' => $schema
                ->integer()
                ->description('0-based column index to start deleting from.')
                ->required(),
            'count' => $schema
                ->integer()
                ->description('Number of columns to delete (default 1).'),
        ];
    }
}
