<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailListLabels implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return 'List all labels in the Gmail mailbox (INBOX, SENT, custom labels, etc.).';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $result = $this->service->listLabels();
            $labels = $result['labels'] ?? [];

            if (empty($labels)) {
                return 'No labels found.';
            }

            $formatted = array_map(fn (array $label) => [
                'id' => $label['id'] ?? '',
                'name' => $label['name'] ?? '',
                'type' => $label['type'] ?? '',
            ], $labels);

            return json_encode(['count' => count($formatted), 'labels' => $formatted], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
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
