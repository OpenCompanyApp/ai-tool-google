<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsAddTextItem implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Add a static text block to a Google Form. Omit index to add at end. Use google_forms_get to see current form structure.';
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

            $title = $request['title'] ?? '';
            if (empty($title)) {
                return 'Error: title is required.';
            }

            $item = [
                'title' => (string) $title,
                'textItem' => new \stdClass(),
            ];

            $description = $request['description'] ?? '';
            if ($description !== '') {
                $item['description'] = (string) $description;
            }

            $createRequest = ['createItem' => ['item' => $item]];

            $index = $request['index'] ?? null;
            if ($index !== null) {
                $createRequest['createItem']['location'] = ['index' => (int) $index];
            }

            $this->service->batchUpdate((string) $formId, [$createRequest]);

            $location = $index !== null ? "at index {$index}" : 'at end';

            return "Text item \"$title\" added {$location}.";
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
            'title' => $schema
                ->string()
                ->description('Title of the text item.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('Body text / description.'),
            'index' => $schema
                ->integer()
                ->description('Insert position (0-based). Omit to add at end.'),
        ];
    }
}