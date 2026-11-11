<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\Support\PublicId;
use Cbox\Id\Client\Support\Scopes;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;

/**
 * Where the catalog's credentials live: sealed, server-side, never in the browser.
 *
 * The SESSION holds the grant, the cached catalog and the person's refresh token. The
 * CACHE holds a second copy of the newest rotated refresh token and grant, keyed by the
 * session id. That copy exists because a session is written back whole at the end of
 * every request: a page request that started before a renewal saves its older copy over
 * the rotated token, and the next renewal would present a spent token. Cbox ID treats
 * that replay as theft and revokes the whole family. Under the renewal lock the cache
 * copy is read first, so the token presented is always the newest one.
 *
 * Everything sits under one session key, so a logout forgets it in one call.
 */
final readonly class AppCatalogStore
{
    public const SESSION_KEY = 'cbox-id-client.app-catalog';

    public const SCOPE = 'account:apps:read';

    public function __construct(
        private Repository $cache,
        private string $issuer,
        private string $clientId,
        private int $ttl,
    ) {}

    /** The catalog resource: Cbox ID's own `/mcp`. */
    public function resource(): string
    {
        return rtrim($this->issuer, '/').'/mcp';
    }

    public function grant(Session $session, AppCatalogContext $context): ?ResourceAuthorizationResult
    {
        return $this->validGrant($session->get($this->bucket($context).'.grant'), $context);
    }

    public function rememberGrant(Session $session, ResourceAuthorizationResult $grant): void
    {
        $context = new AppCatalogContext($grant->subject, $grant->organizationId);
        $bucket = $this->bucket($context);
        $sealed = $grant->seal();

        $session->put($bucket.'.grant', $sealed);
        $session->forget($bucket.'.catalog');
        $this->cache->put($this->cacheKey($session, 'grant', $context), $sealed, $this->ttl);
    }

    /** The newest grant another request of this session renewed, if any. */
    public function sharedGrant(Session $session, AppCatalogContext $context): ?ResourceAuthorizationResult
    {
        return $this->validGrant($this->cache->get($this->cacheKey($session, 'grant', $context)), $context);
    }

    public function forgetGrant(Session $session, AppCatalogContext $context): void
    {
        $session->forget($this->bucket($context));
        $this->cache->forget($this->cacheKey($session, 'grant', $context));
    }

    /**
     * @return array{apps:list<array{key:string,name:string,description:?string,launch_url:?string,icon_url:?string,icon_dark_url:?string,purpose:?string,hue:?string,admission_url:?string,enabled:bool}>,revision:string,refresh_after:int}|null
     */
    public function catalog(Session $session, AppCatalogContext $context): ?array
    {
        $cached = $session->get($this->bucket($context).'.catalog');

        if (! is_array($cached) || ! is_int($cached['expires'] ?? null) || $cached['expires'] <= time()
            || ! is_array($cached['payload'] ?? null) || ! CatalogPayload::isCurrent($cached['payload'])) {
            return null;
        }

        /** @var array{apps:list<array{key:string,name:string,description:?string,launch_url:?string,icon_url:?string,icon_dark_url:?string,purpose:?string,hue:?string,admission_url:?string,enabled:bool}>,revision:string,refresh_after:int} $payload */
        $payload = $cached['payload'];

        return [...$payload, 'refresh_after' => max(1, $cached['expires'] - time())];
    }

    /**
     * @param  array{apps:list<array{key:string,name:string,description:?string,launch_url:?string,icon_url:?string,icon_dark_url:?string,purpose:?string,hue:?string,admission_url:?string,enabled:bool}>,revision:string,refresh_after:int}  $catalog
     */
    public function rememberCatalog(Session $session, AppCatalogContext $context, array $catalog, int $expires): void
    {
        $session->put($this->bucket($context).'.catalog', ['payload' => $catalog, 'expires' => $expires]);
    }

    public function forgetCatalog(Session $session, AppCatalogContext $context): void
    {
        $session->forget($this->bucket($context).'.catalog');
    }

    /**
     * The person's refresh token, newest copy first. Bound to the subject: a token kept
     * for someone else is never handed out.
     */
    public function refreshToken(Session $session, string $subject): ?StoredRefreshToken
    {
        $shared = $this->openRefresh($this->cache->get($this->cacheKey($session, 'refresh', null, $subject)), $subject);

        return $shared ?? $this->openRefresh($session->get(self::SESSION_KEY.'.refresh'), $subject);
    }

    /**
     * Keep a refresh token. `$shared` also writes the cache copy; the sign-in skips it,
     * because logging in gives the session a new id right after.
     */
    public function rememberRefreshToken(Session $session, StoredRefreshToken $token, bool $shared = true): void
    {
        $sealed = Crypt::encryptString(json_encode([
            'token' => $token->token, 'subject' => $token->subject, 'organization' => $token->organizationId,
            'issuer' => $this->issuer, 'client' => $this->clientId,
        ], JSON_THROW_ON_ERROR));

        $session->put(self::SESSION_KEY.'.refresh', $sealed);

        if ($shared) {
            $this->cache->put($this->cacheKey($session, 'refresh', null, $token->subject), $sealed, $this->ttl);
        }
    }

    public function forgetRefreshToken(Session $session, string $subject): void
    {
        $session->forget(self::SESSION_KEY.'.refresh');
        $this->cache->forget($this->cacheKey($session, 'refresh', null, $subject));
    }

    /**
     * The login access token kept for the first exchange, while it is valid for at
     * least another 30 seconds and belongs to this person and organization.
     */
    public function loginToken(Session $session, AppCatalogContext $context): ?StoredLoginToken
    {
        $sealed = $session->get(self::SESSION_KEY.'.login');

        if (! is_string($sealed)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($sealed), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($data) || ! is_string($data['token'] ?? null) || $data['token'] === ''
            || ($data['subject'] ?? null) !== $context->subject || ($data['organization'] ?? null) !== $context->organizationId
            || ! is_int($data['expires'] ?? null) || $data['expires'] <= time() + 30
            || ($data['issuer'] ?? null) !== $this->issuer || ($data['client'] ?? null) !== $this->clientId) {
            return null;
        }

        return new StoredLoginToken($data['token'], $context->subject, $context->organizationId, $data['expires']);
    }

    public function rememberLoginToken(Session $session, StoredLoginToken $token): void
    {
        $session->put(self::SESSION_KEY.'.login', Crypt::encryptString(json_encode([
            'token' => $token->token, 'subject' => $token->subject, 'organization' => $token->organizationId,
            'expires' => $token->expiresAt, 'issuer' => $this->issuer, 'client' => $this->clientId,
        ], JSON_THROW_ON_ERROR)));
    }

    public function forgetLoginToken(Session $session): void
    {
        $session->forget(self::SESSION_KEY.'.login');
    }

    /** Forget everything this session holds for the catalog (logout). */
    public function forgetAll(Session $session): void
    {
        $stored = $this->openRefresh($session->get(self::SESSION_KEY.'.refresh'), null);

        if ($stored !== null) {
            $this->cache->forget($this->cacheKey($session, 'refresh', null, $stored->subject));
        }

        $session->forget(self::SESSION_KEY);
    }

    /** The renewal lock's key: one per browser session, so two tabs renew one at a time. */
    public function lockKey(Session $session): string
    {
        return 'cbox-id-client:app-catalog:lock:'.hash('sha256', $session->getId());
    }

    private function validGrant(mixed $stored, AppCatalogContext $context): ?ResourceAuthorizationResult
    {
        $grant = is_string($stored) ? ResourceAuthorizationResult::open($stored) : null;

        if (! $grant instanceof ResourceAuthorizationResult || ! PublicId::same($grant->subject, $context->subject)
            || ! PublicId::same($grant->organizationId, $context->organizationId) || $grant->resource !== $this->resource()
            || count($grant->scopes) !== 1 || ! Scopes::holds($grant->scopes, self::SCOPE)) {
            return null;
        }

        return $grant;
    }

    private function openRefresh(mixed $sealed, ?string $subject): ?StoredRefreshToken
    {
        if (! is_string($sealed)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($sealed), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($data) || ! is_string($data['token'] ?? null) || $data['token'] === ''
            || ! is_string($data['subject'] ?? null) || ($subject !== null && $data['subject'] !== $subject)
            || ($data['issuer'] ?? null) !== $this->issuer || ($data['client'] ?? null) !== $this->clientId) {
            return null;
        }

        $organization = $data['organization'] ?? null;

        return new StoredRefreshToken($data['token'], $data['subject'], is_string($organization) ? $organization : null);
    }

    private function bucket(AppCatalogContext $context): string
    {
        return self::SESSION_KEY.'.grants.'.$this->hash([$context->subject, $context->organizationId]);
    }

    private function cacheKey(Session $session, string $kind, ?AppCatalogContext $context, ?string $subject = null): string
    {
        return 'cbox-id-client:app-catalog:'.$kind.':'.$this->hash($context instanceof AppCatalogContext
            ? [$session->getId(), $context->subject, $context->organizationId]
            : [$session->getId(), $subject, null]);
    }

    /** @param list<string|null> $parts */
    private function hash(array $parts): string
    {
        return hash('sha256', json_encode([$this->issuer, $this->clientId, ...$parts], JSON_THROW_ON_ERROR));
    }
}
