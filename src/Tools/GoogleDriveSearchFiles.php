<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;

class GoogleDriveSearchFiles implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Search for files in Google Drive using Drive query syntax (default: 20 results, max: 100). Trashed files are excluded by default.

        Drive query syntax examples:
        - By name: `name contains 'budget'` or `name = 'Q1 Report'`
        - By type: `mimeType = 'application/vnd.google-apps.spreadsheet'` (also: document, presentation, folder)
        - In folder: `'FOLDER_ID' in parents`
        - Recent: `modifiedTime > '2026-01-01'`
        - Shared with me: `sharedWithMe = true`
        - Starred: `starred = true`
        - By owner: `'user@example.com' in owners`
        - Combine: `name contains 'report' and mimeType = 'application/vnd.google-apps.spreadsheet'`
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Drive integration is not configured.';
            }

            $params = [];

            $query = $request['query'] ?? '';
            if ($query !== '') {
                // Auto-exclude trashed files unless the query already mentions trashed
                if (stripos($query, 'trashed') === false) {
                    $query .= ' and trashed = false';
                }
                $params['q'] = $query;
            } else {
                $params['q'] = 'trashed = false';
            }

            $pageSize = isset($request['maxResults']) ? min((int) $request['maxResults'], 100) : 20;
            $params['pageSize'] = (string) $pageSize;

            if (isset($request['pageToken'])) {
                $params['pageToken'] = $request['pageToken'];
            }

            if (isset($request['orderBy'])) {
                $params['orderBy'] = $request['orderBy'];
            }

            $result = $this->service->listFiles($params);
            $files = $result['files'] ?? [];

            if (empty($files)) {
                return 'No files found.';
            }

            $formatted = array_map(fn (array $file) => $this->formatFile($file), $files);

            $output = ['count' => count($formatted), 'files' => $formatted];
            if (isset($result['nextPageToken'])) {
                $output['nextPageToken'] = $result['nextPageToken'];
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
            'query' => $schema
                ->string()
                ->description('Drive search query (e.g., "name contains \'report\' and mimeType = \'application/vnd.google-apps.folder\'").'),
            'maxResults' => $schema
                ->integer()
                ->description('Max results per page (default: 20, max: 100).'),
            'pageToken' => $schema
                ->string()
                ->description('Pagination token from previous response.'),
            'orderBy' => $schema
                ->string()
                ->description('Sort order (e.g., "modifiedTime desc", "name").'),
        ];
    }
}
