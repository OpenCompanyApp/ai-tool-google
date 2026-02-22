<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveGetFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return 'Get file metadata by ID from Google Drive. For Google Docs/Sheets/Slides, use `export_as` to get content as text, csv, or markdown.';
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

            $file = $this->service->getFile($fileId);
            $output = $this->formatFile($file);

            // Handle export for Google Workspace files
            $exportAs = $request['export_as'] ?? '';
            if ($exportAs !== '') {
                $mimeType = $file['mimeType'] ?? '';

                if (! GoogleDriveService::isGoogleWorkspaceType($mimeType)) {
                    $output['export_error'] = 'Export is only available for Google Docs, Sheets, and Slides.';
                } else {
                    $exportMimeType = GoogleDriveService::getExportMimeType($mimeType, $exportAs);
                    if ($exportMimeType === null) {
                        $output['export_error'] = "Format '{$exportAs}' is not supported for this file type.";
                    } else {
                        $content = $this->service->exportFile($fileId, $exportMimeType);

                        // For markdown export of Docs, strip HTML tags
                        if ($exportAs === 'markdown' && $exportMimeType === 'text/html') {
                            $content = strip_tags($content);
                        }

                        $output['content'] = $content;
                        $output['export_format'] = $exportAs;
                    }
                }
            }

            return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * Format a file array for output.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function formatFile(array $file): array
    {
        $formatted = [
            'id' => $file['id'] ?? '',
            'name' => $file['name'] ?? '',
            'mimeType' => $file['mimeType'] ?? '',
            'createdTime' => $file['createdTime'] ?? '',
            'modifiedTime' => $file['modifiedTime'] ?? '',
            'webViewLink' => $file['webViewLink'] ?? '',
        ];

        if (isset($file['size'])) {
            $formatted['size'] = GoogleDriveService::formatSize($file['size']);
        }

        if (isset($file['shared']) && $file['shared']) {
            $formatted['shared'] = true;
        }

        if (isset($file['starred']) && $file['starred']) {
            $formatted['starred'] = true;
        }

        if (isset($file['parents'])) {
            $formatted['parents'] = $file['parents'];
        }

        if (isset($file['owners']) && is_array($file['owners'])) {
            $formatted['owner'] = $file['owners'][0]['emailAddress'] ?? $file['owners'][0]['displayName'] ?? '';
        }

        return array_filter($formatted, fn ($v) => $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'fileId' => $schema
                ->string()
                ->description('File ID to retrieve.')
                ->required(),
            'export_as' => $schema
                ->string()
                ->description('Export format for Google Docs/Sheets/Slides: "text", "csv", or "markdown".'),
        ];
    }
}