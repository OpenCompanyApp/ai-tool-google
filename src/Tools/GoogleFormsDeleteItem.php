<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsDeleteItem implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Delete an item from a Google Form by its 0-based index. Use google_forms_get to see current form structure.';
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

            $index = $request['index'] ?? null;
            if ($index === null) {
                return 'Error: index is required (0-based item position).';
            }

            $this->service->batchUpdate((string) $formId, [
                ['deleteItem' => [
                    'location' => ['index' => (int) $index],
                ]],
            ]);

            return "Item at index {$index} deleted.";
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
            'index' => $schema
                ->integer()
                ->description('0-based position of the item to delete.')
                ->required(),
        ];
    }
}