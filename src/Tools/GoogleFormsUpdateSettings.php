<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;

class GoogleFormsUpdateSettings implements Tool
{
    /** @var array<int, string> Valid email collection types */
    private const EMAIL_COLLECTION_TYPES = [
        'DO_NOT_COLLECT', 'VERIFIED', 'RESPONDER_INPUT',
    ];

    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function description(): string
    {
        return 'Update Google Form settings such as quiz mode and email collection. At least one setting must be provided.';
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

            $settings = [];
            $fields = [];

            if (isset($request['isQuiz'])) {
                $settings['quizSettings'] = ['isQuiz' => (bool) $request['isQuiz']];
                $fields[] = 'quizSettings.isQuiz';
            }

            if (isset($request['emailCollection'])) {
                $emailType = strtoupper((string) $request['emailCollection']);
                if (! in_array($emailType, self::EMAIL_COLLECTION_TYPES, true)) {
                    return 'Error: emailCollection must be one of: ' . implode(', ', self::EMAIL_COLLECTION_TYPES) . '.';
                }
                $settings['emailCollection'] = $emailType;
                $fields[] = 'emailCollection';
            }

            if (empty($fields)) {
                return 'Error: At least one setting is required (isQuiz, emailCollection).';
            }

            $this->service->batchUpdate((string) $formId, [
                ['updateSettings' => [
                    'settings' => $settings,
                    'updateMask' => implode(',', $fields),
                ]],
            ]);

            return 'Settings updated (' . implode(', ', $fields) . ').';
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
            'isQuiz' => $schema
                ->boolean()
                ->description('Enable or disable quiz mode.'),
            'emailCollection' => $schema
                ->string()
                ->description('Email collection: DO_NOT_COLLECT, VERIFIED, or RESPONDER_INPUT.'),
        ];
    }
}