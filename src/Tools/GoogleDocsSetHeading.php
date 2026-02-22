<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsSetHeading implements Tool
{
    /** @var array<int, string> Valid heading/paragraph styles */
    private const PARAGRAPH_STYLES = [
        'HEADING_1', 'HEADING_2', 'HEADING_3', 'HEADING_4', 'HEADING_5', 'HEADING_6',
        'TITLE', 'SUBTITLE', 'NORMAL_TEXT',
    ];

    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Set paragraph style (heading level) for a range in a Google Docs document. Valid styles: HEADING_1 through HEADING_6, TITLE, SUBTITLE, NORMAL_TEXT.';
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

            $style = $request['style'] ?? '';
            if (empty($style) || ! in_array((string) $style, self::PARAGRAPH_STYLES, true)) {
                return 'Error: style is required. Valid values: ' . implode(', ', self::PARAGRAPH_STYLES) . '.';
            }

            $requests = [
                ['updateParagraphStyle' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                    'paragraphStyle' => [
                        'namedStyleType' => (string) $style,
                    ],
                    'fields' => 'namedStyleType',
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return "Paragraph style set to {$style} for range [{$startIndex}-{$endIndex}].";
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
            'documentId' => $schema
                ->string()
                ->description('Google Docs document ID (from the URL).')
                ->required(),
            'startIndex' => $schema
                ->integer()
                ->description('Start index of the paragraph range.')
                ->required(),
            'endIndex' => $schema
                ->integer()
                ->description('End index of the paragraph range.')
                ->required(),
            'style' => $schema
                ->string()
                ->description('Paragraph style: HEADING_1, HEADING_2, HEADING_3, HEADING_4, HEADING_5, HEADING_6, TITLE, SUBTITLE, or NORMAL_TEXT.')
                ->required(),
        ];
    }
}
