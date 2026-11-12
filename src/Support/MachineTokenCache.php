<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Support\Carbon;

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
 *
 * RENEWED AHEAD OF EXPIRY, so a request finds a valid token. A token asked for through
 * {@see rememberFor()} is noted as in use. {@see due()} lists the ones in use with less
 * than `refresh_ahead` left, for the scheduled `cbox-id:refresh` to {@see renew()}; a
 * request that finds one in its last two minutes returns it and renews it after the
 * response. A request asks Cbox ID itself only when there is no token at all. A token
 * nothing has asked for in `keep_warm_for` is no longer renewed.
 */
class MachineTokenCache
{
    /** Renew this long before the token's own expiry, so no call carries a token that dies in flight. */
    public const int MARGIN_SECONDS = 60;

    /** Kept this long when neither the token nor the response says when it expires. */
    public const int FALLBACK_SECONDS = 240;

    /** Never kept longer than this, whatever the token says. */
    public const int MAX_SECONDS = 3600;

    /** A request that finds a token with this little left renews it after its response. */
    public const int RENEW_AFTER_RESPONSE_WITHIN = 120;

    /** `refresh_ahead` when nothing is configured: ten minutes, two runs of the schedule. */
    public const int DEFAULT_REFRESH_AHEAD = 600;

    /** `keep_warm_for` when nothing is configured: seven days. */
    public const int DEFAULT_KEEP_WARM_FOR = 604_800;

    /** How often a token's "in use" time is written, at most. */
    private const int NOTE_USE_EVERY = 3600;

    private const string PREFIX = 'cbox-id-client.machine-token.';

    private const int LOCK_SECONDS = 10;

    private const int LOCK_WAIT_SECONDS = 5;

    private readonly int $refreshAhead;

    private readonly int $keepWarmFor;

    public function __construct(
        private readonly Repository $cache,
        private readonly StringEncrypter $encrypter,
        int $refreshAhead = self::DEFAULT_REFRESH_AHEAD,
        int $keepWarmFor = self::DEFAULT_KEEP_WARM_FOR,
    ) {
        $this->refreshAhead = max(0, $refreshAhead);
        $this->keepWarmFor = max(self::NOTE_USE_EVERY, $keepWarmFor);
    }

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

    /**
     * {@see remember()}, and the token is noted as in use, so it is renewed ahead of its
     * expiry from then on ({@see due()}, {@see renew()}). A kept token in its last two
     * minutes is returned as it is and renewed once the response has been sent.
     *
     * @param  Closure(): IssuedMachineToken  $fetch
     */
    public function rememberFor(MachineTokenRequest $request, Closure $fetch): string
    {
        $key = $this->keyFor($request);
        $cached = $this->cached($key);

        if ($cached === null) {
            // No token at all: the one wait. `renew()` writes the note when it stores.
            $token = $this->remember($key, $fetch);
            $this->note($request, $this->meta($key)['until'] ?? null, true);

            return $token;
        }

        $meta = $this->meta($key);
        $now = Carbon::now()->getTimestamp();

        if ($meta === null || $now - $meta['used_at'] >= self::NOTE_USE_EVERY) {
            $this->note($request, $meta['until'] ?? null, true);
        }

        if ($meta !== null && $meta['until'] - $now <= self::RENEW_AFTER_RESPONSE_WITHIN) {
            Background::afterResponse('cbox-id-client:machine-token:'.$key, function () use ($request, $fetch): void {
                $this->renew($request, $fetch);
            });
        }

        return $cached;
    }

    /**
     * The tokens of this issuer and client that are in use and have less than
     * `refresh_ahead` left, or have expired. One nothing has asked for in
     * `keep_warm_for` is dropped from the list instead.
     *
     * @return list<MachineTokenRequest>
     */
    public function due(string $issuer, string $clientId): array
    {
        $now = Carbon::now()->getTimestamp();
        $index = $this->index($issuer, $clientId);
        $due = [];
        $kept = [];

        foreach ($index as $key => $entry) {
            $meta = $this->meta($key);

            if ($meta === null || $now - $meta['used_at'] > $this->keepWarmFor) {
                continue;
            }

            $kept[$key] = $entry;

            if ($this->refreshAhead >= $meta['until'] - $now || $this->cached($key) === null) {
                $due[] = new MachineTokenRequest($issuer, $clientId, $entry['scopes'], $entry['resource']);
            }
        }

        if (count($kept) !== count($index)) {
            $this->cache->put($this->indexKey($issuer, $clientId), $kept, $this->keepWarmFor);
        }

        return $due;
    }

    /**
     * Ask for the token again and keep the new one, under the same lock as
     * {@see remember()}. Returns false, and asks nothing, when another worker holds that
     * lock: it is fetching the same token. A failure is thrown and the kept token stays.
     *
     * @param  Closure(): IssuedMachineToken  $fetch
     */
    public function renew(MachineTokenRequest $request, Closure $fetch): bool
    {
        $key = $this->keyFor($request);
        $store = $this->cache->getStore();
        $lock = $store instanceof LockProvider ? $store->lock($key.'.lock', self::LOCK_SECONDS) : null;

        if ($lock !== null && ! $lock->get()) {
            return false;
        }

        try {
            $this->fetch($key, $fetch);
            $this->note($request, $this->meta($key)['until'] ?? null, false);

            return true;
        } finally {
            $lock?->release();
        }
    }

    /** Drop the cached token, for example after Cbox ID refused it. */
    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }

    /**
     * Seconds to keep a token: its lifetime (from the `exp` claim, else `expires_in`)
     * minus the margin, or half the lifetime for a token that lives under two minutes;
     * the fallback when neither says. Capped at an hour. Zero or less means do not
     * cache.
     *
     * Only `exp` is read from the token, to know when it ends. Its signature is Cbox
     * ID's to check, not the client's.
     */
    public function secondsToKeep(IssuedMachineToken $issued, ?int $now = null): int
    {
        $now ??= time();
        $expiresAt = $this->expiryClaim($issued->token);

        $lifetime = match (true) {
            $expiresAt !== null => $expiresAt - $now,
            $issued->expiresIn !== null => $issued->expiresIn,
            default => null,
        };

        if ($lifetime === null) {
            return self::FALLBACK_SECONDS;
        }

        // A short-lived token is kept for half its life at most, so the margin never eats
        // the whole of it and the token is still worth reusing.
        $seconds = $lifetime >= 2 * self::MARGIN_SECONDS ? $lifetime - self::MARGIN_SECONDS : intdiv($lifetime, 2);

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

            // When the kept copy ends, next to it: the token itself stays an opaque,
            // encrypted string, readable by a version that knows nothing of this.
            $meta = $this->meta($key);
            $now = Carbon::now()->getTimestamp();
            $this->cache->put($key.'.meta', ['until' => $now + $seconds, 'used_at' => $meta['used_at'] ?? $now], $this->keepWarmFor);
        }

        return $issued->token;
    }

    private function keyFor(MachineTokenRequest $request): string
    {
        return $this->key($request->issuer, $request->clientId, $request->scopes, $request->resource);
    }

    /**
     * Write the token's note (when its kept copy ends, when it was last asked for) and
     * list it for {@see due()}. `$used` moves the "last asked for" time to now.
     */
    private function note(MachineTokenRequest $request, ?int $until, bool $used): void
    {
        $key = $this->keyFor($request);
        $now = Carbon::now()->getTimestamp();
        $meta = $this->meta($key);

        $this->cache->put($key.'.meta', [
            'until' => $until ?? $meta['until'] ?? $now,
            'used_at' => $used ? $now : ($meta['used_at'] ?? $now),
        ], $this->keepWarmFor);

        $index = $this->index($request->issuer, $request->clientId);

        if (! isset($index[$key])) {
            $scopes = array_values(array_unique($request->scopes));
            sort($scopes);

            // Read, then written: two workers adding at once can lose one entry, which
            // is added again the next time that token is asked for.
            $index[$key] = ['scopes' => $scopes, 'resource' => $request->resource];
            $this->cache->put($this->indexKey($request->issuer, $request->clientId), $index, $this->keepWarmFor);
        }
    }

    /** @return array{until: int, used_at: int}|null */
    private function meta(string $key): ?array
    {
        $meta = $this->cache->get($key.'.meta');

        if (! is_array($meta) || ! is_int($meta['until'] ?? null) || ! is_int($meta['used_at'] ?? null)) {
            return null;
        }

        return ['until' => $meta['until'], 'used_at' => $meta['used_at']];
    }

    /**
     * The tokens noted as in use for one issuer and client: scopes and resource only,
     * never a token.
     *
     * @return array<string, array{scopes: list<string>, resource: ?string}>
     */
    private function index(string $issuer, string $clientId): array
    {
        $stored = $this->cache->get($this->indexKey($issuer, $clientId));
        $index = [];

        foreach (is_array($stored) ? $stored : [] as $key => $entry) {
            if (! is_string($key) || ! is_array($entry) || ! is_array($entry['scopes'] ?? null)) {
                continue;
            }

            $resource = $entry['resource'] ?? null;
            $index[$key] = [
                'scopes' => array_values(array_filter($entry['scopes'], 'is_string')),
                'resource' => is_string($resource) ? $resource : null,
            ];
        }

        return $index;
    }

    private function indexKey(string $issuer, string $clientId): string
    {
        return self::PREFIX.'index.'.hash('sha256', json_encode([$issuer, $clientId], JSON_THROW_ON_ERROR));
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
