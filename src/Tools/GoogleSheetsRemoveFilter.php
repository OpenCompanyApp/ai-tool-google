<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsRemoveFilter implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Remove the filter from a Google Sheets sheet/tab.';
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

            $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

            $this->service->batchUpdate($spreadsheetId, [
                ['clearBasicFilter' => ['sheetId' => $sheetId]],
            ]);

            return "Filter removed from sheet '{$sheetName}'.";
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
                ->description('Sheet/tab name to remove the filter from.')
                ->required(),
        ];
    }
}
