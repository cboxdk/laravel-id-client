<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\IdentityClient;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The person's catalog, read by the APP with its own machine token
 * (`app_catalog:resolve`, client credentials): `GET {issuer}/api/v1/app-catalog/for-user`.
 * Nothing per person is kept in the session, so there is no grant to lose, renew or
 * reconnect.
 *
 * Local first. Every answer is cached per (issuer, client, person, organization) in the
 * app's cache: fresh for `refresh_after` (at least a minute), and kept for a day as the
 * last good answer. When Cbox ID errors or is slow, that answer is served instead of an
 * error. Only a 404 (the person is no longer an active member) means a sign-in.
 *
 * @internal
 */
final readonly class MachineAppCatalog
{
    public const SCOPE = 'app_catalog:resolve';

    /** How long the last good answer is kept for when Cbox ID cannot answer. */
    public const STALE_FOR = 86400;

    /** The shortest time an answer is fresh for, whatever `refresh_after` says. */
    public const MIN_FRESH = 60;

    /** `refresh_after` on a stale answer: ask again soon. */
    public const STALE_REFRESH_AFTER = 30;

    /** After a refusal, the machine read is not tried again for this long. */
    public const REFUSED_FOR = 300;

    /** While the grant fallback was needed this recently, sign-ins prepare a grant again. */
    public const FALLBACK_FOR = 3600;

    public function __construct(
        private Factory $http,
        private IdentityClient $identity,
        private Repository $cache,
        private string $issuer,
        private string $clientId,
    ) {}

    /**
     * @return array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int}
     *
     * @throws SignInRequired when the person is no longer an active member of the organization
     * @throws CatalogUnavailable when Cbox ID cannot answer and there is no earlier answer
     * @throws MachineCatalogRefused when this client may not read catalogs with its own token
     */
    public function read(AppCatalogContext $context): array
    {
        $key = $this->key('catalog', [$context->subject, $context->organizationId]);
        $kept = $this->kept($key);

        if ($kept !== null && $kept['fresh_until'] > self::now()) {
            return [...$kept['payload'], 'refresh_after' => max(1, $kept['fresh_until'] - self::now())];
        }

        if ($this->cache->get($this->key('refused')) !== null) {
            throw MachineCatalogRefused::because('Cbox ID refused this client app_catalog:resolve a moment ago.');
        }

        try {
            $catalog = $this->fetch($context);
        } catch (SignInRequired $e) {
            $this->cache->forget($key);

            throw $e;
        } catch (MachineCatalogRefused $e) {
            $this->cache->put($this->key('refused'), self::now(), self::REFUSED_FOR);

            throw $e;
        } catch (CatalogUnavailable $e) {
            if ($kept === null) {
                throw $e;
            }

            Log::info('Cbox ID app catalog: serving the last good answer.', ['reason' => $e->getMessage()]);

            return [...$kept['payload'], 'refresh_after' => self::STALE_REFRESH_AFTER];
        }

        $fresh = max(self::MIN_FRESH, $catalog['refresh_after']);
        $this->cache->put($key, ['payload' => $catalog, 'fresh_until' => self::now() + $fresh], self::STALE_FOR);
        $this->cache->forget($this->key('fallback'));

        return [...$catalog, 'refresh_after' => $fresh];
    }

    /**
     * Record that this request fell back to the person's grant. Logs a warning at most
     * once an hour, and says so to {@see grantFallbackActive()} for the next hour.
     */
    public function noteFallback(string $reason): void
    {
        if ($this->cache->add($this->key('fallback'), self::now(), self::FALLBACK_FOR)) {
            Log::warning('Cbox ID app catalog: this client may not read catalogs with its own token yet; using the person\'s grant. Give the client the app_catalog:resolve scope.', ['reason' => $reason]);
        }
    }

    /** Whether the grant fallback was needed within the last hour. */
    public function grantFallbackActive(): bool
    {
        return $this->cache->get($this->key('fallback')) !== null;
    }

    /**
     * @return array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int}
     */
    private function fetch(AppCatalogContext $context, bool $retried = false): array
    {
        $response = $this->request($context, $this->token());

        if ($response->status() === 401 && ! $retried) {
            // The kept machine token was refused (revoked, or the keys rotated): one new one.
            $this->identity->forgetMachineToken([self::SCOPE]);

            return $this->fetch($context, true);
        }

        if ($response->status() === 404) {
            throw SignInRequired::because('The person is not an active member of the organization.');
        }

        if ($response->status() === 403 && self::scopeError($response)) {
            $this->identity->forgetMachineToken([self::SCOPE]);

            throw MachineCatalogRefused::because('Cbox ID answered insufficient_scope for the catalog.');
        }

        if (! $response->successful()) {
            throw CatalogUnavailable::because('Cbox ID answered '.$response->status().' for the catalog.');
        }

        try {
            return CatalogPayload::parse($response, $this->issuer);
        } catch (\RuntimeException $e) {
            throw CatalogUnavailable::because($e->getMessage());
        }
    }

    private function token(): string
    {
        try {
            return $this->identity->machineToken([self::SCOPE]);
        } catch (AuthenticationFailed $e) {
            if ($e->status !== null && $e->status >= 400 && $e->status < 500 && ! $e->isRateLimited()
                && in_array($e->error, ['invalid_scope', 'insufficient_scope', 'unauthorized_client'], true)) {
                throw MachineCatalogRefused::because('Cbox ID refused the app_catalog:resolve token ('.$e->error.').');
            }

            throw CatalogUnavailable::because('Cbox ID did not issue a catalog machine token.');
        } catch (Throwable) {
            // A connection failure, which carries the request (and the secret) with it.
            throw CatalogUnavailable::because('Cbox ID could not be reached for a catalog machine token.');
        }
    }

    private function request(AppCatalogContext $context, #[\SensitiveParameter] string $token): Response
    {
        $url = rtrim($this->issuer, '/').'/api/v1/app-catalog/for-user';

        try {
            return $this->http->acceptJson()->withToken($token)->withoutRedirecting()->connectTimeout(1)->timeout(2)
                ->get($url, ['user_id' => $context->subject, 'organization_id' => $context->organizationId]);
        } catch (Throwable) {
            // A timeout or connection failure; it carries the request (and the token).
            throw CatalogUnavailable::because('Cbox ID could not be reached for the catalog.');
        }
    }

    private static function scopeError(Response $response): bool
    {
        $error = $response->json('error');
        $challenge = (string) $response->header('WWW-Authenticate');

        return in_array($error, ['insufficient_scope', 'invalid_scope'], true)
            || preg_match('/error="?(insufficient_scope|invalid_scope)/', $challenge) === 1;
    }

    /**
     * @return array{payload: array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int}, fresh_until: int}|null
     */
    private function kept(string $key): ?array
    {
        $kept = $this->cache->get($key);

        if (! is_array($kept) || ! is_int($kept['fresh_until'] ?? null) || ! is_array($kept['payload'] ?? null)) {
            return null;
        }

        /** @var array{payload: array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int}, fresh_until: int} $kept */
        return $kept;
    }

    private static function now(): int
    {
        return Date::now()->getTimestamp();
    }

    /** @param list<string> $parts */
    private function key(string $kind, array $parts = []): string
    {
        return 'cbox-id-client:app-catalog:machine:'.$kind.':'.hash('sha256', json_encode([$this->issuer, $this->clientId, ...$parts], JSON_THROW_ON_ERROR));
    }
}
