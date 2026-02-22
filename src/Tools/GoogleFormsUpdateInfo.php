<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsUpdateInfo implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Update a Google Form title and/or description. At least one of title or description must be provided.';
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

            $title = $request['title'] ?? null;
            $description = $request['description'] ?? null;

            if ($title === null && $description === null) {
                return 'Error: At least one of title or description is required.';
            }

            $info = [];
            $fields = [];

            if ($title !== null) {
                $info['title'] = (string) $title;
                $fields[] = 'title';
            }
            if ($description !== null) {
                $info['description'] = (string) $description;
                $fields[] = 'description';
            }

            $this->service->batchUpdate((string) $formId, [
                ['updateFormInfo' => [
                    'info' => $info,
                    'updateMask' => implode(',', $fields),
                ]],
            ]);

            return 'Form info updated (' . implode(', ', $fields) . ').';
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
                ->description('New title for the form.'),
            'description' => $schema
                ->string()
                ->description('New description for the form.'),
        ];
    }
}