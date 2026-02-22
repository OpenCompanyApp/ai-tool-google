<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsFormatText implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Apply formatting to a text range in a Google Docs document. Supports bold, italic, underline, strikethrough, fontSize (points), fontFamily, foregroundColor (hex like "#FF0000"), and link (URL).';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Docs integration is not configured.';
            }

            $documentId = $request['documentId'] ?? '';
            if (empty($documentId)) {
                return 'Error: documentId is required.';
            }

            $startIndex = $request['startIndex'] ?? null;
            $endIndex = $request['endIndex'] ?? null;
            if ($startIndex === null || $endIndex === null) {
                return 'Error: startIndex and endIndex are required.';
            }

            // Build text style and fields mask
            $textStyle = [];
            $fields = [];

            if (isset($request['bold'])) {
                $textStyle['bold'] = (bool) $request['bold'];
                $fields[] = 'bold';
            }
            if (isset($request['italic'])) {
                $textStyle['italic'] = (bool) $request['italic'];
                $fields[] = 'italic';
            }
            if (isset($request['underline'])) {
                $textStyle['underline'] = (bool) $request['underline'];
                $fields[] = 'underline';
            }
            if (isset($request['strikethrough'])) {
                $textStyle['strikethrough'] = (bool) $request['strikethrough'];
                $fields[] = 'strikethrough';
            }
            if (isset($request['fontSize'])) {
                $textStyle['fontSize'] = [
                    'magnitude' => (float) $request['fontSize'],
                    'unit' => 'PT',
                ];
                $fields[] = 'fontSize';
            }
            if (isset($request['fontFamily'])) {
                $textStyle['weightedFontFamily'] = [
                    'fontFamily' => (string) $request['fontFamily'],
                ];
                $fields[] = 'weightedFontFamily';
            }
            if (isset($request['foregroundColor'])) {
                $color = $this->parseHexColor((string) $request['foregroundColor']);
                if ($color !== null) {
                    $textStyle['foregroundColor'] = ['color' => ['rgbColor' => $color]];
                    $fields[] = 'foregroundColor';
                }
            }
            if (isset($request['link'])) {
                $textStyle['link'] = ['url' => (string) $request['link']];
                $fields[] = 'link';
            }

            if (empty($fields)) {
                return 'Error: At least one formatting option is required (bold, italic, underline, strikethrough, fontSize, fontFamily, foregroundColor, link).';
            }

            $requests = [
                ['updateTextStyle' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                    'textStyle' => $textStyle,
                    'fields' => implode(',', $fields),
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return 'Formatting applied (' . implode(', ', $fields) . ") to range [{$startIndex}-{$endIndex}].";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Parse a hex color string to RGB color object.
     *
     * @return array{red: float, green: float, blue: float}|null
     */
    private function parseHexColor(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return null;
        }

        return [
            'red' => hexdec(substr($hex, 0, 2)) / 255.0,
            'green' => hexdec(substr($hex, 2, 2)) / 255.0,
            'blue' => hexdec(substr($hex, 4, 2)) / 255.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'documentId' => $schema
                ->string()
                ->description('Google Docs document ID (from the URL).')
                ->required(),
            'startIndex' => $schema
                ->integer()
                ->description('Start index of the text range to format.')
                ->required(),
            'endIndex' => $schema
                ->integer()
                ->description('End index of the text range to format.')
                ->required(),
            'bold' => $schema
                ->boolean()
                ->description('Apply bold formatting.'),
            'italic' => $schema
                ->boolean()
                ->description('Apply italic formatting.'),
            'underline' => $schema
                ->boolean()
                ->description('Apply underline formatting.'),
            'strikethrough' => $schema
                ->boolean()
                ->description('Apply strikethrough formatting.'),
            'fontSize' => $schema
                ->number()
                ->description('Font size in points (e.g., 12, 14, 18).'),
            'fontFamily' => $schema
                ->string()
                ->description('Font name (e.g., "Arial", "Times New Roman").'),
            'foregroundColor' => $schema
                ->string()
                ->description('Hex color (e.g., "#FF0000" for red).'),
            'link' => $schema
                ->string()
                ->description('URL to link the text to.'),
        ];
    }
}
