<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsUpdateQuestion implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Update a question in a Google Form by its 0-based index. Can update title, description, required status, and options (for choice questions). Use google_forms_get to see current form structure.';
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

            // Fetch current form to get the item at this index
            $form = $this->service->getForm((string) $formId);
            $items = $form['items'] ?? [];

            if ((int) $index >= count($items) || (int) $index < 0) {
                return 'Error: index ' . $index . ' is out of range. Form has ' . count($items) . ' items.';
            }

            $currentItem = $items[(int) $index];

            // Build updated item
            $updateMask = [];

            if (isset($request['title'])) {
                $currentItem['title'] = (string) $request['title'];
                $updateMask[] = 'title';
            }

            if (isset($request['description'])) {
                $currentItem['description'] = (string) $request['description'];
                $updateMask[] = 'description';
            }

            if (isset($request['required']) && isset($currentItem['questionItem'])) {
                $currentItem['questionItem']['question']['required'] = (bool) $request['required'];
                $updateMask[] = 'questionItem.question.required';
            }

            if (isset($request['options']) && isset($currentItem['questionItem'])) {
                $question = $currentItem['questionItem']['question'] ?? [];
                if (isset($question['choiceQuestion'])) {
                    $options = is_array($request['options']) ? $request['options'] : [];
                    $choiceOptions = [];
                    foreach ($options as $opt) {
                        $choiceOptions[] = ['value' => (string) $opt];
                    }
                    $currentItem['questionItem']['question']['choiceQuestion']['options'] = $choiceOptions;
                    $updateMask[] = 'questionItem.question.choiceQuestion.options';
                }
            }

            if (empty($updateMask)) {
                return 'Error: At least one update field is required (title, description, required, options).';
            }

            $this->service->batchUpdate((string) $formId, [
                ['updateItem' => [
                    'item' => $currentItem,
                    'location' => ['index' => (int) $index],
                    'updateMask' => implode(',', $updateMask),
                ]],
            ]);

            return 'Question at index ' . $index . ' updated (' . implode(', ', $updateMask) . ').';
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
                ->description('0-based item position of the question to update.')
                ->required(),
            'title' => $schema
                ->string()
                ->description('New title for the question.'),
            'description' => $schema
                ->string()
                ->description('New description/help text for the question.'),
            'required' => $schema
                ->boolean()
                ->description('Whether the question is required.'),
            'options' => $schema
                ->array()
                ->description('New options array (for choice questions: multiple_choice, checkbox, dropdown).'),
        ];
    }
}