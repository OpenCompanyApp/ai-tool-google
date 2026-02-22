<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GmailService;

class GmailSendDraft implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function description(): string
    {
        return 'Send a previously created Gmail draft by its ID.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Gmail integration is not configured.';
            }

            $draftId = $request['draftId'] ?? '';
            if (empty($draftId)) {
                return 'Error: draftId is required.';
            }

            $result = $this->service->sendDraft(['id' => $draftId]);

            return "Draft sent successfully.\n" . json_encode([
                'id' => $result['id'] ?? '',
                'threadId' => $result['threadId'] ?? '',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            'draftId' => $schema
                ->string()
                ->description('Draft ID to send.')
                ->required(),
        ];
    }
}
