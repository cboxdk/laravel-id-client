<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\PersonalAppCatalog;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Session\Session;
use Throwable;

/**
 * The app switcher's catalog, for one browser session.
 *
 * At sign-in, {@see rememberSignIn()} only seals the login access token and the person's
 * refresh token (when the login got one) into the session: no network call. {@see read()}
 * serves the catalog and gets the grant on the server when it is missing or about to
 * expire: exchange the login token while it lives, otherwise refresh the person's tokens
 * and exchange the new one. Nobody clicks anything. When that is impossible,
 * {@see SignInRequired} says so and the route answers with where to sign in.
 *
 * Renewal runs under a cache lock per session, so two tabs never present the same
 * refresh token: Cbox ID rotates it on every use and revokes the family on a replay.
 */
final readonly class AppCatalog
{
    public function __construct(
        private AppCatalogStore $store,
        private CatalogGrantExchange $exchange,
        private PersonalAppCatalog $catalog,
        private IdentityClient $identity,
        private LockProvider $locks,
        private int $renewBefore = 300,
        private int $lockWait = 10,
    ) {}

    /**
     * Keep what the catalog will need from a sign-in that just completed: the login
     * access token (until it expires) and the refresh token, when there is one, both
     * sealed in the session. No network call: a callback, an SSO hop included, does the
     * code exchange and nothing else. The first catalog read exchanges the login token.
     *
     * Call it only with the identity THIS request's callback verified. Never throws.
     * Returns whether anything was kept.
     */
    public function rememberSignIn(Session $session, CboxUser $identity): bool
    {
        if (self::cookieSessions() || $identity->id === '' || $identity->accessToken === ''
            || array_key_exists('act', $identity->claims)) {
            return false;
        }

        // A new sign-in starts a new token family; the old one is not ours any more.
        $this->store->forgetRefreshToken($session, $identity->id);
        $this->store->forgetLoginToken($session);

        $organization = $identity->organizationId !== null && $identity->organizationId !== '' ? $identity->organizationId : null;
        $kept = false;

        if (is_string($identity->refreshToken) && $identity->refreshToken !== '') {
            $this->store->rememberRefreshToken($session, new StoredRefreshToken(
                $identity->refreshToken, $identity->id, $organization,
            ), shared: false);
            $kept = true;
        }

        if ($organization !== null) {
            // An unknown lifetime is taken as one minute, never as "forever".
            $lifetime = $identity->expiresIn > 0 ? $identity->expiresIn : 60;
            $this->store->rememberLoginToken($session, new StoredLoginToken(
                $identity->accessToken, $identity->id, $organization, time() + $lifetime,
            ));
            $kept = true;
        }

        return $kept;
    }

    /**
     * The person's catalog, in Cbox ID's order.
     *
     * @return array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int}
     *
     * @throws SignInRequired when only a new sign-in can get a grant
     * @throws CatalogUnavailable when Cbox ID cannot answer right now
     */
    public function read(Session $session, AppCatalogContext $context): array
    {
        if (self::cookieSessions()) {
            throw CatalogUnavailable::because('The app catalog needs a server-side session driver; the cookie driver would put its credentials in the browser.');
        }

        $grant = $this->usableGrant($session, $context);
        $cached = $this->store->catalog($session, $context);

        if ($cached !== null) {
            return [...$cached, 'refresh_after' => $this->refreshAfter($cached['refresh_after'], $grant)];
        }

        try {
            return $this->fetch($session, $context, $grant);
        } catch (SignInRequired) {
            // ID refused a grant it issued (revoked, or the person left the team): one
            // renewal, then the answer stands.
            $this->store->forgetGrant($session, $context);

            return $this->fetch($session, $context, $this->renew($session, $context, null));
        }
    }

    /** Forget everything this session holds for the catalog. */
    public function forget(Session $session): void
    {
        $this->store->forgetAll($session);
    }

    /** A grant that lives past the renewal window, renewing it first if it does not. */
    private function usableGrant(Session $session, AppCatalogContext $context): ResourceAuthorizationResult
    {
        $grant = $this->store->grant($session, $context);

        if ($grant instanceof ResourceAuthorizationResult && $grant->expiresAt - time() > $this->renewBefore) {
            return $grant;
        }

        return $this->renew($session, $context, $grant);
    }

    /**
     * @throws SignInRequired
     * @throws CatalogUnavailable
     */
    private function renew(Session $session, AppCatalogContext $context, ?ResourceAuthorizationResult $current): ResourceAuthorizationResult
    {
        try {
            $grant = $this->locks->lock($this->store->lockKey($session), 30)
                ->block($this->lockWait, fn (): ResourceAuthorizationResult => $this->renewLocked($session, $context));

            if (! $grant instanceof ResourceAuthorizationResult) {
                throw CatalogUnavailable::because('The catalog grant could not be renewed.');
            }

            return $grant;
        } catch (SignInRequired|CatalogUnavailable $e) {
            // Not expired yet: keep using it, and try again on the next read.
            if ($current instanceof ResourceAuthorizationResult && $current->expiresAt > time() + 5) {
                return $current;
            }

            throw $e;
        } catch (LockTimeoutException) {
            if ($current instanceof ResourceAuthorizationResult && $current->expiresAt > time() + 5) {
                return $current;
            }

            throw CatalogUnavailable::because('Another request of this session is renewing the catalog grant.');
        }
    }

    private function renewLocked(Session $session, AppCatalogContext $context): ResourceAuthorizationResult
    {
        // Another tab may have renewed while this request waited for the lock; its
        // session write is not visible here, the cache copy is.
        $shared = $this->store->sharedGrant($session, $context);

        if ($shared instanceof ResourceAuthorizationResult && $shared->expiresAt - time() > $this->renewBefore) {
            $this->store->rememberGrant($session, $shared);

            return $shared;
        }

        // First read after sign-in: the login token is still good, so exchange it and
        // drop it. It is used once; a refused or expired one falls through to refresh.
        $login = $this->store->loginToken($session, $context);

        if ($login instanceof StoredLoginToken) {
            try {
                $grant = $this->exchange->exchange($login->token, $context);
                $this->store->forgetLoginToken($session);
                $this->store->rememberGrant($session, $grant);

                return $grant;
            } catch (SignInRequired) {
                $this->store->forgetLoginToken($session);
            }
        }

        $stored = $this->store->refreshToken($session, $context->subject);

        if ($stored === null) {
            throw SignInRequired::because('No refresh token is kept for this session.');
        }

        // A refreshed token acts for the organization the login was bound to, never
        // another one: do not spend the refresh token on a grant that cannot match.
        if ($stored->organizationId !== null && $stored->organizationId !== $context->organizationId) {
            throw SignInRequired::because('The kept refresh token belongs to another organization.');
        }

        try {
            $refreshed = $this->identity->refresh($stored->token);
        } catch (AuthenticationFailed $e) {
            if ($e->status === null || $e->status >= 500 || $e->isRateLimited()) {
                // A transport failure or an ID outage: the same token is good in a moment.
                throw CatalogUnavailable::because('Cbox ID could not refresh the session right now.');
            }

            $this->store->forgetRefreshToken($session, $context->subject);

            throw SignInRequired::because('Cbox ID refused the refresh token.');
        } catch (Throwable) {
            // A connection failure, which carries the request (and the token) with it.
            throw CatalogUnavailable::because('Cbox ID could not be reached to refresh the session.');
        }

        $subject = $refreshed->claims['sub'] ?? null;
        $organization = $refreshed->claims['org'] ?? $stored->organizationId;

        // Persist the rotated token before anything else can fail: the old one is spent.
        $this->store->rememberRefreshToken($session, new StoredRefreshToken(
            $refreshed->refreshToken, $context->subject, is_string($organization) ? $organization : null,
        ));

        if ($subject !== null && $subject !== $context->subject) {
            $this->store->forgetRefreshToken($session, $context->subject);

            throw SignInRequired::because('The refreshed tokens are for a different person.');
        }

        $grant = $this->exchange->exchange($refreshed->accessToken, $context);
        $this->store->rememberGrant($session, $grant);

        return $grant;
    }

    /**
     * @return array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int}
     */
    private function fetch(Session $session, AppCatalogContext $context, ResourceAuthorizationResult $grant): array
    {
        // Never extend the life of a stale list while ID is unavailable.
        $this->store->forgetCatalog($session, $context);

        try {
            $catalog = $this->catalog->read($grant, $context->subject, $context->organizationId);
        } catch (AuthenticationFailed) {
            throw SignInRequired::because('Cbox ID refused the catalog grant.');
        } catch (\RuntimeException $e) {
            throw CatalogUnavailable::because($e->getMessage());
        }

        $this->store->rememberCatalog($session, $context, $catalog, min($grant->expiresAt, time() + $catalog['refresh_after']));

        return [...$catalog, 'refresh_after' => $this->refreshAfter($catalog['refresh_after'], $grant)];
    }

    /**
     * Ask again no later than when the grant enters its renewal window. Inside it (a
     * renewal that could not finish yet) every 15 seconds, not in a tight loop.
     */
    private function refreshAfter(int $refreshAfter, ResourceAuthorizationResult $grant): int
    {
        return max(1, min($refreshAfter, max(15, $grant->expiresAt - $this->renewBefore - time())));
    }

    private static function cookieSessions(): bool
    {
        return config('session.driver') === 'cookie';
    }
}
