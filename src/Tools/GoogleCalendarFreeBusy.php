<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;

class GoogleCalendarFreeBusy implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function description(): string
    {
        return <<<'MD'
        Check free/busy availability across one or more Google Calendars.
        Returns busy time slots within the specified time range.
        Useful for finding open slots for scheduling meetings.
        MD;
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Calendar integration is not configured.';
            }

            $timeMin = $request['timeMin'] ?? '';
            $timeMax = $request['timeMax'] ?? '';

            if (empty($timeMin) || empty($timeMax)) {
                return 'Error: timeMin and timeMax are required (ISO 8601 format).';
            }

            $calendarIds = $request['calendarIds'] ?? 'primary';
            $ids = array_map('trim', explode(',', $calendarIds));
            $items = array_map(fn (string $id) => ['id' => $id], $ids);

            $result = $this->service->queryFreeBusy([
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'items' => $items,
            ]);

            $calendars = $result['calendars'] ?? [];
            if (empty($calendars)) {
                return 'No free/busy data returned.';
            }

            $output = [];
            foreach ($calendars as $calId => $calData) {
                $busy = $calData['busy'] ?? [];
                $errors = $calData['errors'] ?? [];

                $output[$calId] = [
                    'busySlots' => count($busy),
                    'busy' => array_map(fn (array $slot) => [
                        'start' => $slot['start'] ?? '',
                        'end' => $slot['end'] ?? '',
                    ], $busy),
                ];

                if (! empty($errors)) {
                    $output[$calId]['errors'] = $errors;
                }
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
            'timeMin' => $schema
                ->string()
                ->description('ISO 8601 start of query range (e.g., "2026-02-14T08:00:00Z").')
                ->required(),
            'timeMax' => $schema
                ->string()
                ->description('ISO 8601 end of query range (e.g., "2026-02-14T18:00:00Z").')
                ->required(),
            'calendarIds' => $schema
                ->string()
                ->description('Comma-separated calendar IDs to check (default: "primary").'),
        ];
    }
}
