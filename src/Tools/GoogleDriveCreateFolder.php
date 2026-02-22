<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveCreateFolder implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return 'Create a folder in Google Drive.';
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

            $metadata = [
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
            ];

            $parentId = $request['parentId'] ?? '';
            if ($parentId !== '') {
                $metadata['parents'] = [$parentId];
            }

            $result = $this->service->createFile($metadata);

            return json_encode([
                'message' => "Folder '{$name}' created.",
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
                ->description('Folder name.')
                ->required(),
            'parentId' => $schema
                ->string()
                ->description('Parent folder ID (defaults to root).'),
        ];
    }
}
