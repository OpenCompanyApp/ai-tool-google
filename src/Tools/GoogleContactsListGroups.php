<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;

class GoogleContactsListGroups implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function description(): string
    {
        return 'List all Google Contact groups/labels (e.g., Friends, Family, custom groups) with member counts.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Contacts integration is not configured.';
            }

            $result = $this->service->listContactGroups();
            $groups = $result['contactGroups'] ?? [];

            if (empty($groups)) {
                return 'No contact groups found.';
            }

            $formatted = [];
            foreach ($groups as $group) {
                $groupType = $group['groupType'] ?? '';
                $formatted[] = array_filter([
                    'resourceName' => $group['resourceName'] ?? '',
                    'name' => $group['name'] ?? $group['formattedName'] ?? '',
                    'type' => $groupType,
                    'memberCount' => $group['memberCount'] ?? 0,
                ], fn ($v) => $v !== '' && $v !== 0);
            }

            return json_encode([
                'count' => count($formatted),
                'groups' => $formatted,
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
        return [];
    }
}
