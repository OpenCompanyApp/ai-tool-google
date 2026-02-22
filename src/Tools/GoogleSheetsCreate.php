<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsCreate implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Create a new empty Google Spreadsheet with a given title. Returns the new spreadsheet ID and URL.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Sheets integration is not configured.';
            }

            $title = $request['title'] ?? '';
            if (empty($title)) {
                return 'Error: title is required for create.';
            }

            $result = $this->service->createSpreadsheet($title);

            return json_encode([
                'message' => "Spreadsheet '{$title}' created.",
                'spreadsheetId' => $result['spreadsheetId'] ?? '',
                'url' => $result['spreadsheetUrl'] ?? '',
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
            'title' => $schema
                ->string()
                ->description('Title for the new spreadsheet.')
                ->required(),
        ];
    }
}
