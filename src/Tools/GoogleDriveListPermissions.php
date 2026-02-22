<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveListPermissions implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return 'List all permissions (sharing settings) on a Google Drive file or folder.';
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

            $result = $this->service->listPermissions($fileId);
            $permissions = $result['permissions'] ?? [];

            if (empty($permissions)) {
                return 'No permissions found.';
            }

            $formatted = array_map(fn (array $perm) => array_filter([
                'id' => $perm['id'] ?? '',
                'type' => $perm['type'] ?? '',
                'role' => $perm['role'] ?? '',
                'email' => $perm['emailAddress'] ?? '',
                'displayName' => $perm['displayName'] ?? '',
                'domain' => $perm['domain'] ?? '',
            ], fn ($v) => $v !== ''), $permissions);

            return json_encode([
                'count' => count($formatted),
                'permissions' => $formatted,
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
                ->description('File/folder ID to list permissions for.')
                ->required(),
        ];
    }
}