<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarListCalendars implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return 'List all Google Calendars the user has access to.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Calendar integration is not configured.';
            }

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
            'maxResults' => $schema
                ->integer()
                ->description('Max results to return (default: 25, max: 250).'),
            'pageToken' => $schema
                ->string()
                ->description('Pagination token from previous response.'),
        ];
    }
}
