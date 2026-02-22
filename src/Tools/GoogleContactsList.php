<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;

class GoogleContactsList implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function description(): string
    {
        return 'List all Google Contacts sorted by first name with pagination.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Contacts integration is not configured.';
            }

            $pageSize = isset($request['maxResults']) ? min((int) $request['maxResults'], 100) : 20;
            $pageToken = $request['pageToken'] ?? null;

            $result = $this->service->listContacts($pageSize, $pageToken);
            $connections = $result['connections'] ?? [];

            if (empty($connections)) {
                return 'No contacts found.';
            }

            $contacts = array_map(
                fn (array $person) => GoogleContactsService::formatContact($person),
                $connections
            );

            $output = [
                'count' => count($contacts),
                'totalPeople' => (int) ($result['totalPeople'] ?? 0),
                'contacts' => $contacts,
            ];

            if (isset($result['nextPageToken'])) {
                $output['nextPageToken'] = $result['nextPageToken'];
            }

            return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
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
            'maxResults' => $schema
                ->integer()
                ->description('Max results to return (default: 20, max: 100).'),
            'pageToken' => $schema
                ->string()
                ->description('Pagination token from previous response.'),
        ];
    }
}
