<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsGetResponse implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Get a single response to a Google Form by response ID, with question labels.
        The form ID is the long string in the Google Forms URL: docs.google.com/forms/d/{formId}/edit
        MD;
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

            $responseId = $request['responseId'] ?? '';
            if (empty($responseId)) {
                return 'Error: responseId is required.';
            }

            $response = $this->service->getResponse((string) $formId, (string) $responseId);

            // Fetch form for question labels
            $form = $this->service->getForm((string) $formId);

            // Format as single response
            return $this->service->formatResponses(['responses' => [$response]], $form);
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
                ->description('Google Forms form ID (from the URL).')
                ->required(),
            'responseId' => $schema
                ->string()
                ->description('Response ID.')
                ->required(),
        ];
    }
}