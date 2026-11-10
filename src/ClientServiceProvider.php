<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\ApiKeys\ApiKeyVerifier;
use Cbox\Id\Client\AppCatalog\AppCatalog;
use Cbox\Id\Client\AppCatalog\AppCatalogStore;
use Cbox\Id\Client\AppCatalog\CatalogGrantExchange;
use Cbox\Id\Client\AppCatalog\SessionAppCatalogContext;
use Cbox\Id\Client\Authz\HttpPermissionDecisions;
use Cbox\Id\Client\Authz\ManifestPublisher;
use Cbox\Id\Client\BackchannelLogout\LogoutTokenVerifier;
use Cbox\Id\Client\BackchannelLogout\SessionRegistry;
use Cbox\Id\Client\Console\PublishManifestCommand;
use Cbox\Id\Client\Contracts\BackchannelAuthentication;
use Cbox\Id\Client\Contracts\DeviceAuthorization;
use Cbox\Id\Client\Contracts\HasFeatureFlags;
use Cbox\Id\Client\Contracts\KeptPermissionDecisions;
use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Contracts\PermissionDecisions;
use Cbox\Id\Client\Contracts\ResolvesAppCatalogContext;
use Cbox\Id\Client\Contracts\ServiceConnections;
use Cbox\Id\Client\Contracts\UserProperties;
use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Decisions\KeptDecisions;
use Cbox\Id\Client\Events\UserPropertiesUpdated;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Frontend\FrontendClient;
use Cbox\Id\Client\Grants\AuthorizationServerMetadata;
use Cbox\Id\Client\Grants\ClientAuthentication;
use Cbox\Id\Client\Grants\HttpBackchannelAuthentication;
use Cbox\Id\Client\Grants\HttpDeviceAuthorization;
use Cbox\Id\Client\Grants\SystemTimer;
use Cbox\Id\Client\Grants\Timer;
use Cbox\Id\Client\Http\BackchannelLogoutController;
use Cbox\Id\Client\Http\EnforceBackchannelLogout;
use Cbox\Id\Client\Http\RequireConfiguredIdentity;
use Cbox\Id\Client\Http\RequireFeature;
use Cbox\Id\Client\Http\RequireOrganization;
use Cbox\Id\Client\Http\RequirePermission;
use Cbox\Id\Client\Http\VerifyAccessToken;
use Cbox\Id\Client\Http\VerifyApiKey;
use Cbox\Id\Client\Http\WebhookController;
use Cbox\Id\Client\Management\AuditLogs\AuditLogger;
use Cbox\Id\Client\Management\EnvironmentClient;
use Cbox\Id\Client\Management\HttpManagementClient;
use Cbox\Id\Client\Management\ManagementClients;
use Cbox\Id\Client\Management\WorkspaceClient;
use Cbox\Id\Client\ServiceConnections\HttpServiceConnections;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Support\InternalRoutes;
use Cbox\Id\Client\Support\MachineTokenCache;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\Tenancy\PermissionGate;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\UserProperties\HttpUserProperties;
use Cbox\Id\Client\Webhooks\EventType;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use Cbox\Id\Client\Webhooks\WebhookHandlers;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Throwable;

class ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cbox-id-client.php', 'cbox-id-client');

        // Shared registry — the app registers provisioning hooks against it, the
        // webhook controller dispatches to it. Singleton so both see the same handlers.
        $this->app->singleton(WebhookHandlers::class);
        $this->app->singleton(PermissionDecisions::class, static fn (): HttpPermissionDecisions => new HttpPermissionDecisions(
            self::configString('cbox-id-client.issuer'),
            self::configString('cbox-id-client.client_id'),
            self::configInt('cbox-id-client.http_timeout', 10),
        ));

        // Kept decisions resolve their collaborators each time, so a test that swaps the
        // decisions client or the identity client (CboxId::fake()) reaches them.
        $this->app->bind(KeptPermissionDecisions::class, KeptDecisions::class);
        $this->app->bind(KeptDecisions::class, static function (Application $app): KeptDecisions {
            $store = self::configString('cbox-id-client.decisions.store');
            $connection = self::configString('cbox-id-client.decisions.queue_connection');
            $queue = self::configString('cbox-id-client.decisions.queue');

            return new KeptDecisions(
                $app->make(PermissionDecisions::class),
                $app->make(IdentityClient::class),
                Cache::store($store !== '' ? $store : null),
                $app,
                self::configString('cbox-id-client.client_id'),
                self::configInt('cbox-id-client.decisions.fresh_for', 30),
                self::configInt('cbox-id-client.decisions.max_age', 86_400),
                $connection !== '' ? $connection : null,
                $queue !== '' ? $queue : null,
            );
        });

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

        $this->app->singleton(ServiceConnections::class, static fn (Container $app): HttpServiceConnections => new HttpServiceConnections(
            $app->make(Factory::class), $app->make(IdentityClient::class), self::configString('cbox-id-client.issuer'), self::configString('cbox-id-client.client_id'),
        ));

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

            return new IdentityClient(
                $config,
                new Discovery($issuer, $cacheTtl, $timeout),
                $app->make(SessionIdentityStore::class),
                self::machineTokenCache($app),
            );
        });

        $this->app->singleton(ResourceAuthorizationClient::class, static function (Container $app): ResourceAuthorizationClient {
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

            return new ResourceAuthorizationClient($config, new Discovery($issuer, $cacheTtl, $timeout));
        });

        $this->app->bind(PersonalAppCatalog::class, static fn () => new PersonalAppCatalog(app(Factory::class), self::configString('cbox-id-client.issuer')));
        $this->registerAppCatalog();
        $this->app->bind(UserProperties::class, static fn () => new HttpUserProperties(
            app(Factory::class), self::configString('cbox-id-client.issuer'), self::configInt('cbox-id-client.http_timeout', 10),
        ));

        // CIBA and the device grant. Bound, not singletons, so the HTTP factory and the
        // timer are resolved when the grant is — which is what lets a test swap either.
        $this->app->bind(Timer::class, SystemTimer::class);
        $this->app->bind(BackchannelAuthentication::class, static fn (Container $app): HttpBackchannelAuthentication => new HttpBackchannelAuthentication(
            ...self::pollingGrantArguments($app),
        ));
        $this->app->bind(DeviceAuthorization::class, static fn (Container $app): HttpDeviceAuthorization => new HttpDeviceAuthorization(
            ...self::pollingGrantArguments($app),
        ));

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

        // The typed management clients, generated from the planes' OpenAPI documents.
        // Like the client above, resolvable without a key: the first use without one is a
        // NotConfigured naming the variable.
        $this->app->singleton(ManagementClients::class, static function (Container $app): ManagementClients {
            $config = config('cbox-id-client.management');
            $management = [];

            foreach (is_array($config) ? $config : [] as $key => $value) {
                $management[(string) $key] = $value;
            }

            return new ManagementClients($management, self::configString('cbox-id-client.issuer'), $app->make(Dispatcher::class));
        });
        $this->app->bind(EnvironmentClient::class, static fn (Container $app): EnvironmentClient => $app->make(ManagementClients::class)->environment());
        $this->app->bind(WorkspaceClient::class, static fn (Container $app): WorkspaceClient => $app->make(ManagementClients::class)->workspace());
        $this->app->bind(AuditLogger::class, static fn (Container $app): AuditLogger => $app->make(ManagementClients::class)->auditLogger());

        // Back-channel logout. The cache must be one every web server shares: it holds the
        // replay cache, the session index and the revocation list.
        $this->app->singleton(SessionRegistry::class, static function (): SessionRegistry {
            $store = self::configString('cbox-id-client.backchannel_logout.cache_store');
            $remember = self::configString('cbox-id-client.backchannel_logout.remember_tokens');

            return new SessionRegistry(
                Cache::store($store !== '' ? $store : null),
                // The session lifetime (minutes), plus the logout token's own life: an
                // older local session has expired anyway.
                self::configInt('session.lifetime', 120) * 60 + 300,
                config('cbox-id-client.backchannel_logout.destroy_sessions', true) !== false,
                in_array($remember, ['subject', 'always', 'never'], true) ? $remember : 'subject',
            );
        });

        $this->app->singleton(LogoutTokenVerifier::class, static function (): LogoutTokenVerifier {
            $issuer = self::configString('cbox-id-client.issuer');
            $store = self::configString('cbox-id-client.backchannel_logout.cache_store');

            return new LogoutTokenVerifier(
                new Discovery($issuer, self::configInt('cbox-id-client.cache_ttl', 3600), self::configInt('cbox-id-client.http_timeout', 10)),
                $issuer,
                self::configString('cbox-id-client.client_id'),
                Cache::store($store !== '' ? $store : null),
                self::configInt('cbox-id-client.backchannel_logout.max_age', 300),
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
        $this->registerInternalRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([PublishManifestCommand::class]);

            $this->publishes([
                __DIR__.'/../config/cbox-id-client.php' => config_path('cbox-id-client.php'),
            ], 'cbox-id-client-config');
        }

        // Register through the existing queue path; applications need only a Laravel listener.
        foreach ([EventType::UserPreferencesUpdated, EventType::UserMetadataUpdated] as $type) {
            $this->app->make(WebhookHandlers::class)->on($type, static function (WebhookEvent $webhook): void {
                Event::dispatch(UserPropertiesUpdated::fromWebhook($webhook, self::configString('cbox-id-client.issuer')));
            });
        }

        $this->registerWebhookRoute();

        // Audit events buffered during the request go out after the response is sent. A
        // failure is reported, never thrown at a response that has already left.
        $this->app->terminating(function (): void {
            $clients = $this->app->make(ManagementClients::class);

            if (! $clients->hasAuditLogger()) {
                return;
            }

            try {
                $clients->auditLogger()->flush();
            } catch (Throwable $e) {
                report($e);
            }
        });

        // Aliased so a route reads `cbox-id.token:tax.quote` and states its own
        // requirement where anyone reading the route can see it.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('cbox-id.token', VerifyAccessToken::class);
        $router->aliasMiddleware('cbox-id.api-key', VerifyApiKey::class);
        $router->aliasMiddleware('cbox-id.org', RequireOrganization::class);
        $router->aliasMiddleware('cbox-id.permission', RequirePermission::class);
        $router->aliasMiddleware('cbox-id.configured', RequireConfiguredIdentity::class);
        $router->aliasMiddleware('cbox-id.session', EnforceBackchannelLogout::class);
        $router->aliasMiddleware('cbox-id.feature', RequireFeature::class);

        $this->registerFeatureFlagShortcuts($router);

        $this->registerBackchannelLogout($router);

        $this->bindSessionIdentityToLocalLogin();

        if (config('cbox-id-client.authorization.gate') === true) {
            PermissionGate::register($this->app->make(Gate::class), $this->app->make(CurrentPrincipal::class));
        }
    }

    /**
     * The app switcher's catalog. Bound, not singletons: each resolves the HTTP factory,
     * the identity client and the cache when it is used, so a test can swap any of them.
     */
    private function registerAppCatalog(): void
    {
        $this->app->bind(ResolvesAppCatalogContext::class, SessionAppCatalogContext::class);

        $this->app->bind(AppCatalogStore::class, static fn (): AppCatalogStore => new AppCatalogStore(
            self::appCatalogCache()['repository'],
            self::configString('cbox-id-client.issuer'),
            self::configString('cbox-id-client.client_id'),
            self::configInt('session.lifetime', 120) * 60,
        ));

        $this->app->bind(CatalogGrantExchange::class, static fn (Container $app): CatalogGrantExchange => new CatalogGrantExchange(
            $app->make(Factory::class),
            new Discovery(self::configString('cbox-id-client.issuer'), self::configInt('cbox-id-client.cache_ttl', 3600), self::configInt('cbox-id-client.http_timeout', 10)),
            self::configString('cbox-id-client.issuer'),
            self::configString('cbox-id-client.client_id'),
            self::configString('cbox-id-client.client_secret'),
        ));

        $this->app->bind(AppCatalog::class, static fn (Container $app): AppCatalog => new AppCatalog(
            $app->make(AppCatalogStore::class),
            $app->make(CatalogGrantExchange::class),
            $app->make(PersonalAppCatalog::class),
            $app->make(IdentityClient::class),
            self::appCatalogCache()['locks'],
            max(0, self::configInt('cbox-id-client.app_catalog.renew_before', 300)),
        ));
    }

    /**
     * The cache the catalog keeps its newest tokens and its renewal lock in.
     *
     * @return array{repository: Repository, locks: LockProvider}
     */
    private static function appCatalogCache(): array
    {
        $name = self::configString('cbox-id-client.app_catalog.cache_store');
        $repository = Cache::store($name !== '' ? $name : null);
        $store = $repository->getStore();

        if (! $store instanceof LockProvider) {
            throw ClientConfigurationException::because(
                'The app catalog needs a cache store with locks (redis, database, memcached, file or array). '.
                'Set CBOX_APP_CATALOG_CACHE_STORE to one.',
            );
        }

        return ['repository' => $repository, 'locks' => $store];
    }

    /**
     * `CBOX_INTERNAL_ROUTES`: HTTPS calls to the listed public hosts go to their
     * in-cluster address instead. Registered on the application's HTTP client factory,
     * so it covers this package's calls and the application's own `Http::` calls, and
     * only once a request is being sent: everything above the transport still sees the
     * public URL. Empty, nothing is registered.
     */
    private function registerInternalRoutes(): void
    {
        $routes = InternalRoutes::fromConfig(
            config('cbox-id-client.internal_routes'),
            allowLoopback: $this->app->environment('testing'),
        );

        foreach ($routes->rejected as $reason) {
            Log::warning('Cbox internal route ignored: '.$reason);
        }

        $this->app->instance(InternalRoutes::class, $routes);

        if ($routes->isEmpty()) {
            return;
        }

        $this->callAfterResolving(Factory::class, static function (Factory $http) use ($routes): void {
            $http->globalRequestMiddleware($routes->rewrite(...));
        });
    }

    /**
     * `feature:key` and `@feature('key')`, under the names configured. The short alias is
     * only taken when the application has not already taken it: an alias registered here
     * at boot would otherwise silently replace the application's own.
     */
    private function registerFeatureFlagShortcuts(Router $router): void
    {
        $alias = config('cbox-id-client.feature_flags.middleware', 'feature');

        if (is_string($alias) && $alias !== '' && ! array_key_exists($alias, $router->getMiddleware())) {
            $router->aliasMiddleware($alias, RequireFeature::class);
        }

        $directive = config('cbox-id-client.feature_flags.blade', 'feature');

        if (! is_string($directive) || $directive === '') {
            return;
        }

        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade) use ($directive): void {
            $blade->if($directive, function (string $key): bool {
                $principal = $this->app->make(CurrentPrincipal::class)->resolve(auth()->user(), request());

                return $principal instanceof HasFeatureFlags && $principal->hasFeature($key);
            });
        });
    }

    /**
     * Opt-in: the receiver route, and the enforcement middleware in the `web` group so a
     * browser whose session was ended is signed out on its next request.
     *
     * The route is a bare POST outside the `web` group — no session, no CSRF check,
     * because the caller is Cbox ID's server and the token's signature is its
     * authentication. (Excluding a `web` route from CSRF with `withoutMiddleware()` does
     * nothing on Laravel 11+, which is why it is not mounted that way.)
     */
    private function registerBackchannelLogout(Router $router): void
    {
        if (config('cbox-id-client.backchannel_logout.enabled') !== true) {
            return;
        }

        $path = self::configString('cbox-id-client.backchannel_logout.path');

        Route::post($path !== '' ? $path : '/cbox-id/backchannel-logout', BackchannelLogoutController::class)
            ->name('cbox-id.backchannel-logout');

        // Through the HTTP kernel, not the router: the kernel owns the groups and writes
        // them to the router when it is built, so a group pushed onto the router directly
        // is overwritten whenever the kernel is constructed after this provider boots.
        $kernel = $this->httpKernel();

        if ($kernel instanceof FoundationHttpKernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
            $kernel->appendMiddlewareToGroup('web', EnforceBackchannelLogout::class);

            return;
        }

        $router->pushMiddlewareToGroup('web', EnforceBackchannelLogout::class);
    }

    /** The HTTP kernel, as its contract: an application may bind its own. */
    private function httpKernel(): HttpKernel
    {
        return $this->app->make(HttpKernel::class);
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

            // The catalog's grant and the person's refresh token leave with them.
            $session = $this->app->bound('session.store') ? $this->app->make('session.store') : null;

            if ($session instanceof Session && $session->has(AppCatalogStore::SESSION_KEY)) {
                try {
                    $this->app->make(AppCatalog::class)->forget($session);
                } catch (Throwable $e) {
                    $session->forget(AppCatalogStore::SESSION_KEY);
                    report($e);
                }
            }
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

    /**
     * The shared constructor arguments of the CIBA and device grants, from config.
     *
     * @return array{Factory, AuthorizationServerMetadata, ClientAuthentication, Timer, int}
     */
    private static function pollingGrantArguments(Container $app): array
    {
        $http = $app->make(Factory::class);
        $timeout = self::configInt('cbox-id-client.http_timeout', 10);
        $raw = config('cbox-id-client');
        $config = [];

        foreach (is_array($raw) ? $raw : [] as $key => $value) {
            $config[(string) $key] = $value;
        }

        return [
            $http,
            new AuthorizationServerMetadata($http, self::configString('cbox-id-client.issuer'), $timeout),
            ClientAuthentication::fromConfig($config),
            $app->make(Timer::class),
            $timeout,
        ];
    }

    /**
     * The cache `machineToken()` keeps its tokens in, or null when it is switched off.
     * It also stays off without an app key, because the token is only ever stored
     * encrypted, and an app without a key should still be able to sign people in.
     */
    private static function machineTokenCache(Container $app): ?MachineTokenCache
    {
        if (filter_var(config('cbox-id-client.token_cache.enabled', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false
            || self::configString('app.key') === ''
            || ! $app->bound('cache')
            || ! $app->bound('encrypter')) {
            return null;
        }

        $store = self::configString('cbox-id-client.token_cache.store');

        return new MachineTokenCache(Cache::store($store !== '' ? $store : null), $app->make(StringEncrypter::class));
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
