<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarDeleteEvent implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return 'Delete a Google Calendar event by its ID.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Calendar integration is not configured.';
            }

            $calendarId = $request['calendarId'] ?? 'primary';
            $eventId = $request['eventId'] ?? '';

            if (empty($eventId)) {
                return 'Error: eventId is required.';
            }

            $this->service->deleteEvent($calendarId, $eventId);

            return "Event '{$eventId}' deleted successfully.";
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
            'calendarId' => $schema
                ->string()
                ->description('Calendar ID (default: "primary").'),
            'eventId' => $schema
                ->string()
                ->description('Event ID to delete.')
                ->required(),
        ];
    }
}
