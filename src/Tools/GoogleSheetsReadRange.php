<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsReadRange implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Read cell values from a Google Sheets range using A1 notation.
        A1 notation examples: `Sheet1!A1:D10` (range), `Sheet1!A:A` (whole column), `Sheet1` (entire sheet). Sheet names with spaces need quotes: `'My Sheet'!A1:B2`.
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

            $renderOption = $this->resolveRenderOption($request['render'] ?? 'formatted');

            $result = $this->service->readRange($spreadsheetId, $range, $renderOption);
            $values = $result['values'] ?? [];

            if (empty($values)) {
                return json_encode([
                    'range' => $result['range'] ?? $range,
                    'rows' => 0,
                    'values' => [],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
            }

            return json_encode([
                'range' => $result['range'] ?? $range,
                'rows' => count($values),
                'columns' => max(array_map('count', $values)),
                'values' => $values,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function resolveRenderOption(string $render): string
    {
        return match ($render) {
            'unformatted' => 'UNFORMATTED_VALUE',
            'formula' => 'FORMULA',
            default => 'FORMATTED_VALUE',
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
                ->description('A1 notation range (e.g., "Sheet1!A1:D10", "Sheet1!A:A", "Sheet1").')
                ->required(),
            'render' => $schema
                ->string()
                ->description('Value rendering: "formatted" (default, as displayed), "unformatted" (raw numbers), or "formula" (shows formulas).'),
        ];
    }
}
