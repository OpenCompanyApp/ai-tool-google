<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarCreateEvent;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarDeleteEvent;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarFreeBusy;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarGetEvent;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarListCalendars;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarListEvents;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarQuickAdd;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarUpdateEvent;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class GoogleCalendarToolProvider implements ToolProvider, ConfigurableIntegration
{
    public function appName(): string
    {
        return 'google_calendar';
    }

    public function appMeta(): array
    {
        return [
            'label' => 'calendar, events, schedule, availability',
            'description' => 'Calendar management',
            'icon' => 'ph:calendar',
            'logo' => 'simple-icons:googlecalendar',
        ];
    }

    public function integrationMeta(): array
    {
        return [
            'name' => 'Google Calendar',
            'description' => 'Calendar events, scheduling, and availability',
            'icon' => 'ph:calendar',
            'logo' => 'simple-icons:googlecalendar',
            'category' => 'productivity',
            'badge' => 'verified',
            'docs_url' => 'https://console.cloud.google.com/apis/library/calendar-json.googleapis.com',
        ];
    }

    public function configSchema(): array
    {
        return [
            [
                'key' => 'client_id',
                'type' => 'text',
                'label' => 'Client ID',
                'placeholder' => 'Your Google Cloud OAuth Client ID',
                'hint' => 'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> &rarr; Credentials &rarr; OAuth 2.0 Client IDs. Shared across all Google integrations &mdash; only needs to be entered once.',
                'required' => true,
            ],
            [
                'key' => 'client_secret',
                'type' => 'secret',
                'label' => 'Client Secret',
                'placeholder' => 'Your Google Cloud OAuth Client Secret',
                'required' => true,
            ],
            [
                'key' => 'access_token',
                'type' => 'oauth_connect',
                'label' => 'Google Account',
                'authorize_url' => '/api/integrations/google/oauth/authorize?service=google_calendar',
                'redirect_uri' => '/api/integrations/google/oauth/callback',
            ],
        ];
    }

    public function testConnection(array $config): array
    {
        $accessToken = $config['access_token'] ?? '';
        $connectedEmail = $config['connected_email'] ?? null;

        if (empty($accessToken)) {
            return ['success' => false, 'error' => 'Not connected. Click "Connect with Google Calendar" to authorize.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
                'maxResults' => 10,
            ]);

            if ($response->successful()) {
                $items = $response->json('items') ?? [];
                $count = count($items);
                $emailInfo = $connectedEmail ? " as {$connectedEmail}" : '';

                return [
                    'success' => true,
                    'message' => "Connected to Google Calendar{$emailInfo}. Found {$count} calendar(s).",
                ];
            }

            $error = $response->json('error.message') ?? $response->body();

            return [
                'success' => false,
                'error' => 'Google Calendar API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, string|array<int, string>> */
    public function validationRules(): array
    {
        return [
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'access_token' => 'nullable|string',
        ];
    }

    public function tools(): array
    {
        return [
            'google_calendar_list_calendars' => [
                'class' => GoogleCalendarListCalendars::class,
                'type' => 'read',
                'name' => 'List Calendars',
                'description' => 'List all calendars the user has access to.',
                'icon' => 'ph:calendar-blank',
            ],
            'google_calendar_list_events' => [
                'class' => GoogleCalendarListEvents::class,
                'type' => 'read',
                'name' => 'List Events',
                'description' => 'List or search events in a calendar.',
                'icon' => 'ph:calendar-blank',
            ],
            'google_calendar_get_event' => [
                'class' => GoogleCalendarGetEvent::class,
                'type' => 'read',
                'name' => 'Get Event',
                'description' => 'Get a single calendar event by ID.',
                'icon' => 'ph:calendar-blank',
            ],
            'google_calendar_create_event' => [
                'class' => GoogleCalendarCreateEvent::class,
                'type' => 'write',
                'name' => 'Create Event',
                'description' => 'Create a new calendar event.',
                'icon' => 'ph:calendar-plus',
            ],
            'google_calendar_update_event' => [
                'class' => GoogleCalendarUpdateEvent::class,
                'type' => 'write',
                'name' => 'Update Event',
                'description' => 'Update an existing calendar event.',
                'icon' => 'ph:pencil-simple',
            ],
            'google_calendar_delete_event' => [
                'class' => GoogleCalendarDeleteEvent::class,
                'type' => 'write',
                'name' => 'Delete Event',
                'description' => 'Delete a calendar event.',
                'icon' => 'ph:trash',
            ],
            'google_calendar_quick_add' => [
                'class' => GoogleCalendarQuickAdd::class,
                'type' => 'write',
                'name' => 'Quick Add Event',
                'description' => 'Create an event from natural language text.',
                'icon' => 'ph:lightning',
            ],
            'google_calendar_freebusy' => [
                'class' => GoogleCalendarFreeBusy::class,
                'type' => 'read',
                'name' => 'Check Availability',
                'description' => 'Check free/busy status across calendars.',
                'icon' => 'ph:clock',
            ],
        ];
    }

    public function isIntegration(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function createTool(string $class, array $context = []): Tool
    {
        $service = app(GoogleCalendarService::class);

        return new $class($service);
    }
}
