<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarEvent;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarFreeBusy;
use OpenCompany\AiToolGoogle\Tools\GoogleCalendarList;
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
                'hint' => 'From <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> &rarr; Credentials &rarr; OAuth 2.0 Client IDs. Enable the <strong>Google Calendar API</strong> first.',
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
            'google_calendar_list' => [
                'class' => GoogleCalendarList::class,
                'type' => 'read',
                'name' => 'List Calendars & Events',
                'description' => 'List calendars and search/list events.',
                'icon' => 'ph:calendar-blank',
            ],
            'google_calendar_event' => [
                'class' => GoogleCalendarEvent::class,
                'type' => 'write',
                'name' => 'Manage Events',
                'description' => 'Create, update, delete, or quick-add calendar events.',
                'icon' => 'ph:calendar-plus',
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
