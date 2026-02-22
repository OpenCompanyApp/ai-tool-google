<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;

class GoogleContactsGet implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function description(): string
    {
        return 'Get full details of a single Google Contact including notes, websites, and group memberships.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Contacts integration is not configured.';
            }

            $resourceName = $request['resourceName'] ?? '';
            if (empty($resourceName)) {
                return 'Error: resourceName is required (e.g., "people/c1234567890").';
            }

            $person = $this->service->getContact($resourceName);
            $contact = GoogleContactsService::formatContact($person);

            return json_encode($contact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
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
            'resourceName' => $schema
                ->string()
                ->description('Contact resource name (e.g., "people/c1234567890").')
                ->required(),
        ];
    }
}
