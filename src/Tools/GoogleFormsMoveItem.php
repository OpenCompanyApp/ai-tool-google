<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsMoveItem implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Move an item in a Google Form from one 0-based index to another. Use google_forms_get to see current form structure.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Forms integration is not configured.';
            }

            $formId = $request['formId'] ?? '';
            if (empty($formId)) {
                return 'Error: formId is required.';
            }

            $from = $request['from'] ?? null;
            $to = $request['to'] ?? null;
            if ($from === null || $to === null) {
                return 'Error: from and to are required (0-based positions).';
            }

            $this->service->batchUpdate((string) $formId, [
                ['moveItem' => [
                    'originalLocation' => ['index' => (int) $from],
                    'newLocation' => ['index' => (int) $to],
                ]],
            ]);

            return "Item moved from index {$from} to index {$to}.";
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
            'formId' => $schema
                ->string()
                ->description('Google Forms form ID.')
                ->required(),
            'from' => $schema
                ->integer()
                ->description('Current item index (0-based).')
                ->required(),
            'to' => $schema
                ->integer()
                ->description('Target index (0-based).')
                ->required(),
        ];
    }
}