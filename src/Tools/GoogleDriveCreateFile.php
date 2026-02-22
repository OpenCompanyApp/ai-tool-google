<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveCreateFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return 'Create an empty Google Doc, Sheet, or Presentation in Google Drive.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Drive integration is not configured.';
            }

            $name = $request['name'] ?? '';
            if (empty($name)) {
                return 'Error: name is required.';
            }

            $type = $request['type'] ?? '';
            $mimeType = GoogleDriveService::resolveMimeType($type);
            if ($mimeType === null) {
                return 'Error: type must be "document", "spreadsheet", or "presentation".';
            }

            $metadata = [
                'name' => $name,
                'mimeType' => $mimeType,
            ];

            $parentId = $request['parentId'] ?? '';
            if ($parentId !== '') {
                $metadata['parents'] = [$parentId];
            }

            $result = $this->service->createFile($metadata);
            $typeLabel = ucfirst($type);

            return json_encode([
                'message' => "{$typeLabel} '{$name}' created.",
                'id' => $result['id'] ?? '',
                'webViewLink' => $result['webViewLink'] ?? '',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
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
            'name' => $schema
                ->string()
                ->description('File name.')
                ->required(),
            'type' => $schema
                ->string()
                ->description('File type: "document", "spreadsheet", or "presentation".')
                ->required(),
            'parentId' => $schema
                ->string()
                ->description('Parent folder ID (defaults to root).'),
        ];
    }
}
