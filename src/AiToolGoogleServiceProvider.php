<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OpenCompany\AiToolGoogle\Services\GmailService;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class AiToolGoogleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleCalendarService::class, function ($app) {
            $creds = $app->make(CredentialResolver::class);

            $client = new GoogleClient(
                clientId: $creds->get('google_calendar', 'client_id', ''),
                clientSecret: $creds->get('google_calendar', 'client_secret', ''),
                accessToken: $creds->get('google_calendar', 'access_token', ''),
                refreshToken: $creds->get('google_calendar', 'refresh_token', ''),
                expiresAt: $creds->get('google_calendar', 'expires_at'),
                integrationId: 'google_calendar',
            );

            return new GoogleCalendarService($client);
        });

        $this->app->singleton(GmailService::class, function ($app) {
            $creds = $app->make(CredentialResolver::class);

            $client = new GoogleClient(
                clientId: $creds->get('gmail', 'client_id', ''),
                clientSecret: $creds->get('gmail', 'client_secret', ''),
                accessToken: $creds->get('gmail', 'access_token', ''),
                refreshToken: $creds->get('gmail', 'refresh_token', ''),
                expiresAt: $creds->get('gmail', 'expires_at'),
                integrationId: 'gmail',
            );

            return new GmailService($client);
        });
    }

    public function boot(): void
    {
        if ($this->app->bound(ToolProviderRegistry::class)) {
            $registry = $this->app->make(ToolProviderRegistry::class);
            $registry->register(new GoogleCalendarToolProvider());
            $registry->register(new GmailToolProvider());
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::prefix('api/integrations/google/oauth')
            ->middleware('web')
            ->group(function () {
                Route::get('authorize', [GoogleOAuthController::class, 'authorize']);
                Route::get('callback', [GoogleOAuthController::class, 'callback']);
            });
    }
}
