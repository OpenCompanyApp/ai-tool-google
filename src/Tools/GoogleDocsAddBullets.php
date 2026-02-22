<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsAddBullets implements Tool
{
    /** @var array<int, string> Valid bullet presets */
    private const BULLET_PRESETS = [
        'BULLET_DISC_CIRCLE_SQUARE',
        'BULLET_DIAMONDX_ARROW3D_SQUARE',
        'BULLET_CHECKBOX',
        'BULLET_ARROW_DIAMOND_DISC',
        'BULLET_STAR_CIRCLE_SQUARE',
        'BULLET_ARROW3D_CIRCLE_SQUARE',
        'BULLET_LEFTTRIANGLE_DIAMOND_DISC',
        'BULLET_DIAMONDX_HOLLOWDIAMOND_SQUARE',
        'NUMBERED_DECIMAL_ALPHA_ROMAN',
        'NUMBERED_DECIMAL_ALPHA_ROMAN_PARENS',
        'NUMBERED_DECIMAL_NESTED',
        'NUMBERED_UPPERALPHA_ALPHA_ROMAN',
        'NUMBERED_UPPERROMAN_UPPERALPHA_DECIMAL',
        'NUMBERED_ZERODECIMAL_ALPHA_ROMAN',
    ];

    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Add bullet or numbered list formatting to a range in a Google Docs document. Default preset is BULLET_DISC_CIRCLE_SQUARE. Use NUMBERED_DECIMAL_ALPHA_ROMAN for numbered lists.';
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

            $preset = (string) ($request['preset'] ?? 'BULLET_DISC_CIRCLE_SQUARE');
            if (! in_array($preset, self::BULLET_PRESETS, true)) {
                return 'Error: Invalid preset. Valid values: ' . implode(', ', self::BULLET_PRESETS) . '.';
            }

            $requests = [
                ['createParagraphBullets' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                    'bulletPreset' => $preset,
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return "Bullet list ({$preset}) applied to range [{$startIndex}-{$endIndex}].";
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
                ->description('Start index of the range.')
                ->required(),
            'endIndex' => $schema
                ->integer()
                ->description('End index of the range.')
                ->required(),
            'preset' => $schema
                ->string()
                ->description('Bullet preset. Default BULLET_DISC_CIRCLE_SQUARE. Use NUMBERED_DECIMAL_ALPHA_ROMAN for numbered lists.'),
        ];
    }
}
