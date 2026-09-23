<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ApiKeys;

use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Exceptions\ApiKeyRejected;
use Cbox\Id\Client\Exceptions\ApiKeyVerificationUnavailable;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Support\Claims;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks Cbox ID whether a customer API key is live, as this application.
 *
 * `POST {issuer}/oauth/api-keys/verify` with the key, authenticated with THIS app's own
 * client credentials. Cbox ID answers only for keys bound to this client (or to an API
 * whose app is this client), and re-caps the key's permissions to what its holder still
 * has — so a member who lost a permission cannot keep it through a key.
 *
 * **CACHED BRIEFLY, AND ONLY THE YES.** A round trip per request would put Cbox ID on
 * your API's hot path; a long cache would keep a revoked key working. The default is 60
 * seconds (`cbox-id-client.api_keys.cache_ttl`), never past the key's own expiry — that
 * is the revocation window you are choosing. A "no" is never cached: a caller spraying
 * random keys must not be able to fill your cache, and a key that was just created must
 * not be remembered as invalid. The cache is keyed on a SHA-256 of the key, never the key.
 *
 * **A key for another application is refused here too**, not only by Cbox ID: the answer
 * must name this client, or it is not an answer about a key for this API.
 */
class ApiKeyVerifier implements VerifiesApiKeys
{
    public const ENDPOINT = '/oauth/api-keys/verify';

    public function __construct(
        private readonly string $issuer,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly int $cacheTtl = 60,
        private readonly int $timeout = 10,
    ) {}

    public function verify(string $key, array $requiredPermissions = []): VerifiedApiKey
    {
        $key = trim($key);

        if ($key === '') {
            throw ApiKeyRejected::inactive('No API key was presented.');
        }

        $verified = $this->cached($key) ?? $this->fetch($key);

        $missing = array_values(array_filter($requiredPermissions, static fn (string $p): bool => ! $verified->hasPermission($p)));

        if ($missing !== []) {
            throw ApiKeyRejected::missingPermissions($missing);
        }

        return $verified;
    }

    /** Forget a cached answer — call it when your app learns a key was revoked (the `api_key.revoked` webhook). */
    public function forget(string $key): void
    {
        Cache::forget($this->cacheKey(trim($key)));
    }

    private function fetch(string $key): VerifiedApiKey
    {
        foreach (['issuer' => $this->issuer, 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret] as $name => $value) {
            if ($value === '') {
                throw NotConfigured::key($name, 'verify customer API keys');
            }
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post(rtrim($this->issuer, '/').self::ENDPOINT, ['key' => $key]);
        } catch (Throwable $e) {
            throw ApiKeyVerificationUnavailable::unreachable($e);
        }

        if (! $response->successful()) {
            $body = Claims::normalize($response->json());

            throw ApiKeyVerificationUnavailable::refused($response->status(), Claims::string($body, 'error'));
        }

        $verified = VerifiedApiKey::fromResponse(Claims::normalize($response->json()));

        if ($verified === null) {
            throw ApiKeyRejected::inactive();
        }

        if (! hash_equals($this->clientId, $verified->clientId)) {
            throw ApiKeyRejected::inactive('The API key belongs to another application.');
        }

        if ($verified->isExpired()) {
            throw ApiKeyRejected::inactive('The API key has expired.');
        }

        $ttl = $this->cacheTtl;

        if ($verified->expiresAt !== null) {
            $ttl = min($ttl, $verified->expiresAt->getTimestamp() - Carbon::now()->getTimestamp());
        }

        if ($ttl > 0) {
            Cache::put($this->cacheKey($key), $verified->toArray(), $ttl);
        }

        return $verified;
    }

    private function cached(string $key): ?VerifiedApiKey
    {
        if ($this->cacheTtl <= 0) {
            return null;
        }

        $verified = VerifiedApiKey::fromResponse(Claims::normalize(Cache::get($this->cacheKey($key))));

        // Belt and braces: the TTL already stops at the expiry, but a cache store that
        // rounds TTLs up must not stretch a key past it.
        return $verified !== null && ! $verified->isExpired() && hash_equals($this->clientId, $verified->clientId) ? $verified : null;
    }

    private function cacheKey(string $key): string
    {
        return 'cbox-id-client:api-key:'.hash('sha256', $this->issuer.'|'.$key);
    }
}
