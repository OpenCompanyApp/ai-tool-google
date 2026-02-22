<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarQuickAdd implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return 'Create a Google Calendar event from natural language text (e.g., "Lunch with Alice tomorrow at noon").';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Calendar integration is not configured.';
            }

            $calendarId = $request['calendarId'] ?? 'primary';
            $text = $request['text'] ?? '';

            if (empty($text)) {
                return 'Error: text is required (e.g., "Lunch with Alice tomorrow at noon").';
            }

            $event = $this->service->quickAddEvent($calendarId, $text);

            return "Event created via quick add.\n" . json_encode($this->formatEvent($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function formatEvent(array $event): array
    {
        $formatted = [
            'id' => $event['id'] ?? '',
            'summary' => $event['summary'] ?? '(No title)',
            'start' => $event['start']['dateTime'] ?? $event['start']['date'] ?? '',
            'end' => $event['end']['dateTime'] ?? $event['end']['date'] ?? '',
            'status' => $event['status'] ?? '',
            'htmlLink' => $event['htmlLink'] ?? '',
        ];

        if (! empty($event['description'])) {
            $formatted['description'] = $event['description'];
        }
        if (! empty($event['location'])) {
            $formatted['location'] = $event['location'];
        }
        if (! empty($event['attendees'])) {
            $formatted['attendees'] = array_map(fn (array $a) => [
                'email' => $a['email'] ?? '',
                'responseStatus' => $a['responseStatus'] ?? '',
            ], $event['attendees']);
        }
        if (! empty($event['recurrence'])) {
            $formatted['recurrence'] = $event['recurrence'];
        }

        return $formatted;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'calendarId' => $schema
                ->string()
                ->description('Calendar ID (default: "primary").'),
            'text' => $schema
                ->string()
                ->description('Natural language event text (e.g., "Lunch with Alice tomorrow at noon").')
                ->required(),
        ];
    }
}
