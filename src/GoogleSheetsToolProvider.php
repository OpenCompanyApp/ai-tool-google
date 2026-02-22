<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsAddFilter;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsAddSheet;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsAppend;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsBatchRead;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsBatchWrite;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsClear;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsCreate;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsDeleteColumns;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsDeleteRows;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsDeleteSheet;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsDuplicateSheet;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsFind;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsGetMetadata;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsInsertColumns;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsInsertRows;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsReadRange;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsRemoveFilter;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsRenameSheet;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsSortRange;
use OpenCompany\AiToolGoogle\Tools\GoogleSheetsWriteRange;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleSheetsToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_sheets';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'spreadsheets, cells, rows, columns, data, tables',
            'description' => 'Spreadsheet data management',
            'icon' => 'ph:table',
            'logo' => 'simple-icons:googlesheets',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Sheets',
            'description' => 'Read, write, and manage spreadsheet data',
            'icon' => 'ph:table',
            'logo' => 'simple-icons:googlesheets',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/sheets.googleapis.com',
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
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_sheets',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Sheets" to authorize.'];
        }

        try {
            // Try to access a non-existent spreadsheet to verify auth
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://sheets.googleapis.com/v4/spreadsheets/__test_connection__', [
                'fields' => 'spreadsheetId',
            ]);

            // 404 = auth works, spreadsheet not found (expected)
            // 401/403 = auth failed
            if ($response->status() === 404) {
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return ['success' => true, 'message' => "Google Sheets connected{$emailInfo}."];
            }

            if ($response->successful()) {
                // Shouldn't happen with a fake ID, but handle it gracefully
                $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

                return ['success' => true, 'message' => "Google Sheets connected{$emailInfo}."];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Sheets API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
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
            'google_sheets_get_metadata' => [
                'class' => GoogleSheetsGetMetadata::class,
                'type' => 'read',
                'name' => 'Get Metadata',
                'description' => 'Get spreadsheet title and sheet list.',
                'icon' => 'ph:table',
            ],
            'google_sheets_read_range' => [
                'class' => GoogleSheetsReadRange::class,
                'type' => 'read',
                'name' => 'Read Range',
                'description' => 'Read cell values from a range.',
                'icon' => 'ph:table',
            ],
            'google_sheets_batch_read' => [
                'class' => GoogleSheetsBatchRead::class,
                'type' => 'read',
                'name' => 'Batch Read',
                'description' => 'Read multiple ranges in one call.',
                'icon' => 'ph:table',
            ],
            'google_sheets_find' => [
                'class' => GoogleSheetsFind::class,
                'type' => 'read',
                'name' => 'Find Text',
                'description' => 'Search for text in a spreadsheet.',
                'icon' => 'ph:magnifying-glass',
            ],
            'google_sheets_create' => [
                'class' => GoogleSheetsCreate::class,
                'type' => 'write',
                'name' => 'Create Spreadsheet',
                'description' => 'Create a new empty spreadsheet.',
                'icon' => 'ph:plus',
            ],
            'google_sheets_write_range' => [
                'class' => GoogleSheetsWriteRange::class,
                'type' => 'write',
                'name' => 'Write Range',
                'description' => 'Write values to a range.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_sheets_append' => [
                'class' => GoogleSheetsAppend::class,
                'type' => 'write',
                'name' => 'Append Rows',
                'description' => 'Append rows after the last data row.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_sheets_clear' => [
                'class' => GoogleSheetsClear::class,
                'type' => 'write',
                'name' => 'Clear Range',
                'description' => 'Clear all values from a range.',
                'icon' => 'ph:eraser',
            ],
            'google_sheets_batch_write' => [
                'class' => GoogleSheetsBatchWrite::class,
                'type' => 'write',
                'name' => 'Batch Write',
                'description' => 'Write to multiple ranges in one call.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_sheets_add_sheet' => [
                'class' => GoogleSheetsAddSheet::class,
                'type' => 'write',
                'name' => 'Add Sheet',
                'description' => 'Add a new sheet/tab.',
                'icon' => 'ph:plus',
            ],
            'google_sheets_delete_sheet' => [
                'class' => GoogleSheetsDeleteSheet::class,
                'type' => 'write',
                'name' => 'Delete Sheet',
                'description' => 'Delete a sheet/tab.',
                'icon' => 'ph:trash',
            ],
            'google_sheets_rename_sheet' => [
                'class' => GoogleSheetsRenameSheet::class,
                'type' => 'write',
                'name' => 'Rename Sheet',
                'description' => 'Rename a sheet/tab.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_sheets_duplicate_sheet' => [
                'class' => GoogleSheetsDuplicateSheet::class,
                'type' => 'write',
                'name' => 'Duplicate Sheet',
                'description' => 'Copy a sheet/tab within the spreadsheet.',
                'icon' => 'ph:copy',
            ],
            'google_sheets_insert_rows' => [
                'class' => GoogleSheetsInsertRows::class,
                'type' => 'write',
                'name' => 'Insert Rows',
                'description' => 'Insert blank rows.',
                'icon' => 'ph:rows',
            ],
            'google_sheets_delete_rows' => [
                'class' => GoogleSheetsDeleteRows::class,
                'type' => 'write',
                'name' => 'Delete Rows',
                'description' => 'Delete rows.',
                'icon' => 'ph:rows',
            ],
            'google_sheets_insert_columns' => [
                'class' => GoogleSheetsInsertColumns::class,
                'type' => 'write',
                'name' => 'Insert Columns',
                'description' => 'Insert blank columns.',
                'icon' => 'ph:columns',
            ],
            'google_sheets_delete_columns' => [
                'class' => GoogleSheetsDeleteColumns::class,
                'type' => 'write',
                'name' => 'Delete Columns',
                'description' => 'Delete columns.',
                'icon' => 'ph:columns',
            ],
            'google_sheets_sort_range' => [
                'class' => GoogleSheetsSortRange::class,
                'type' => 'write',
                'name' => 'Sort Range',
                'description' => 'Sort data by column.',
                'icon' => 'ph:sort-ascending',
            ],
            'google_sheets_add_filter' => [
                'class' => GoogleSheetsAddFilter::class,
                'type' => 'write',
                'name' => 'Add Filter',
                'description' => 'Apply filter dropdowns to a range.',
                'icon' => 'ph:funnel',
            ],
            'google_sheets_remove_filter' => [
                'class' => GoogleSheetsRemoveFilter::class,
                'type' => 'write',
                'name' => 'Remove Filter',
                'description' => 'Remove filter from a sheet.',
                'icon' => 'ph:funnel',
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
        $service = app(GoogleSheetsService::class);

        return new $class($service);
    }
}
