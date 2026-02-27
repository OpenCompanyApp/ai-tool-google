<?php

namespace OpenCompany\AiToolGoogle;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OpenCompany\AiToolGoogle\Services\GmailService;
use OpenCompany\AiToolGoogle\Services\GoogleCalendarService;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;
use OpenCompany\AiToolGoogle\Services\GoogleDriveService;
use OpenCompany\AiToolGoogle\Services\GoogleSearchConsoleService;
use OpenCompany\AiToolGoogle\Services\GoogleSheetsService;
use OpenCompany\AiToolGoogle\Services\GoogleAnalyticsService;
use OpenCompany\AiToolGoogle\Services\GoogleDocsService;
use OpenCompany\AiToolGoogle\Services\GoogleFormsService;
use OpenCompany\AiToolGoogle\Services\GoogleTasksService;
use OpenCompany\IntegrationCore\Contracts\CredentialResolver;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class AiToolGoogleServiceProvider extends ServiceProvider
{
    /** @var array<int, string> */
    private const GOOGLE_INTEGRATIONS = [
        'google_calendar', 'gmail', 'google_drive',
        'google_contacts', 'google_sheets', 'google_search_console', 'google_tasks', 'google_analytics',
        'google_docs', 'google_forms',
    ];

    public function register(): void
    {
        $this->app->singleton(GoogleCalendarService::class, function ($app) {
            return new GoogleCalendarService($this->buildClient($app, 'google_calendar'));
        });

        $this->app->singleton(GmailService::class, function ($app) {
            return new GmailService($this->buildClient($app, 'gmail'));
        });

        $this->app->singleton(GoogleDriveService::class, function ($app) {
            return new GoogleDriveService($this->buildClient($app, 'google_drive'));
        });

        $this->app->singleton(GoogleContactsService::class, function ($app) {
            return new GoogleContactsService($this->buildClient($app, 'google_contacts'));
        });

        $this->app->singleton(GoogleSheetsService::class, function ($app) {
            return new GoogleSheetsService($this->buildClient($app, 'google_sheets'));
        });

        $this->app->singleton(GoogleSearchConsoleService::class, function ($app) {
            return new GoogleSearchConsoleService($this->buildClient($app, 'google_search_console'));
        });

        $this->app->singleton(GoogleTasksService::class, function ($app) {
            return new GoogleTasksService($this->buildClient($app, 'google_tasks'));
        });

        $this->app->singleton(GoogleAnalyticsService::class, function ($app) {
            return new GoogleAnalyticsService($this->buildClient($app, 'google_analytics'));
        });

        $this->app->singleton(GoogleDocsService::class, function ($app) {
            return new GoogleDocsService($this->buildClient($app, 'google_docs'));
        });

        $this->app->singleton(GoogleFormsService::class, function ($app) {
            return new GoogleFormsService($this->buildClient($app, 'google_forms'));
        });
    }

    private function buildClient(\Illuminate\Contracts\Foundation\Application $app, string $integration): GoogleClient
    {
        $creds = $app->make(CredentialResolver::class);

        return new GoogleClient(
            clientId: $this->resolveSharedCredential($creds, $integration, 'client_id'),
            clientSecret: $this->resolveSharedCredential($creds, $integration, 'client_secret'),
            accessToken: $creds->get($integration, 'access_token', ''),
            refreshToken: $creds->get($integration, 'refresh_token', ''),
            expiresAt: $creds->get($integration, 'expires_at'),
            integrationId: $integration,
        );
    }

    /**
     * Resolve a shared credential (client_id/client_secret) across all Google integrations.
     * Tries the target integration first, then falls back to any sibling that has it configured.
     */
    private function resolveSharedCredential(CredentialResolver $creds, string $integration, string $key): string
    {
        $value = $creds->get($integration, $key, '');
        if (! empty($value)) {
            return (string) $value;
        }

        foreach (self::GOOGLE_INTEGRATIONS as $sibling) {
            if ($sibling === $integration) {
                continue;
            }
            $value = $creds->get($sibling, $key, '');
            if (! empty($value)) {
                return (string) $value;
            }
        }

        return '';
    }

    public function boot(): void
    {
        if ($this->app->bound(ToolProviderRegistry::class)) {
            $registry = $this->app->make(ToolProviderRegistry::class);
            $registry->register(new GoogleCalendarToolProvider());
            $registry->register(new GmailToolProvider());
            $registry->register(new GoogleDriveToolProvider());
            $registry->register(new GoogleContactsToolProvider());
            $registry->register(new GoogleSheetsToolProvider());
            $registry->register(new GoogleSearchConsoleToolProvider());
            $registry->register(new GoogleTasksToolProvider());
            $registry->register(new GoogleAnalyticsToolProvider());
            $registry->register(new GoogleDocsToolProvider());
            $registry->register(new GoogleFormsToolProvider());
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::prefix('api/integrations/google/oauth')
            ->middleware(['web', 'auth'])
            ->group(function () {
                Route::get('authorize', [GoogleOAuthController::class, 'authorize']);
                Route::get('callback', [GoogleOAuthController::class, 'callback']);
            });
    }
}
