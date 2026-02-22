<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsAddSheet implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Add a new sheet/tab to a Google Spreadsheet.';
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

            $title = $request['title'] ?? '';
            if (empty($title)) {
                return 'Error: title is required.';
            }

            $result = $this->service->batchUpdate($spreadsheetId, [
                ['addSheet' => ['properties' => ['title' => $title]]],
            ]);

            $newSheet = $result['replies'][0]['addSheet']['properties'] ?? [];

            return json_encode([
                'message' => "Sheet '{$title}' added.",
                'sheetId' => (int) ($newSheet['sheetId'] ?? 0),
                'title' => $newSheet['title'] ?? $title,
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
            'title' => $schema
                ->string()
                ->description('Name for the new sheet/tab.')
                ->required(),
        ];
    }
}
