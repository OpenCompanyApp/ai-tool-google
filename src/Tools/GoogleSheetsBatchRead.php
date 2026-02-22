<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsBatchRead implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Read multiple ranges from a Google Spreadsheet in one call. Provide an array of A1 notation ranges (e.g., ["Sheet1!A1:B5", "Sheet2!C1:D10"]). Returns results keyed by range.';
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

            $ranges = $request['ranges'] ?? [];
            if (! is_array($ranges) || empty($ranges)) {
                return 'Error: ranges is required (array of A1 notation strings).';
            }

            $renderOption = $this->resolveRenderOption($request['render'] ?? 'formatted');

            /** @var array<int, string> $ranges */
            $result = $this->service->batchGetRanges($spreadsheetId, $ranges, $renderOption);
            $valueRanges = $result['valueRanges'] ?? [];

            $output = [];
            foreach ($valueRanges as $vr) {
                $range = $vr['range'] ?? '';
                $values = $vr['values'] ?? [];
                $output[] = [
                    'range' => $range,
                    'rows' => count($values),
                    'values' => $values,
                ];
            }

            return json_encode([
                'count' => count($output),
                'results' => $output,
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
            'ranges' => $schema
                ->array()
                ->description('Array of A1 notation ranges (e.g., ["Sheet1!A1:B5", "Sheet2!C1:D10"]).')
                ->required(),
            'render' => $schema
                ->string()
                ->description('Value rendering: "formatted" (default, as displayed), "unformatted" (raw numbers), or "formula" (shows formulas).'),
        ];
    }
}
