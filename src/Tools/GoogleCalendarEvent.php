<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarEvent implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Manage Google Calendar events. Actions:
        - **get**: Get a single event by ID.
        - **create**: Create an event. Use startDateTime/endDateTime for timed events, or startDate/endDate for all-day events.
        - **update**: Update an existing event (partial update).
        - **delete**: Delete an event.
        - **quick_add**: Create an event from natural language text (e.g., "Lunch with Alice tomorrow at noon").
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Calendar integration is not configured.';
            }

            $action = $request['action'] ?? '';
            if (empty($action)) {
                return 'Error: action is required (get, create, update, delete, quick_add).';
            }

            return match ($action) {
                'get' => $this->getEvent($request),
                'create' => $this->createEvent($request),
                'update' => $this->updateEvent($request),
                'delete' => $this->deleteEvent($request),
                'quick_add' => $this->quickAddEvent($request),
                default => "Error: Unknown action '{$action}'. Use: get, create, update, delete, quick_add.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function getEvent(Request $request): string
    {
        $calendarId = $request['calendarId'] ?? 'primary';
        $eventId = $request['eventId'] ?? '';

        if (empty($eventId)) {
            return 'Error: eventId is required for get action.';
        }

        $event = $this->service->getEvent($calendarId, $eventId);

        return json_encode($this->formatEvent($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function createEvent(Request $request): string
    {
        $calendarId = $request['calendarId'] ?? 'primary';
        $summary = $request['summary'] ?? '';

        if (empty($summary)) {
            return 'Error: summary is required for create action.';
        }

        $data = ['summary' => $summary];

        if (isset($request['description'])) {
            $data['description'] = $request['description'];
        }
        if (isset($request['location'])) {
            $data['location'] = $request['location'];
        }

        // Timed events
        if (isset($request['startDateTime'])) {
            $data['start'] = ['dateTime' => $request['startDateTime']];
            if (isset($request['timeZone'])) {
                $data['start']['timeZone'] = $request['timeZone'];
            }
        } elseif (isset($request['startDate'])) {
            $data['start'] = ['date' => $request['startDate']];
        } else {
            return 'Error: startDateTime or startDate is required for create action.';
        }

        if (isset($request['endDateTime'])) {
            $data['end'] = ['dateTime' => $request['endDateTime']];
            if (isset($request['timeZone'])) {
                $data['end']['timeZone'] = $request['timeZone'];
            }
        } elseif (isset($request['endDate'])) {
            $data['end'] = ['date' => $request['endDate']];
        } else {
            return 'Error: endDateTime or endDate is required for create action.';
        }

        // Attendees
        if (isset($request['attendees'])) {
            $emails = array_map('trim', explode(',', $request['attendees']));
            $data['attendees'] = array_map(fn (string $email) => ['email' => $email], $emails);
        }

        // Recurrence
        if (isset($request['recurrence'])) {
            $data['recurrence'] = [$request['recurrence']];
        }

        $event = $this->service->createEvent($calendarId, $data);

        return "Event created successfully.\n" . json_encode($this->formatEvent($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function updateEvent(Request $request): string
    {
        $calendarId = $request['calendarId'] ?? 'primary';
        $eventId = $request['eventId'] ?? '';

        if (empty($eventId)) {
            return 'Error: eventId is required for update action.';
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
    }

    private function deleteEvent(Request $request): string
    {
        $calendarId = $request['calendarId'] ?? 'primary';
        $eventId = $request['eventId'] ?? '';

        if (empty($eventId)) {
            return 'Error: eventId is required for delete action.';
        }

        $this->service->deleteEvent($calendarId, $eventId);

        return "Event '{$eventId}' deleted successfully.";
    }

    private function quickAddEvent(Request $request): string
    {
        $calendarId = $request['calendarId'] ?? 'primary';
        $text = $request['text'] ?? '';

        if (empty($text)) {
            return 'Error: text is required for quick_add action (e.g., "Lunch with Alice tomorrow at noon").';
        }

        $event = $this->service->quickAddEvent($calendarId, $text);

        return "Event created via quick add.\n" . json_encode($this->formatEvent($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            'action' => $schema
                ->string()
                ->description('Action: "get", "create", "update", "delete", or "quick_add".')
                ->required(),
            'calendarId' => $schema
                ->string()
                ->description('Calendar ID (default: "primary").'),
            'eventId' => $schema
                ->string()
                ->description('Event ID (required for get, update, delete).'),
            'summary' => $schema
                ->string()
                ->description('Event title (required for create).'),
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
            'text' => $schema
                ->string()
                ->description('Natural language event text for quick_add (e.g., "Lunch with Alice tomorrow at noon").'),
        ];
    }
}
