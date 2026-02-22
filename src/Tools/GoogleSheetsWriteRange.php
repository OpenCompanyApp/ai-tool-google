<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsWriteRange implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Write values to a Google Sheets range. Values format: `[["Name", "Age"], ["Alice", 30]]` — each inner array is one row.
        Formulas work with user_entered input mode (default): `[["=SUM(A1:A10)"]]`.
        MD;
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
                return 'Error: range is required (e.g., "Sheet1!A1:D10").';
            }

            $values = $request['values'] ?? [];
            if (! is_array($values) || empty($values)) {
                return 'Error: values is required (2D array of rows).';
            }

            $inputOption = $this->resolveInputOption($request['input'] ?? 'user_entered');

            /** @var array<int, array<int, mixed>> $values */
            $result = $this->service->writeRange($spreadsheetId, $range, $values, $inputOption);

            return json_encode([
                'message' => 'Values written.',
                'updatedRange' => $result['updatedRange'] ?? $range,
                'updatedRows' => (int) ($result['updatedRows'] ?? 0),
                'updatedColumns' => (int) ($result['updatedColumns'] ?? 0),
                'updatedCells' => (int) ($result['updatedCells'] ?? 0),
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
                ->description('A1 notation range (e.g., "Sheet1!A1:D10").')
                ->required(),
            'values' => $schema
                ->array()
                ->description('2D array of values. Each inner array is a row (e.g., [["Name", "Age"], ["Alice", 30]]).')
                ->required(),
            'input' => $schema
                ->string()
                ->description('Input mode: "user_entered" (default, parses formulas/dates) or "raw" (literal strings).'),
        ];
    }
}
