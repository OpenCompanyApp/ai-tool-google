<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsCreate implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Create a new Google Form with a title, optional description, and optional quiz mode. Auto-publishes. Returns form ID, edit URL, and responder URL.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Forms integration is not configured.';
            }

            $title = $request['title'] ?? '';
            if (empty($title)) {
                return 'Error: title is required.';
            }

            $result = $this->service->createForm((string) $title);
            $formId = $result['formId'] ?? '';
            $responderUri = $result['responderUri'] ?? '';
            $editUrl = "https://docs.google.com/forms/d/{$formId}/edit";

            // Add description if provided
            $description = $request['description'] ?? '';
            if ($description !== '') {
                $this->service->batchUpdate($formId, [
                    ['updateFormInfo' => [
                        'info' => ['description' => (string) $description],
                        'updateMask' => 'description',
                    ]],
                ]);
            }

            // Enable quiz mode if requested
            $isQuiz = (bool) ($request['isQuiz'] ?? false);
            if ($isQuiz) {
                $this->service->batchUpdate($formId, [
                    ['updateSettings' => [
                        'settings' => ['quizSettings' => ['isQuiz' => true]],
                        'updateMask' => 'quizSettings.isQuiz',
                    ]],
                ]);
            }

            // Auto-publish the form
            $this->service->setPublishSettings($formId, [
                'publishSettings' => [
                    'isPublished' => true,
                    'isAcceptingResponses' => true,
                ],
            ]);

            $lines = [
                'Form created.',
                "Title: \"$title\"",
                "ID: {$formId}",
                "Edit URL: {$editUrl}",
            ];

            if ($responderUri !== '') {
                $lines[] = "Responder URL: {$responderUri}";
            }

            return implode("\n", $lines);
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
            'title' => $schema
                ->string()
                ->description('Title of the new form.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('Description of the form.'),
            'isQuiz' => $schema
                ->boolean()
                ->description('Enable quiz mode (default false).'),
        ];
    }
}