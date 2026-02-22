<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarListEvents implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return 'List or search events in a Google Calendar. Supports date range filtering and text search.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Calendar integration is not configured.';
            }

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
            'timeMin' => $schema
                ->string()
                ->description('ISO 8601 start filter (e.g., "2026-02-14T00:00:00Z").'),
            'timeMax' => $schema
                ->string()
                ->description('ISO 8601 end filter (e.g., "2026-02-21T23:59:59Z").'),
            'query' => $schema
                ->string()
                ->description('Free text search within events.'),
            'maxResults' => $schema
                ->integer()
                ->description('Max results to return (default: 25, max: 250).'),
            'pageToken' => $schema
                ->string()
                ->description('Pagination token from previous response.'),
        ];
    }
}
