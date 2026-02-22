<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsBatchWrite implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Write to multiple ranges in a Google Spreadsheet in one call. Provide an array of {range, values} objects to update several areas at once.';
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

            $data = $request['data'] ?? [];
            if (! is_array($data) || empty($data)) {
                return 'Error: data is required (array of {range, values} objects).';
            }

            $inputOption = $this->resolveInputOption($request['input'] ?? 'user_entered');

            /** @var array<int, array{range: string, values: array<int, array<int, mixed>>}> $data */
            $result = $this->service->batchUpdateValues($spreadsheetId, $data, $inputOption);

            return json_encode([
                'message' => 'Batch write complete.',
                'totalUpdatedRows' => (int) ($result['totalUpdatedRows'] ?? 0),
                'totalUpdatedColumns' => (int) ($result['totalUpdatedColumns'] ?? 0),
                'totalUpdatedCells' => (int) ($result['totalUpdatedCells'] ?? 0),
                'totalUpdatedSheets' => (int) ($result['totalUpdatedSheets'] ?? 0),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function resolveInputOption(string $input): string
    {
        return match ($input) {
            'raw' => 'RAW',
            default => 'USER_ENTERED',
        };
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
            'data' => $schema
                ->array()
                ->description('Array of {range, values} objects (e.g., [{"range": "Sheet1!A1:B2", "values": [["a", "b"]]}]).')
                ->required(),
            'input' => $schema
                ->string()
                ->description('Input mode: "user_entered" (default, parses formulas/dates) or "raw" (literal strings).'),
        ];
    }
}
