<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;

class GoogleContactsSearchContacts implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function description(): string
    {
        return 'Fuzzy search Google Contacts by name, email, or phone. Matches partial strings (e.g., "john", "acme.com", "555"). Use this to look up contacts before sending emails with gmail_send.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Contacts integration is not configured.';
            }

            $query = $request['query'] ?? '';
            if (empty($query)) {
                return 'Error: query is required for search.';
            }

            $pageSize = isset($request['maxResults']) ? min((int) $request['maxResults'], 30) : 10;

            $result = $this->service->searchContacts($query, $pageSize);
            $results = $result['results'] ?? [];

            if (empty($results)) {
                return 'No contacts found.';
            }

            $contacts = [];
            foreach ($results as $entry) {
                $person = $entry['person'] ?? [];
                if (! empty($person)) {
                    $contacts[] = GoogleContactsService::formatContact($person);
                }
            }

            return json_encode([
                'count' => count($contacts),
                'contacts' => $contacts,
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
            'query' => $schema
                ->string()
                ->description('Search query (name, email, or phone).')
                ->required(),
            'maxResults' => $schema
                ->integer()
                ->description('Max results to return (default: 10, max: 30).'),
        ];
    }
}
