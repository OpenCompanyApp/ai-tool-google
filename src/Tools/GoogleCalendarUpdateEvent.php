<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarUpdateEvent implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return 'Update an existing Google Calendar event (partial update). Only specified fields are changed.';
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

            $data = [];

            if (isset($request['summary'])) {
                $data['summary'] = $request['summary'];
            }
            if (isset($request['description'])) {
                $data['description'] = $request['description'];
            }
            if (isset($request['location'])) {
                $data['location'] = $request['location'];
            }
            if (isset($request['startDateTime'])) {
                $data['start'] = ['dateTime' => $request['startDateTime']];
                if (isset($request['timeZone'])) {
                    $data['start']['timeZone'] = $request['timeZone'];
                }
            } elseif (isset($request['startDate'])) {
                $data['start'] = ['date' => $request['startDate']];
            }
            if (isset($request['endDateTime'])) {
                $data['end'] = ['dateTime' => $request['endDateTime']];
                if (isset($request['timeZone'])) {
                    $data['end']['timeZone'] = $request['timeZone'];
                }
            } elseif (isset($request['endDate'])) {
                $data['end'] = ['date' => $request['endDate']];
            }
            if (isset($request['attendees'])) {
                $emails = array_map('trim', explode(',', $request['attendees']));
                $data['attendees'] = array_map(fn (string $email) => ['email' => $email], $emails);
            }
            if (isset($request['recurrence'])) {
                $data['recurrence'] = [$request['recurrence']];
            }

            if (empty($data)) {
                return 'Error: At least one field to update is required.';
            }

            $event = $this->service->updateEvent($calendarId, $eventId, $data);

            return "Event updated successfully.\n" . json_encode($this->formatEvent($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            'eventId' => $schema
                ->string()
                ->description('Event ID to update.')
                ->required(),
            'summary' => $schema
                ->string()
                ->description('Event title.'),
            'description' => $schema
                ->string()
                ->description('Event description.'),
            'location' => $schema
                ->string()
                ->description('Event location.'),
            'startDateTime' => $schema
                ->string()
                ->description('ISO 8601 start time for timed events (e.g., "2026-02-15T10:00:00-05:00").'),
            'endDateTime' => $schema
                ->string()
                ->description('ISO 8601 end time for timed events.'),
            'startDate' => $schema
                ->string()
                ->description('YYYY-MM-DD start date for all-day events.'),
            'endDate' => $schema
                ->string()
                ->description('YYYY-MM-DD end date for all-day events.'),
            'timeZone' => $schema
                ->string()
                ->description('IANA timezone (e.g., "America/New_York"). Applied to start/end times.'),
            'attendees' => $schema
                ->string()
                ->description('Comma-separated attendee email addresses.'),
            'recurrence' => $schema
                ->string()
                ->description('RRULE recurrence string (e.g., "RRULE:FREQ=WEEKLY;COUNT=10").'),
        ];
    }
}
