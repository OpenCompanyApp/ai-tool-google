<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;

class GoogleSheetsSortRange implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function description(): string
    {
        return 'Sort data by column(s) in a Google Sheets range.';
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
                return 'Error: range is required (A1 notation, e.g., "Sheet1!A1:D10").';
            }

            $sortColumn = $request['sortColumn'] ?? null;
            if ($sortColumn === null) {
                return 'Error: sortColumn is required (0-based column index within the range).';
            }

            $ascending = $request['ascending'] ?? true;

            // Parse the A1 range to get sheet name and grid coordinates
            $gridRange = $this->parseA1ToGridRange($spreadsheetId, $range);

            $this->service->batchUpdate($spreadsheetId, [
                ['sortRange' => [
                    'range' => $gridRange,
                    'sortSpecs' => [[
                        'dimensionIndex' => (int) $sortColumn,
                        'sortOrder' => $ascending ? 'ASCENDING' : 'DESCENDING',
                    ]],
                ]],
            ]);

            $direction = $ascending ? 'ascending' : 'descending';

            return "Range '{$range}' sorted by column {$sortColumn} ({$direction}).";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Parse an A1 notation range into a GridRange for batchUpdate requests.
     *
     * @return array<string, mixed>
     */
    private function parseA1ToGridRange(string $spreadsheetId, string $range): array
    {
        $sheetName = '';
        $cellRange = $range;

        if (str_contains($range, '!')) {
            $parts = explode('!', $range, 2);
            $sheetName = trim($parts[0], "'");
            $cellRange = $parts[1];
        }

        if ($sheetName === '') {
            throw new \RuntimeException('Range must include sheet name (e.g., "Sheet1!A1:D10").');
        }

        $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

        $gridRange = ['sheetId' => $sheetId];

        $rangeParts = explode(':', $cellRange, 2);
        $start = $this->parseCellReference($rangeParts[0]);

        $gridRange['startColumnIndex'] = $start['col'];
        if ($start['row'] !== null) {
            $gridRange['startRowIndex'] = $start['row'];
        }

        if (isset($rangeParts[1])) {
            $end = $this->parseCellReference($rangeParts[1]);
            $gridRange['endColumnIndex'] = $end['col'] + 1;
            if ($end['row'] !== null) {
                $gridRange['endRowIndex'] = $end['row'] + 1;
            }
        } else {
            $gridRange['endColumnIndex'] = $start['col'] + 1;
            if ($start['row'] !== null) {
                $gridRange['endRowIndex'] = $start['row'] + 1;
            }
        }

        return $gridRange;
    }

    /**
     * @return array{col: int, row: int|null}
     */
    private function parseCellReference(string $ref): array
    {
        $ref = strtoupper(trim($ref));

        preg_match('/^([A-Z]+)(\d*)$/', $ref, $matches);
        if (empty($matches)) {
            throw new \RuntimeException("Invalid cell reference: '{$ref}'.");
        }

        $colLetters = $matches[1];
        $rowStr = $matches[2];

        $colIndex = 0;
        $len = strlen($colLetters);
        for ($i = 0; $i < $len; $i++) {
            $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - ord('A') + 1);
        }
        $colIndex--;

        $rowIndex = $rowStr !== '' ? ((int) $rowStr - 1) : null;

        return ['col' => $colIndex, 'row' => $rowIndex];
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
                ->description('A1 notation range to sort (e.g., "Sheet1!A1:D10").')
                ->required(),
            'sortColumn' => $schema
                ->integer()
                ->description('0-based column index within the range to sort by.')
                ->required(),
            'ascending' => $schema
                ->boolean()
                ->description('Sort ascending (true, default) or descending (false).'),
        ];
    }
}
