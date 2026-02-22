<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;

class GoogleDocsInsertImage implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function description(): string
    {
        return 'Insert an image from a URL into a Google Docs document. Supports PNG, JPEG, and GIF. Optionally specify width and height in points.';
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

            $imageUrl = $request['imageUrl'] ?? '';
            if (empty($imageUrl)) {
                return 'Error: imageUrl is required.';
            }

            $index = $request['index'] ?? -1;
            $atEnd = $index === -1;

            $insertImage = [
                'uri' => (string) $imageUrl,
            ];

            if ($atEnd) {
                $insertImage['endOfSegmentLocation'] = ['segmentId' => ''];
            } else {
                $insertImage['location'] = ['index' => (int) $index];
            }

            // Optional size
            $width = $request['width'] ?? null;
            $height = $request['height'] ?? null;
            if ($width !== null || $height !== null) {
                $objectSize = [];
                if ($width !== null) {
                    $objectSize['width'] = ['magnitude' => (float) $width, 'unit' => 'PT'];
                }
                if ($height !== null) {
                    $objectSize['height'] = ['magnitude' => (float) $height, 'unit' => 'PT'];
                }
                $insertImage['objectSize'] = $objectSize;
            }

            $requests = [['insertInlineImage' => $insertImage]];

            $this->service->batchUpdate((string) $documentId, $requests);

            $location = $atEnd ? 'end of document' : "index {$index}";

            return "Image inserted at {$location}.";
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
            'imageUrl' => $schema
                ->string()
                ->description('Image URL (PNG/JPEG/GIF).')
                ->required(),
            'index' => $schema
                ->integer()
                ->description('Insert position (1-based). Omit or -1 for end of document.'),
            'width' => $schema
                ->number()
                ->description('Width in points (optional).'),
            'height' => $schema
                ->number()
                ->description('Height in points (optional).'),
        ];
    }
}
