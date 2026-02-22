<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveStar implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return 'Mark a file as starred/favorite in Google Drive.';
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

            $this->service->updateFile($fileId, ['starred' => true]);

            return 'File starred.';
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
                ->description('File ID to star.')
                ->required(),
        ];
    }
}
