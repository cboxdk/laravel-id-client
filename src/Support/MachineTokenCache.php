<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;

/**
 * Keeps client-credentials tokens in a cache until shortly before they expire.
 *
 * Cbox ID throttles `/oauth/token` per source address. Apps that share an egress
 * address (a cluster behind one tunnel, a NAT gateway) share that budget, so one app
 * asking for a token on every request starves the rest. With this cache an app asks
 * once per token lifetime, and a lock makes sure only one worker asks while the others
 * wait for its answer.
 *
 * The token is stored encrypted, one entry per issuer, client, scope set and resource.
 * An entry that cannot be read back (corrupt, or written under another app key) is
 * forgotten and fetched again.
 *
 * Use a store every worker shares (Redis, Valkey, database); a per-process store
 * still works, it just saves fewer requests.
 */
class MachineTokenCache
{
    /** Renew this long before the token's own expiry, so no call carries a token that dies in flight. */
    public const int MARGIN_SECONDS = 60;

    /** Kept this long when neither the token nor the response says when it expires. */
    public const int FALLBACK_SECONDS = 240;

    /** Never kept longer than this, whatever the token says. */
    public const int MAX_SECONDS = 3600;

    private const string PREFIX = 'cbox-id-client.machine-token.';

    private const int LOCK_SECONDS = 10;

    private const int LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private readonly Repository $cache,
        private readonly StringEncrypter $encrypter,
    ) {}

    /**
     * The cache key for one token. Scopes are deduplicated and sorted, so `['b', 'a']`
     * and `['a', 'b', 'a']` share an entry.
     *
     * @param  list<string>  $scopes
     */
    public function key(string $issuer, string $clientId, array $scopes, ?string $resource): string
    {
        $scopes = array_values(array_unique($scopes));
        sort($scopes);

        return self::PREFIX.hash('sha256', json_encode([$issuer, $clientId, $scopes, $resource], JSON_THROW_ON_ERROR));
    }

    /**
     * The cached token for the key, or the one `$fetch` returns, which is then cached.
     *
     * When the store supports locks, the fetch runs under one, so concurrent workers
     * make one request between them. If the lock cannot be had within five seconds the
     * worker checks the cache once more and then fetches on its own, rather than failing
     * a request because another worker is slow.
     *
     * @param  Closure(): IssuedMachineToken  $fetch
     */
    public function remember(string $key, Closure $fetch): string
    {
        $cached = $this->cached($key);

        if ($cached !== null) {
            return $cached;
        }

        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $this->fetch($key, $fetch);
        }

        $lock = $store->lock($key.'.lock', self::LOCK_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            return $this->cached($key) ?? $this->fetch($key, $fetch);
        }

        try {
            return $this->cached($key) ?? $this->fetch($key, $fetch);
        } finally {
            $lock->release();
        }
    }

    /** Drop the cached token, for example after Cbox ID refused it. */
    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }

    /**
     * Seconds to keep a token: its `exp` claim minus the margin, else `expires_in`
     * minus the margin, else the fallback. Capped at an hour. Zero or less means do
     * not cache.
     *
     * Only `exp` is read from the token, to know when it ends. Its signature is Cbox
     * ID's to check, not the client's.
     */
    public function secondsToKeep(IssuedMachineToken $issued, ?int $now = null): int
    {
        $now ??= time();
        $expiresAt = $this->expiryClaim($issued->token);

        $seconds = match (true) {
            $expiresAt !== null => $expiresAt - $now - self::MARGIN_SECONDS,
            $issued->expiresIn !== null => $issued->expiresIn - self::MARGIN_SECONDS,
            default => self::FALLBACK_SECONDS,
        };

        return min(self::MAX_SECONDS, $seconds);
    }

    /**
     * @param  Closure(): IssuedMachineToken  $fetch
     */
    private function fetch(string $key, Closure $fetch): string
    {
        $issued = $fetch();
        $seconds = $this->secondsToKeep($issued);

        if ($seconds > 0) {
            $this->cache->put($key, $this->encrypter->encryptString($issued->token), $seconds);
        }

        return $issued->token;
    }

    private function cached(string $key): ?string
    {
        $value = $this->cache->get($key);

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            try {
                $token = $this->encrypter->decryptString($value);

                if ($token !== '') {
                    return $token;
                }
            } catch (DecryptException) {
                // Corrupt, or written under another key: forget it below.
            }
        }

        $this->cache->forget($key);

        return null;
    }

    private function expiryClaim(string $token): ?int
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = is_string($payload) ? json_decode($payload, true) : null;
        $expiry = is_array($claims) ? ($claims['exp'] ?? null) : null;

        return is_int($expiry) || is_float($expiry) ? (int) $expiry : null;
    }
}
