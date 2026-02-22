<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsAddQuestion implements Tool
{
    /** @var array<int, string> Valid question types */
    private const QUESTION_TYPES = [
        'text', 'paragraph', 'multiple_choice', 'checkbox', 'dropdown',
        'scale', 'date', 'time', 'rating',
    ];

    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Add a question to a Google Form. Supports types: text, paragraph, multiple_choice, checkbox, dropdown, scale, date, time, rating. Use options for choice types. Use low/high/lowLabel/highLabel for scale. Use ratingScale/ratingIcon for rating. Use includeTime/includeYear for date. Use duration for time. Omit index to add at end. Use google_forms_get to see current form structure before editing.';
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

            $type = $request['type'] ?? '';
            if (empty($type) || ! in_array((string) $type, self::QUESTION_TYPES, true)) {
                return 'Error: type is required. Valid values: ' . implode(', ', self::QUESTION_TYPES) . '.';
            }

            // Build options array from request
            $options = $request['options'] ?? [];
            if (! is_array($options)) {
                $options = [];
            }

            $item = $this->service->buildQuestionItem([
                'type' => (string) $type,
                'title' => (string) $title,
                'description' => (string) ($request['description'] ?? ''),
                'required' => (bool) ($request['required'] ?? false),
                'options' => $options,
                'low' => $request['low'] ?? 1,
                'high' => $request['high'] ?? 5,
                'lowLabel' => (string) ($request['lowLabel'] ?? ''),
                'highLabel' => (string) ($request['highLabel'] ?? ''),
                'ratingScale' => $request['ratingScale'] ?? 5,
                'ratingIcon' => (string) ($request['ratingIcon'] ?? 'STAR'),
                'includeTime' => (bool) ($request['includeTime'] ?? false),
                'includeYear' => (bool) ($request['includeYear'] ?? true),
                'duration' => (bool) ($request['duration'] ?? false),
            ]);

            $createRequest = ['createItem' => ['item' => $item]];

            $index = $request['index'] ?? null;
            if ($index !== null) {
                $createRequest['createItem']['location'] = ['index' => (int) $index];
            }

            $this->service->batchUpdate((string) $formId, [$createRequest]);

            $location = $index !== null ? "at index {$index}" : 'at end';

            return "Question added {$location}: [{$type}] \"$title\"";
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
                ->description('Question title text.')
                ->required(),
            'type' => $schema
                ->string()
                ->description('Question type: text, paragraph, multiple_choice, checkbox, dropdown, scale, date, time, or rating.')
                ->required(),
            'required' => $schema
                ->boolean()
                ->description('Whether the question is required (default false).'),
            'description' => $schema
                ->string()
                ->description('Help text / description for the question.'),
            'options' => $schema
                ->array()
                ->description('Array of option strings (for multiple_choice, checkbox, dropdown).'),
            'low' => $schema
                ->integer()
                ->description('For scale: low value (default 1).'),
            'high' => $schema
                ->integer()
                ->description('For scale: high value (default 5).'),
            'lowLabel' => $schema
                ->string()
                ->description('For scale: label for the low end.'),
            'highLabel' => $schema
                ->string()
                ->description('For scale: label for the high end.'),
            'ratingScale' => $schema
                ->integer()
                ->description('For rating: scale level 3-10 (default 5).'),
            'ratingIcon' => $schema
                ->string()
                ->description('For rating: STAR (default), HEART, or THUMB_UP.'),
            'includeTime' => $schema
                ->boolean()
                ->description('For date: include time (default false).'),
            'includeYear' => $schema
                ->boolean()
                ->description('For date: include year (default true).'),
            'duration' => $schema
                ->boolean()
                ->description('For time: duration mode instead of time-of-day (default false).'),
            'index' => $schema
                ->integer()
                ->description('Insert position (0-based). Omit to add at end.'),
        ];
    }
}