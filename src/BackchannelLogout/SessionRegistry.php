<?php

declare(strict_types=1);

namespace Cbox\Id\Client\BackchannelLogout;

use Cbox\Id\Client\Http\EnforceBackchannelLogout;
use Cbox\Id\Client\Support\Claims;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Which local sessions belong to which Cbox ID session (`sid`) and person (`sub`), and
 * which of them Cbox ID has since ended.
 *
 * TWO MECHANISMS, because no single one works on every session driver:
 *
 * - **An index, for ending sessions now.** {@see EnforceBackchannelLogout}
 *   records `sid → session id` and `sub → session ids` as a signed-in request finishes
 *   (after any login regenerated the id). A logout token looks the sessions up and
 *   destroys them in the session store. That works where the server can delete a session:
 *   `database`, `redis`, `memcached`/`dynamodb` (cache-backed), and `file` on a single
 *   server.
 * - **A revocation list, for everything else.** The ended `sid` — or, for a whole person,
 *   `sub` with the logout token's `iat` — is remembered, and the same middleware signs out
 *   any session that presents it on its next request. This is the only mechanism for the
 *   `cookie` driver (the session lives in the browser; there is nothing to delete) and for
 *   `file` behind a load balancer, and the safety net for a session the index missed.
 *
 * Both live in a cache store, so it must be one every web server shares (redis, database,
 * memcached — not `array`, and not `file` across machines). Entries are kept for the
 * session lifetime: a local session older than that has expired anyway.
 *
 * LARAVEL'S REMEMBER-ME COOKIE IS PER USER, NOT PER SESSION. Destroying a session does not
 * clear a browser's recaller cookie, and the next request logs the person back in. So when
 * a logout ends EVERY session of a person, the local users' remember tokens are cycled
 * (`remember_tokens: subject`, the default), which invalidates every recaller they hold.
 * A single-session (`sid`) logout cannot do that without signing them out everywhere; set
 * `remember_tokens: always` if that trade is right for you.
 */
class SessionRegistry
{
    private const PREFIX = 'cbox-id-client:bcl:';

    public function __construct(
        private readonly Cache $cache,
        private readonly int $ttlSeconds,
        private readonly bool $destroySessions = true,
        private readonly string $rememberTokens = 'subject',
    ) {}

    /**
     * Index a local session under the identity's `sid` and `sub`.
     */
    public function record(Identity $identity, string $sessionId, ?string $localUserId, ?int $rememberedAt): void
    {
        $entry = ['session' => $sessionId, 'user' => $localUserId, 'at' => $rememberedAt];
        $sid = Claims::string($identity->claims, 'sid');

        if ($sid !== null) {
            $this->append($this->key('sid', $sid), $entry);
        }

        $this->append($this->key('sub', $identity->subject), $entry);
    }

    /**
     * End what a logout token names: by `sid` when it carries one, otherwise every
     * session of `sub` that signed in no later than the token was issued.
     */
    public function end(LogoutToken $token): EndedSessions
    {
        if ($token->sid !== null) {
            $this->cache->put($this->key('revoked-sid', $token->sid), true, $this->ttlSeconds);
            $indexKey = $this->key('sid', $token->sid);
        } else {
            $subject = (string) $token->subject;
            $revokedAt = $this->cache->get($this->key('revoked-sub', $subject));
            $this->cache->put($this->key('revoked-sub', $subject), max(is_int($revokedAt) ? $revokedAt : 0, $token->issuedAt), $this->ttlSeconds);
            $indexKey = $this->key('sub', $subject);
        }

        $entries = $this->entries($indexKey);

        if ($token->sid === null) {
            // Only sessions that signed in no later than the token was issued. Cbox ID
            // retries a delivery for many minutes, and a person who signed out everywhere
            // and straight back in must not lose the new session to the late notice.
            $kept = array_values(array_filter($entries, static fn (array $e): bool => $e['at'] !== null && $e['at'] > $token->issuedAt));
            $entries = array_values(array_filter($entries, static fn (array $e): bool => $e['at'] === null || $e['at'] <= $token->issuedAt));
            $kept === [] ? $this->cache->forget($indexKey) : $this->cache->put($indexKey, $kept, $this->ttlSeconds);
        } else {
            $this->cache->forget($indexKey);
        }

        $sessionIds = array_values(array_unique(array_column($entries, 'session')));
        $userIds = array_values(array_unique(array_filter(array_column($entries, 'user'), 'is_string')));

        if ($this->destroySessions) {
            $this->destroy($sessionIds);
        }

        if ($this->rememberTokens === 'always' || ($this->rememberTokens === 'subject' && $token->sid === null)) {
            $this->cycleRememberTokens($userIds);
        }

        return new EndedSessions($sessionIds, $userIds);
    }

    /**
     * Whether Cbox ID has ended the session this identity came from. `$rememberedAt` is
     * when the identity was remembered; a session with no such time is treated as older
     * than any logout (deny by default).
     */
    public function isRevoked(Identity $identity, ?int $rememberedAt): bool
    {
        $sid = Claims::string($identity->claims, 'sid');

        if ($sid !== null && $this->cache->get($this->key('revoked-sid', $sid)) === true) {
            return true;
        }

        $revokedAt = $this->cache->get($this->key('revoked-sub', $identity->subject));

        return is_int($revokedAt) && ($rememberedAt ?? 0) <= $revokedAt;
    }

    /**
     * @param  array{session: string, user: string|null, at: int|null}  $entry
     */
    private function append(string $key, array $entry): void
    {
        $write = function () use ($key, $entry): void {
            $entries = array_filter($this->entries($key), static fn (array $e): bool => $e['session'] !== $entry['session']);
            $entries[] = $entry;

            $this->cache->put($key, array_values($entries), $this->ttlSeconds);
        };

        $store = $this->cache->getStore();

        // Two requests of one person finishing at once must not drop each other's entry.
        if ($store instanceof LockProvider) {
            $store->lock($key.':lock', 5)->block(3, $write);

            return;
        }

        $write();
    }

    /**
     * @return list<array{session: string, user: string|null, at: int|null}>
     */
    private function entries(string $key): array
    {
        $out = [];

        foreach (Claims::objects(['v' => $this->cache->get($key)], 'v') as $entry) {
            $session = Claims::string($entry, 'session');

            if ($session !== null) {
                $out[] = ['session' => $session, 'user' => Claims::string($entry, 'user'), 'at' => Claims::int($entry, 'at')];
            }
        }

        return $out;
    }

    /** @param list<string> $sessionIds */
    private function destroy(array $sessionIds): void
    {
        if ($sessionIds === []) {
            return;
        }

        $store = app(SessionManager::class)->driver();

        if (! $store instanceof Session) {
            return;
        }

        $handler = $store->getHandler();

        foreach ($sessionIds as $id) {
            $handler->destroy($id);
        }
    }

    /** @param list<string> $userIds */
    private function cycleRememberTokens(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        try {
            $guard = app(AuthFactory::class)->guard();
        } catch (Throwable) {
            return;
        }

        if (! $guard instanceof SessionGuard) {
            return;
        }

        $provider = $guard->getProvider();

        foreach ($userIds as $id) {
            $user = $provider->retrieveById($id);

            if ($user !== null) {
                $provider->updateRememberToken($user, Str::random(60));
            }
        }
    }

    private function key(string $kind, string $value): string
    {
        return self::PREFIX.$kind.':'.hash('sha256', $value);
    }
}
