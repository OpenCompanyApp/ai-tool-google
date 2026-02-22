<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveShareFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Share a Google Drive file or folder. Provide `fileId`, `role` ("reader", "writer", "commenter"), and one of:
        - `email`: share with a specific user (e.g., "alice@example.com")
        - `domain`: share with an entire domain (e.g., "example.com")
        - `type` set to `"anyone"`: make accessible to anyone with the link (no email/domain needed)
        - `notify` (optional, default true): send email notification (only for email shares)
        MD;
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

            $role = $request['role'] ?? '';
            if (! in_array($role, ['reader', 'writer', 'commenter'], true)) {
                return 'Error: role must be "reader", "writer", or "commenter".';
            }

            $email = $request['email'] ?? '';
            $domain = $request['domain'] ?? '';
            $type = $request['type'] ?? '';

            $permissionData = ['role' => $role];

            if ($type === 'anyone') {
                $permissionData['type'] = 'anyone';
            } elseif ($email !== '') {
                $permissionData['type'] = 'user';
                $permissionData['emailAddress'] = $email;
            } elseif ($domain !== '') {
                $permissionData['type'] = 'domain';
                $permissionData['domain'] = $domain;
            } else {
                return 'Error: Provide email, domain, or type="anyone".';
            }

            $notify = ($request['notify'] ?? 'true') !== 'false';

            $result = $this->service->createPermission($fileId, $permissionData, $notify);
            $target = match (true) {
                $type === 'anyone' => 'anyone with the link',
                $email !== '' => $email,
                default => $domain,
            };

            return json_encode([
                'message' => "Shared with {$target} as {$role}.",
                'permissionId' => $result['id'] ?? '',
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
                ->description('File/folder ID to share.')
                ->required(),
            'role' => $schema
                ->string()
                ->description('Permission role: "reader", "writer", or "commenter".')
                ->required(),
            'type' => $schema
                ->string()
                ->description('Set to "anyone" to share with anyone who has the link.'),
            'email' => $schema
                ->string()
                ->description('Email address to share with a specific user.'),
            'domain' => $schema
                ->string()
                ->description('Domain to share with (e.g., "example.com").'),
            'notify' => $schema
                ->string()
                ->description('Send email notification: "true" (default) or "false".'),
        ];
    }
}
