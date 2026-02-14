<?php

namespace OpenCompany\AiToolGoogle;

use App\Models\IntegrationSetting;
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
    ];

    /**
     * Redirect the user to Google's OAuth authorization page.
     */
    public function authorize(Request $request): \Illuminate\Http\RedirectResponse
    {
        $service = $request->query('service', '');
        if (! is_string($service) || ! isset(self::SCOPES[$service])) {
            return redirect('/settings?tab=integrations')
                ->with('error', 'Invalid Google service. Expected google_calendar or gmail.');
        }

        $setting = IntegrationSetting::where('integration_id', $service)->first();
        $clientId = $setting?->getConfigValue('client_id');

        if (! $clientId) {
            return redirect('/settings?tab=integrations')
                ->with('error', 'Google Client ID is not configured. Save your Client ID first.');
        }

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

        if (! $storedState || $storedState !== $request->input('state')) {
            return redirect('/settings?tab=integrations')
                ->with('error', 'Invalid OAuth state. Please try connecting again.');
        }

        if (! $service || ! isset(self::SCOPES[$service])) {
            return redirect('/settings?tab=integrations')
                ->with('error', 'Invalid OAuth session. Please try connecting again.');
        }

        $code = $request->input('code');
        if (! $code) {
            $error = $request->input('error_description', $request->input('error', 'No authorization code received.'));

            return redirect('/settings?tab=integrations')
                ->with('error', "Google authorization failed: {$error}");
        }

        $setting = IntegrationSetting::where('integration_id', $service)->first();
        if (! $setting) {
            return redirect('/settings?tab=integrations')
                ->with('error', 'Integration not found. Save your Client ID and Secret first.');
        }

        $clientId = $setting->getConfigValue('client_id');
        $clientSecret = $setting->getConfigValue('client_secret');
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

                return redirect('/settings?tab=integrations')
                    ->with('error', 'Failed to exchange token: ' . (is_string($error) ? $error : json_encode($error)));
            }

            $tokenData = $tokenResponse->json() ?? [];
            $accessToken = $tokenData['access_token'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

            if (! $accessToken) {
                return redirect('/settings?tab=integrations')
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

            $serviceName = $service === 'google_calendar' ? 'Google Calendar' : 'Gmail';
            $emailInfo = $connectedEmail ? " ({$connectedEmail})" : '';

            return redirect('/settings?tab=integrations')
                ->with('success', "{$serviceName} connected successfully{$emailInfo}.");
        } catch (\Throwable $e) {
            return redirect('/settings?tab=integrations')
                ->with('error', 'OAuth token exchange failed: ' . $e->getMessage());
        }
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
}
