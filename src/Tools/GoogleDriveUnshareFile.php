<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveUnshareFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return 'Remove a permission from a Google Drive file or folder. Use google_drive_list_permissions first to find the permission ID.';
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

            $permissionId = $request['permissionId'] ?? '';
            if (empty($permissionId)) {
                return 'Error: permissionId is required. Use google_drive_list_permissions to find it.';
            }

            $this->service->deletePermission($fileId, $permissionId);

            return 'Permission removed.';
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
                ->description('File/folder ID to remove permission from.')
                ->required(),
            'permissionId' => $schema
                ->string()
                ->description('Permission ID to remove.')
                ->required(),
        ];
    }
}
