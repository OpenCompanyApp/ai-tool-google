<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsPublish implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Set publish settings for a Google Form: publish/unpublish and accept/stop accepting responses. At least one of published or acceptingResponses must be provided.';
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

            $published = $request['published'] ?? null;
            $acceptingResponses = $request['acceptingResponses'] ?? null;

            if ($published === null && $acceptingResponses === null) {
                return 'Error: At least one of published or acceptingResponses is required.';
            }

            $publishSettings = [];
            $changes = [];

            if ($published !== null) {
                $publishSettings['isPublished'] = (bool) $published;
                $changes[] = $published ? 'published' : 'unpublished';
            }

            if ($acceptingResponses !== null) {
                $publishSettings['isAcceptingResponses'] = (bool) $acceptingResponses;
                $changes[] = $acceptingResponses ? 'accepting responses' : 'not accepting responses';
            }

            $this->service->setPublishSettings((string) $formId, [
                'publishSettings' => $publishSettings,
            ]);

            return 'Form is now ' . implode(' and ', $changes) . '.';
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
            'published' => $schema
                ->boolean()
                ->description('Publish or unpublish the form.'),
            'acceptingResponses' => $schema
                ->boolean()
                ->description('Accept or stop accepting responses.'),
        ];
    }
}