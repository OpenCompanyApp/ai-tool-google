<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsListResponses implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        List responses to a Google Form with question labels.
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

            $after = $request['after'] ?? null;
            $pageSize = (int) ($request['pageSize'] ?? 10);
            $pageToken = $request['pageToken'] ?? null;

            $filter = null;
            if ($after !== null && $after !== '') {
                $filter = "timestamp >= {$after}";
            }

            $responsesData = $this->service->listResponses(
                (string) $formId,
                $filter,
                $pageSize,
                $pageToken !== null ? (string) $pageToken : null,
            );

            // Fetch form to get question labels
            $form = $this->service->getForm((string) $formId);

            return $this->service->formatResponses($responsesData, $form);
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
            'after' => $schema
                ->string()
                ->description('Only responses after this timestamp (RFC3339).'),
            'pageSize' => $schema
                ->integer()
                ->description('Max responses per page (default 10, max 5000).'),
            'pageToken' => $schema
                ->string()
                ->description('Pagination token from previous response.'),
        ];
    }
}
