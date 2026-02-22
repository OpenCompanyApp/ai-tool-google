<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsAppend implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Append rows after the last data row in a Google Spreadsheet. Auto-detects the table boundary. Provide the range (e.g., "Sheet1" or "Sheet1!A:D") and a 2D array of rows to append.';
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

            $range = $request['range'] ?? '';
            if (empty($range)) {
                return 'Error: range is required (e.g., "Sheet1" or "Sheet1!A:D").';
            }

            $values = $request['values'] ?? [];
            if (! is_array($values) || empty($values)) {
                return 'Error: values is required (2D array of rows to append).';
            }

            $inputOption = $this->resolveInputOption($request['input'] ?? 'user_entered');

            /** @var array<int, array<int, mixed>> $values */
            $result = $this->service->appendRows($spreadsheetId, $range, $values, $inputOption);

            $updates = $result['updates'] ?? [];

            return json_encode([
                'message' => 'Rows appended.',
                'updatedRange' => $updates['updatedRange'] ?? '',
                'updatedRows' => (int) ($updates['updatedRows'] ?? 0),
                'updatedCells' => (int) ($updates['updatedCells'] ?? 0),
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
            'range' => $schema
                ->string()
                ->description('A1 notation range (e.g., "Sheet1" or "Sheet1!A:D").')
                ->required(),
            'values' => $schema
                ->array()
                ->description('2D array of rows to append (e.g., [["Alice", 30], ["Bob", 25]]).')
                ->required(),
            'input' => $schema
                ->string()
                ->description('Input mode: "user_entered" (default, parses formulas/dates) or "raw" (literal strings).'),
        ];
    }
}
