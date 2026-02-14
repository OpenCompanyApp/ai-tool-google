<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarList implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        List Google Calendars or events. Actions:
        - **list_calendars**: List all calendars the user has access to.
        - **list_events**: List or search events in a calendar. Supports date range filtering and text search.
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
                return 'Error: action is required (list_calendars, list_events).';
            }

            return match ($action) {
                'list_calendars' => $this->listCalendars($request),
                'list_events' => $this->listEvents($request),
                default => "Error: Unknown action '{$action}'. Use: list_calendars, list_events.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function listCalendars(Request $request): string
    {
        $params = [];
        if (isset($request['maxResults'])) {
            $params['maxResults'] = (int) $request['maxResults'];
        }
        if (isset($request['pageToken'])) {
            $params['pageToken'] = $request['pageToken'];
        }

        $result = $this->service->listCalendars($params);
        $items = $result['items'] ?? [];

        if (empty($items)) {
            return 'No calendars found.';
        }

        $calendars = array_map(fn (array $cal) => [
            'id' => $cal['id'] ?? '',
            'summary' => $cal['summary'] ?? '',
            'primary' => $cal['primary'] ?? false,
            'accessRole' => $cal['accessRole'] ?? '',
            'backgroundColor' => $cal['backgroundColor'] ?? '',
        ], $items);

        $output = ['count' => count($calendars), 'calendars' => $calendars];
        if (isset($result['nextPageToken'])) {
            $output['nextPageToken'] = $result['nextPageToken'];
        }

        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function listEvents(Request $request): string
    {
        $calendarId = $request['calendarId'] ?? 'primary';

        $params = [
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
        ];

        if (isset($request['timeMin'])) {
            $params['timeMin'] = $request['timeMin'];
        }
        if (isset($request['timeMax'])) {
            $params['timeMax'] = $request['timeMax'];
        }
        if (isset($request['query'])) {
            $params['q'] = $request['query'];
        }
        if (isset($request['maxResults'])) {
            $params['maxResults'] = (string) (int) $request['maxResults'];
        }
        if (isset($request['pageToken'])) {
            $params['pageToken'] = $request['pageToken'];
        }

        $result = $this->service->listEvents($calendarId, $params);
        $items = $result['items'] ?? [];

        if (empty($items)) {
            return 'No events found.';
        }

        $events = array_map(fn (array $event) => [
            'id' => $event['id'] ?? '',
            'summary' => $event['summary'] ?? '(No title)',
            'start' => $event['start']['dateTime'] ?? $event['start']['date'] ?? '',
            'end' => $event['end']['dateTime'] ?? $event['end']['date'] ?? '',
            'location' => $event['location'] ?? null,
            'status' => $event['status'] ?? '',
            'htmlLink' => $event['htmlLink'] ?? '',
        ], $items);

        // Remove null values
        $events = array_map(fn (array $e) => array_filter($e, fn ($v) => $v !== null), $events);

        $output = ['count' => count($events), 'events' => $events];
        if (isset($result['nextPageToken'])) {
            $output['nextPageToken'] = $result['nextPageToken'];
        }

        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action: "list_calendars" or "list_events".')
                ->required(),
            'calendarId' => $schema
                ->string()
                ->description('Calendar ID (default: "primary"). For list_events.'),
            'timeMin' => $schema
                ->string()
                ->description('ISO 8601 start filter (e.g., "2026-02-14T00:00:00Z"). For list_events.'),
            'timeMax' => $schema
                ->string()
                ->description('ISO 8601 end filter (e.g., "2026-02-21T23:59:59Z"). For list_events.'),
            'query' => $schema
                ->string()
                ->description('Free text search within events. For list_events.'),
            'maxResults' => $schema
                ->integer()
                ->description('Max results to return (default: 25, max: 250).'),
            'pageToken' => $schema
                ->string()
                ->description('Pagination token from previous response.'),
        ];
    }
}
