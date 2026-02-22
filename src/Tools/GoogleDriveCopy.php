<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveCopy implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return 'Duplicate a file in Google Drive.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Drive integration is not configured.';
            }

            $fileId = $request['fileId'] ?? '';
            if (empty($fileId)) {
                return 'Error: fileId is required.';
            }

            $metadata = [];

            $name = $request['name'] ?? '';
            if ($name !== '') {
                $metadata['name'] = $name;
            }

            $parentId = $request['parentId'] ?? '';
            if ($parentId !== '') {
                $metadata['parents'] = [$parentId];
            }

            $result = $this->service->copyFile($fileId, $metadata);
            $newName = $result['name'] ?? $name ?: 'copy';

            return json_encode([
                'message' => "File copied as '{$newName}'.",
                'id' => $result['id'] ?? '',
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
            'fileId' => $schema
                ->string()
                ->description('File ID to copy.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('Name for the copy (optional).'),
            'parentId' => $schema
                ->string()
                ->description('Target folder ID for the copy (optional).'),
        ];
    }
}
