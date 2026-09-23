<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\ApiKeys\ApiKeyVerifier;
use Cbox\Id\Client\Authz\ManifestPublisher;
use Cbox\Id\Client\Console\PublishManifestCommand;
use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Frontend\FrontendClient;
use Cbox\Id\Client\Http\RequireConfiguredIdentity;
use Cbox\Id\Client\Http\RequireOrganization;
use Cbox\Id\Client\Http\RequirePermission;
use Cbox\Id\Client\Http\VerifyAccessToken;
use Cbox\Id\Client\Http\VerifyApiKey;
use Cbox\Id\Client\Http\WebhookController;
use Cbox\Id\Client\Management\HttpManagementClient;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\Tenancy\PermissionGate;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Webhooks\WebhookHandlers;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cbox-id-client.php', 'cbox-id-client');

        // Shared registry — the app registers provisioning hooks against it, the
        // webhook controller dispatches to it. Singleton so both see the same handlers.
        $this->app->singleton(WebhookHandlers::class);

        $this->app->singleton(ManifestPublisher::class, static function (): ManifestPublisher {
            $issuer = self::configString('cbox-id-client.issuer');
            $cacheTtl = self::configInt('cbox-id-client.cache_ttl', 3600);
            $timeout = self::configInt('cbox-id-client.http_timeout', 10);

            return new ManifestPublisher(
                new Discovery($issuer, $cacheTtl, $timeout),
                $issuer,
                self::configString('cbox-id-client.client_id'),
                self::configString('cbox-id-client.client_secret'),
                self::configListOfArrays('cbox-id-client.authz.permissions'),
                self::configListOfArrays('cbox-id-client.authz.roles'),
                $timeout,
            );
        });

        $this->app->singleton(SessionIdentityStore::class);
        $this->app->singleton(CurrentPrincipal::class);

        $this->app->singleton(IdentityClient::class, static function (Container $app): IdentityClient {
            $raw = config('cbox-id-client');
            $config = [];

            if (is_array($raw)) {
                foreach ($raw as $key => $value) {
                    if (is_string($key)) {
                        $config[$key] = $value;
                    }
                }
            }

            $issuer = is_string($config['issuer'] ?? null) ? $config['issuer'] : '';
            $cacheTtl = is_numeric($config['cache_ttl'] ?? null) ? (int) $config['cache_ttl'] : 3600;
            $timeout = is_numeric($config['http_timeout'] ?? null) ? (int) $config['http_timeout'] : 10;

            return new IdentityClient($config, new Discovery($issuer, $cacheTtl, $timeout), $app->make(SessionIdentityStore::class));
        });

        // The environment management API. Resolvable without a key so an application
        // that never provisions boots fine; the first call without one is refused with a
        // NotConfigured that names CBOX_ID_MANAGEMENT_KEY.
        $this->app->singleton(Management::class, static function (): Management {
            $url = self::configString('cbox-id-client.management.url');
            $issuer = self::configString('cbox-id-client.issuer');

            return new HttpManagementClient(
                rtrim($url !== '' ? $url : ($issuer !== '' ? rtrim($issuer, '/').'/api/v1' : ''), '/'),
                self::configString('cbox-id-client.management.key'),
                self::configInt('cbox-id-client.http_timeout', 10),
            );
        });

        $this->app->singleton(VerifiesApiKeys::class, static fn (): VerifiesApiKeys => new ApiKeyVerifier(
            self::configString('cbox-id-client.issuer'),
            self::configString('cbox-id-client.client_id'),
            self::configString('cbox-id-client.client_secret'),
            self::configInt('cbox-id-client.api_keys.cache_ttl', 60),
            self::configInt('cbox-id-client.http_timeout', 10),
        ));

        // Verifying a token presented TO this application, as opposed to one minted
        // for it at the end of a login. The audience is the API's own resource
        // identifier and defaults to the issuer, because Cbox ID mints a token with
        // the issuer as `aud` when no resource was requested (RFC 9068 §2.2) — so an
        // application that has not thought about resources still verifies rather than
        // rejecting everything.
        $this->app->singleton(AccessTokenVerifier::class, static function (): AccessTokenVerifier {
            $issuer = is_string(config('cbox-id-client.issuer')) ? config('cbox-id-client.issuer') : '';
            $audience = config('cbox-id-client.audience');
            $cacheTtl = is_numeric(config('cbox-id-client.cache_ttl')) ? (int) config('cbox-id-client.cache_ttl') : 3600;
            $timeout = is_numeric(config('cbox-id-client.http_timeout')) ? (int) config('cbox-id-client.http_timeout') : 10;

            return new AccessTokenVerifier(
                new Discovery($issuer, $cacheTtl, $timeout),
                $issuer,
                is_string($audience) && $audience !== '' ? $audience : $issuer,
            );
        });

        // The browser-facing channel, resolvable only when a publishable key is
        // configured. Bound rather than always-constructed: the key is optional — most
        // applications never draw their own sign-in box — and a container entry that
        // throws on resolution is friendlier than one that fails at a call site.
        $this->app->singleton(FrontendClient::class, static function (): FrontendClient {
            $issuer = config('cbox-id-client.issuer');
            $key = config('cbox-id-client.publishable_key');

            if (! is_string($key) || $key === '') {
                throw ClientConfigurationException::because(
                    'No Cbox ID publishable key is configured. Set CBOX_ID_PUBLISHABLE_KEY to the '.
                    'pk_test_… or pk_live_… value from the console (Developers → Frontend keys).',
                );
            }

            $cacheTtl = config('cbox-id-client.frontend_cache_ttl');
            $timeout = config('cbox-id-client.http_timeout');

            return new FrontendClient(
                is_string($issuer) ? $issuer : '',
                $key,
                is_numeric($cacheTtl) ? (int) $cacheTtl : 60,
                is_numeric($timeout) ? (int) $timeout : 10,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PublishManifestCommand::class]);

            $this->publishes([
                __DIR__.'/../config/cbox-id-client.php' => config_path('cbox-id-client.php'),
            ], 'cbox-id-client-config');
        }

        $this->registerWebhookRoute();

        // Aliased so a route reads `cbox-id.token:tax.quote` and states its own
        // requirement where anyone reading the route can see it.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('cbox-id.token', VerifyAccessToken::class);
        $router->aliasMiddleware('cbox-id.api-key', VerifyApiKey::class);
        $router->aliasMiddleware('cbox-id.org', RequireOrganization::class);
        $router->aliasMiddleware('cbox-id.permission', RequirePermission::class);
        $router->aliasMiddleware('cbox-id.configured', RequireConfiguredIdentity::class);

        $this->bindSessionIdentityToLocalLogin();

        if (config('cbox-id-client.authorization.gate') === true) {
            PermissionGate::register($this->app->make(Gate::class), $this->app->make(CurrentPrincipal::class));
        }
    }

    /**
     * The identity remembered at sign-in answers only for the local user who was logged
     * in with it: bound on `Login`, forgotten on `Logout` or when somebody else logs in.
     * See {@see SessionIdentityStore}.
     */
    private function bindSessionIdentityToLocalLogin(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $this->app->make(SessionIdentityStore::class)->bindTo($event->user);
        });

        Event::listen(Logout::class, function (): void {
            $this->app->make(SessionIdentityStore::class)->forget();
        });
    }

    /**
     * Mount the webhook receiver at the configured path (default `/cbox-id/webhooks`),
     * unless disabled. A bare POST route — deliberately outside the `web` group, so it
     * carries no session/CSRF; the HMAC signature is its authentication.
     */
    private function registerWebhookRoute(): void
    {
        if (config('cbox-id-client.webhooks.route', true) !== true) {
            return;
        }

        $path = config('cbox-id-client.webhooks.path');

        if (! is_string($path) || $path === '') {
            return;
        }

        Route::post($path, WebhookController::class)->name('cbox-id.webhooks');
    }

    private static function configString(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    private static function configInt(string $key, int $default): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function configListOfArrays(string $key): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $normalized = [];
                foreach ($entry as $k => $v) {
                    if (is_string($k)) {
                        $normalized[$k] = $v;
                    }
                }
                $list[] = $normalized;
            }
        }

        return $list;
    }
}
