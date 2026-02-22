<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveCopy;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveCreateFile;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveCreateFolder;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveDelete;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveMove;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveRename;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveGetFile;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveListPermissions;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveSearchFiles;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveShareFile;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveUnshareFile;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveStar;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveTrash;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveUnstar;
use OpenCompany\AiToolGoogle\Tools\GoogleDriveUntrash;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleDriveToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_drive';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'files, folders, documents, drive, sharing',
            'description' => 'File storage and management',
            'icon' => 'ph:google-drive-logo',
            'logo' => 'simple-icons:googledrive',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Drive',
            'description' => 'File search, management, and sharing',
            'icon' => 'ph:google-drive-logo',
            'logo' => 'simple-icons:googledrive',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/drive.googleapis.com',
        ];
    }

    public function configSchema(): array
    {
        return [
            [
                'key' => 'client_id',
                'type' => 'text',
                'label' => 'Client ID',
                'placeholder' => 'Your Google Cloud OAuth Client ID',
                'hint' => 'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> &rarr; Credentials &rarr; OAuth 2.0 Client IDs. Shared across all Google integrations &mdash; only needs to be entered once.',
                'required' => true,
            ],
            [
                'key' => 'client_secret',
                'type' => 'secret',
                'label' => 'Client Secret',
                'placeholder' => 'Your Google Cloud OAuth Client Secret',
                'required' => true,
            ],
            [
                'key' => 'access_token',
                'type' => 'oauth_connect',
                'label' => 'Google Account',
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_drive',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Drive" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/drive/v3/about', [
                'fields' => 'user(displayName,emailAddress),storageQuota(usage,limit)',
            ]);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $email = $data['user']['emailAddress'] ?? $connectedEmail ?? 'unknown';
                $usage = (int) ($data['storageQuota']['usage'] ?? 0);
                $limit = (int) ($data['storageQuota']['limit'] ?? 0);

                $usageFormatted = GoogleDriveService::formatSize($usage);
                $message = "Connected as {$email}. {$usageFormatted} used.";
                if ($limit > 0) {
                    $limitFormatted = GoogleDriveService::formatSize($limit);
                    $message = "Connected as {$email}. {$usageFormatted} of {$limitFormatted} used.";
                }

                return ['success' => true, 'message' => $message];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Drive API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, string|array<int, string>> */
    public function validationRules(): array
    {
        return [
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'access_token' => 'nullable|string',
        ];
    }

    public function tools(): array
    {
        return [
            'google_drive_search_files' => [
                'class' => GoogleDriveSearchFiles::class,
                'type' => 'read',
                'name' => 'Search Drive',
                'description' => 'Search for files in Drive.',
                'icon' => 'ph:magnifying-glass',
            ],
            'google_drive_get_file' => [
                'class' => GoogleDriveGetFile::class,
                'type' => 'read',
                'name' => 'Get File',
                'description' => 'Get file metadata and content.',
                'icon' => 'ph:file',
            ],
            'google_drive_create_folder' => [
                'class' => GoogleDriveCreateFolder::class,
                'type' => 'write',
                'name' => 'Create Folder',
                'description' => 'Create a folder in Drive.',
                'icon' => 'ph:folder-plus',
            ],
            'google_drive_create_file' => [
                'class' => GoogleDriveCreateFile::class,
                'type' => 'write',
                'name' => 'Create File',
                'description' => 'Create an empty Doc, Sheet, or Presentation.',
                'icon' => 'ph:file-plus',
            ],
            'google_drive_rename' => [
                'class' => GoogleDriveRename::class,
                'type' => 'write',
                'name' => 'Rename',
                'description' => 'Rename a file or folder.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_drive_move' => [
                'class' => GoogleDriveMove::class,
                'type' => 'write',
                'name' => 'Move',
                'description' => 'Move a file to a different folder.',
                'icon' => 'ph:arrows-left-right',
            ],
            'google_drive_copy' => [
                'class' => GoogleDriveCopy::class,
                'type' => 'write',
                'name' => 'Copy',
                'description' => 'Duplicate a file.',
                'icon' => 'ph:copy',
            ],
            'google_drive_trash' => [
                'class' => GoogleDriveTrash::class,
                'type' => 'write',
                'name' => 'Trash',
                'description' => 'Move a file to trash.',
                'icon' => 'ph:trash',
            ],
            'google_drive_untrash' => [
                'class' => GoogleDriveUntrash::class,
                'type' => 'write',
                'name' => 'Untrash',
                'description' => 'Restore a file from trash.',
                'icon' => 'ph:arrow-counter-clockwise',
            ],
            'google_drive_star' => [
                'class' => GoogleDriveStar::class,
                'type' => 'write',
                'name' => 'Star',
                'description' => 'Mark a file as starred.',
                'icon' => 'ph:star',
            ],
            'google_drive_unstar' => [
                'class' => GoogleDriveUnstar::class,
                'type' => 'write',
                'name' => 'Unstar',
                'description' => 'Remove star from a file.',
                'icon' => 'ph:star',
            ],
            'google_drive_delete' => [
                'class' => GoogleDriveDelete::class,
                'type' => 'write',
                'name' => 'Delete',
                'description' => 'Permanently delete a file.',
                'icon' => 'ph:trash',
            ],
            'google_drive_share_file' => [
                'class' => GoogleDriveShareFile::class,
                'type' => 'write',
                'name' => 'Share File',
                'description' => 'Share a file or folder.',
                'icon' => 'ph:share-network',
            ],
            'google_drive_unshare_file' => [
                'class' => GoogleDriveUnshareFile::class,
                'type' => 'write',
                'name' => 'Unshare File',
                'description' => 'Remove a sharing permission.',
                'icon' => 'ph:share-network',
            ],
            'google_drive_list_permissions' => [
                'class' => GoogleDriveListPermissions::class,
                'type' => 'read',
                'name' => 'List Permissions',
                'description' => 'List sharing permissions on a file.',
                'icon' => 'ph:users',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function createTool(string $class, array $context = []): Tool
    {
        $service = app(GoogleDriveService::class);

        return new $class($service);
    }
}
