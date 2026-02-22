<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleAnalyticsService;

class GoogleAnalyticsListProperties implements Tool
{
    public function __construct(
        private GoogleAnalyticsService $service,
    ) {}

    public function description(): string
    {
        return 'List all accessible GA4 properties with their IDs and names. Use this first to discover the propertyId needed for other Analytics tools.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Analytics integration is not configured.';
            }

            $allAccounts = [];
            $pageToken = '';

            // Paginate through all account summaries
            do {
                $result = $this->service->listAccountSummaries($pageToken);
                $accounts = $result['accountSummaries'] ?? [];
                foreach ($accounts as $account) {
                    $allAccounts[] = $account;
                }
                $pageToken = $result['nextPageToken'] ?? '';
            } while ($pageToken !== '');

            if (empty($allAccounts)) {
                return 'No GA4 properties found. Ensure the connected Google account has access to Google Analytics.';
            }

            $lines = [];
            $totalProperties = 0;

            foreach ($allAccounts as $account) {
                $accountName = $account['displayName'] ?? 'Unknown';
                $properties = $account['propertySummaries'] ?? [];

                if (empty($properties)) {
                    continue;
                }

                $lines[] = "Account: {$accountName}";

                foreach ($properties as $prop) {
                    $propName = $prop['displayName'] ?? 'Unknown';
                    // Property name format: "properties/123456789" — extract the numeric ID
                    $propResource = $prop['property'] ?? '';
                    $propId = str_replace('properties/', '', $propResource);

                    $lines[] = "  - {$propName} (propertyId: {$propId})";
                    $totalProperties++;
                }
            }

            $header = "{$totalProperties} " . ($totalProperties === 1 ? 'property' : 'properties') . " found:\n";

            return $header . implode("\n", $lines);
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
