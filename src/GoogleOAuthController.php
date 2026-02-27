<?php

namespace OpenCompany\AiToolGoogle;

use App\Models\IntegrationSetting;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    /** @var array<string, string> */
    private const SCOPES = [
        'google_calendar' => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email',
        'gmail' => 'https://www.googleapis.com/auth/gmail.modify https://www.googleapis.com/auth/userinfo.email',
        'google_drive' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/userinfo.email',
        'google_contacts' => 'https://www.googleapis.com/auth/contacts https://www.googleapis.com/auth/userinfo.email',
        'google_sheets' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/userinfo.email',
        'google_search_console' => 'https://www.googleapis.com/auth/webmasters https://www.googleapis.com/auth/userinfo.email',
        'google_tasks' => 'https://www.googleapis.com/auth/tasks https://www.googleapis.com/auth/userinfo.email',
        'google_analytics' => 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/userinfo.email',
        'google_docs' => 'https://www.googleapis.com/auth/documents https://www.googleapis.com/auth/userinfo.email',
        'google_forms' => 'https://www.googleapis.com/auth/forms.body https://www.googleapis.com/auth/forms.responses.readonly https://www.googleapis.com/auth/userinfo.email',
    ];

    /**
     * Redirect the user to Google's OAuth authorization page.
     */
    public function authorize(Request $request): \Illuminate\Http\RedirectResponse
    {
        $workspaceSlug = $this->resolveWorkspaceSlug();
        $request->session()->put('google_oauth_workspace_slug', $workspaceSlug);

        $service = $request->query('service', '');
        if (! is_string($service) || ! isset(self::SCOPES[$service])) {
            return redirect($this->settingsUrl($workspaceSlug))
                ->with('error', 'Invalid Google service. Expected google_calendar or gmail.');
        }

        $credentials = $this->resolveClientCredentials($service);

        if (! $credentials) {
            return redirect($this->settingsUrl($workspaceSlug))
                ->with('error', 'Google Client ID is not configured. Save your Client ID first.');
        }

        $clientId = $credentials['client_id'];

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_service', $service);

        $redirectUri = url('/api/integrations/google/oauth/callback');

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES[$service],
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect("https://accounts.google.com/o/oauth2/v2/auth?{$query}");
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        $storedState = $request->session()->pull('google_oauth_state');
        $service = $request->session()->pull('google_oauth_service');
        $workspaceSlug = $request->session()->pull('google_oauth_workspace_slug');

        if (! $storedState || $storedState !== $request->input('state')) {
            return redirect($this->settingsUrl($workspaceSlug))
                ->with('error', 'Invalid OAuth state. Please try connecting again.');
        }

        if (! $service || ! isset(self::SCOPES[$service])) {
            return redirect($this->settingsUrl($workspaceSlug))
                ->with('error', 'Invalid OAuth session. Please try connecting again.');
        }

        $code = $request->input('code');
        if (! $code) {
            $error = $request->input('error_description', $request->input('error', 'No authorization code received.'));

            return redirect($this->settingsUrl($workspaceSlug))
                ->with('error', "Google authorization failed: {$error}");
        }

        $credentials = $this->resolveClientCredentials($service);
        if (! $credentials) {
            return redirect($this->settingsUrl($workspaceSlug))
                ->with('error', 'Integration not found. Save your Client ID and Secret first.');
        }

        // Ensure the target integration setting exists (create if needed for token storage)
        $setting = IntegrationSetting::firstOrNew(['integration_id' => $service]);
        if (! $setting->id) {
            $setting->id = Str::uuid()->toString();
            if ($workspaceId = session('current_workspace_id')) {
                $setting->workspace_id = $workspaceId;
            }
        }

        $clientId = $credentials['client_id'];
        $clientSecret = $credentials['client_secret'];
        $redirectUri = url('/api/integrations/google/oauth/callback');

        try {
            // Exchange authorization code for tokens
            $tokenResponse = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if (! $tokenResponse->successful()) {
                $error = $tokenResponse->json('error_description') ?? $tokenResponse->json('error') ?? $tokenResponse->body();

                return redirect($this->settingsUrl($workspaceSlug))
                    ->with('error', 'Failed to exchange token: ' . (is_string($error) ? $error : json_encode($error)));
            }

            $tokenData = $tokenResponse->json() ?? [];
            $accessToken = $tokenData['access_token'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

            if (! $accessToken) {
                return redirect($this->settingsUrl($workspaceSlug))
                    ->with('error', 'No access token in response.');
            }

            // Fetch connected user email
            $connectedEmail = $this->fetchUserEmail($accessToken);

            // Store tokens in config
            /** @var array<string, mixed> $config */
            $config = $setting->config ?? [];
            $config['access_token'] = $accessToken;
            $config['refresh_token'] = $refreshToken;
            $config['expires_at'] = time() + $expiresIn;
            $config['connected_email'] = $connectedEmail;
            $setting->config = $config;
            $setting->enabled = true;
            $setting->save();

            $serviceNames = [
                'google_calendar' => 'Google Calendar',
                'gmail' => 'Gmail',
                'google_drive' => 'Google Drive',
                'google_contacts' => 'Google Contacts',
                'google_sheets' => 'Google Sheets',
                'google_search_console' => 'Google Search Console',
                'google_tasks' => 'Google Tasks',
                'google_analytics' => 'Google Analytics',
                'google_docs' => 'Google Docs',
                'google_forms' => 'Google Forms',
            ];
            $serviceName = $serviceNames[$service];
            $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

            return redirect($this->settingsUrl($workspaceSlug))
                ->with('success', "{$serviceName} connected successfully{$emailInfo}.");
        } catch (\Throwable $e) {
            return redirect($this->settingsUrl($workspaceSlug))
                ->with('error', 'OAuth token exchange failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve client_id and client_secret from the target integration or any sibling Google integration.
     *
     * @return array{client_id: string, client_secret: string}|null
     */
    private function resolveClientCredentials(string $service): ?array
    {
        $integrations = array_unique(array_merge([$service], array_keys(self::SCOPES)));

        foreach ($integrations as $id) {
            $setting = IntegrationSetting::where('integration_id', $id)->first();
            $clientId = $setting?->getConfigValue('client_id');
            $clientSecret = $setting?->getConfigValue('client_secret');

            if (! empty($clientId) && ! empty($clientSecret)) {
                return ['client_id' => (string) $clientId, 'client_secret' => (string) $clientSecret];
            }
        }

        return null;
    }

    /**
     * Fetch the authenticated user's email address.
     */
    private function fetchUserEmail(string $accessToken): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->timeout(10)->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                return $response->json('email');
            }
        } catch (\Throwable) {
            // Non-critical — we can proceed without the email
        }

        return null;
    }

    /**
     * Resolve the current workspace slug from session.
     */
    private function resolveWorkspaceSlug(): ?string
    {
        $workspaceId = session('current_workspace_id');
        if ($workspaceId) {
            return Workspace::where('id', $workspaceId)->value('slug');
        }

        return null;
    }

    /**
     * Build the settings URL with workspace prefix.
     */
    private function settingsUrl(?string $workspaceSlug): string
    {
        if ($workspaceSlug) {
            return "/w/{$workspaceSlug}/settings?tab=integrations";
        }

        return '/settings?tab=integrations';
    }
}
