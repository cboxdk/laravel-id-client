<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves the Cbox ID instance's OIDC endpoints from its discovery document
 * (`{issuer}/.well-known/openid-configuration`) and its JWKS, both kept in the cache.
 * This is what lets you configure only the issuer URL — every endpoint is discovered.
 *
 * Both are fetched in a request once. After that a request reads the kept copy, and a
 * copy older than `cache_ttl` is fetched again in the background; see {@see kept()}.
 */
class Discovery
{
    /**
     * How long to wait before a second JWKS refetch after a `kid` miss. Without it a
     * token bearing a bogus kid would force a JWKS request on every verification.
     */
    private const REFETCH_COOLDOWN_SECONDS = 60;

    /** How long a failed or running background refresh holds off the next one. */
    private const REFRESH_COOLDOWN_SECONDS = 60;

    /** `cache_keep` when nothing is configured: seven days. */
    public const int DEFAULT_KEEP_SECONDS = 604_800;

    private readonly int $keepFor;

    /**
     * @param  int  $cacheTtl  seconds a kept copy is fresh for; older, it is still served
     *                         and fetched again after the response. Zero keeps nothing.
     * @param  int|null  $keepFor  seconds a copy is kept at all; null reads
     *                             `cbox-id-client.cache_keep` (seven days). Never
     *                             shorter than `$cacheTtl`.
     */
    public function __construct(
        private readonly string $issuer,
        private readonly int $cacheTtl,
        private readonly int $timeout,
        ?int $keepFor = null,
    ) {
        self::assertSecureIssuer($issuer);

        $this->keepFor = max($cacheTtl, $keepFor ?? self::configuredKeep());
    }

    /**
     * An issuer must be HTTPS.
     *
     * Every request this package makes to the issuer carries a credential — the
     * authorization code, the PKCE verifier, the client secret — and over `http` a
     * network attacker reads all of them. Worse, the same attacker replaces the
     * discovery document and the JWKS this class fetches, after which a forged id_token
     * verifies cleanly and none of the verification downstream proves anything.
     *
     * Loopback stays allowed: `php artisan serve` against a local instance runs there,
     * and RFC 8252 makes loopback the native-app callback by definition.
     *
     * An empty issuer is left alone here: that is "not configured yet", and it is
     * refused with {@see NotConfigured} at the first call that needs it — so an app
     * whose login is not set up yet can still boot and serve its other pages.
     */
    private static function assertSecureIssuer(string $issuer): void
    {
        if ($issuer === '') {
            return;
        }

        $scheme = strtolower((string) parse_url($issuer, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($issuer, PHP_URL_HOST));

        if ($scheme === 'https') {
            return;
        }

        if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return;
        }

        throw ClientConfigurationException::because(
            "The Cbox ID issuer must be https (config `cbox-id-client.issuer` is '{$issuer}').",
        );
    }

    public function endpoint(string $key): string
    {
        $document = $this->document();
        $value = $document[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw ClientConfigurationException::because("The Cbox ID discovery document is missing '{$key}'.");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        // Named, before any request. An empty issuer used to reach Guzzle as a bare path
        // and come back as "URI must include a scheme" — a 500 that says nothing about
        // the environment variable that is actually missing.
        if (trim($this->issuer) === '') {
            throw NotConfigured::key('issuer', 'discover the Cbox ID endpoints');
        }

        return $this->kept($this->documentCacheKey(), $this->fetchDocument(...));
    }

    /**
     * @return array<string, mixed>
     */
    public function jwks(): array
    {
        $uri = $this->endpoint('jwks_uri');

        return $this->kept($this->jwksCacheKey($uri), fn (): array => $this->fetchJwks($uri));
    }

    /**
     * Fetch the document and the JWKS again where the kept copy is older than
     * `cache_ttl` (or missing). For a scheduled command, so no request ever finds a
     * stale copy. Returns how many were fetched; a failure is thrown.
     */
    public function refreshStale(): int
    {
        $fetched = 0;

        if ($this->fresh($this->documentCacheKey()) === null) {
            $this->keep($this->documentCacheKey(), $this->fetchDocument());
            $fetched++;
        }

        $uri = $this->endpoint('jwks_uri');

        if ($this->fresh($this->jwksCacheKey($uri)) === null) {
            $this->keep($this->jwksCacheKey($uri), $this->fetchJwks($uri));
            $fetched++;
        }

        return $fetched;
    }

    /**
     * Refetch the JWKS, discarding the cached copy — for when an id_token presents a
     * `kid` the cached set does not carry, i.e. the instance rolled its signing key
     * inside our cache TTL. Without this, every login fails until the TTL lapses.
     *
     * Rate-limited to one refetch per cooldown window (and across processes, since
     * the marker lives in the cache), so a token bearing a bogus kid cannot turn each
     * verification into a JWKS request. Returns null while the cooldown is in effect.
     *
     * @return array<string, mixed>|null
     */
    public function refreshJwks(): ?array
    {
        $uri = $this->endpoint('jwks_uri');

        // add() is atomic: the first caller in the window wins, the rest back off.
        if (! Cache::add('cbox-id-client:jwks-refetch:'.md5($uri), true, self::REFETCH_COOLDOWN_SECONDS)) {
            return null;
        }

        return $this->keep($this->jwksCacheKey($uri), $this->fetchJwks($uri));
    }

    /**
     * The kept copy, or a fetched one when there is none.
     *
     * LOCAL FIRST. A copy is served however old it is, up to `cache_keep`. One older
     * than `cache_ttl` is fetched again once the response has been sent, so a changed
     * document or a rotated key set is picked up one request after it went stale and
     * no request waits for it. Only an app that holds no copy at all (its first
     * request, or one after `cache_keep` without any) fetches while a person waits.
     *
     * A refresh that fails leaves the kept copy in place and is tried again a minute
     * later. A refreshed document goes through the same issuer check as the first.
     *
     * @param  Closure(): array<string, mixed>  $fetch
     * @return array<string, mixed>
     */
    private function kept(string $key, Closure $fetch): array
    {
        if ($this->cacheTtl <= 0) {
            return $fetch();
        }

        $entry = $this->entry($key);

        if ($entry === null) {
            return $this->keep($key, $fetch());
        }

        if (Carbon::now()->getTimestamp() - $entry['at'] >= $this->cacheTtl) {
            Background::afterResponse('cbox-id-client:refresh:'.$key, function () use ($key, $fetch): void {
                // add() is atomic: one refresh per cooldown across every worker.
                if (Cache::add($key.':refreshing', true, self::REFRESH_COOLDOWN_SECONDS)) {
                    $this->keep($key, $fetch());
                }
            });
        }

        return $entry['value'];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function keep(string $key, array $value): array
    {
        if ($this->cacheTtl > 0) {
            Cache::put($key, ['at' => Carbon::now()->getTimestamp(), 'value' => $value], $this->keepFor);
        }

        return $value;
    }

    /**
     * @return array{at: int, value: array<string, mixed>}|null
     */
    private function entry(string $key): ?array
    {
        $entry = Cache::get($key);

        if (! is_array($entry) || ! is_int($entry['at'] ?? null) || ! is_array($entry['value'] ?? null)) {
            return null;
        }

        return ['at' => $entry['at'], 'value' => Claims::normalize($entry['value'])];
    }

    /**
     * The kept copy while it is younger than `cache_ttl`.
     *
     * @return array<string, mixed>|null
     */
    private function fresh(string $key): ?array
    {
        $entry = $this->entry($key);

        return $entry !== null && Carbon::now()->getTimestamp() - $entry['at'] < $this->cacheTtl ? $entry['value'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchDocument(): array
    {
        $response = Http::timeout($this->timeout)->connectTimeout(HttpTimeouts::connect())->get(rtrim($this->issuer, '/').'/.well-known/openid-configuration');

        if (! $response->successful()) {
            throw ClientConfigurationException::because('Could not load the Cbox ID discovery document from '.$this->issuer.'.');
        }

        $json = Claims::normalize($response->json());

        // RFC 8414 §3.3: the document's `issuer` MUST be the one it was fetched for.
        // Without this a host answering with another tenant's document silently
        // redirects the whole flow — the authorization code and client secret to that
        // tenant's token endpoint, id_token verification against its JWKS — while this
        // application still believes it is talking to the issuer it configured.
        $claimed = $json['issuer'] ?? null;

        if (! is_string($claimed) || rtrim($claimed, '/') !== rtrim($this->issuer, '/')) {
            throw ClientConfigurationException::because(
                'The Cbox ID discovery document is for a different issuer than '.$this->issuer.'.',
            );
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(string $uri): array
    {
        $response = Http::timeout($this->timeout)->connectTimeout(HttpTimeouts::connect())->get($uri);

        if (! $response->successful()) {
            throw ClientConfigurationException::because('Could not load the Cbox ID signing keys (JWKS).');
        }

        return Claims::normalize($response->json());
    }

    // New key names: an entry an older version wrote under the old ones has another
    // shape and its own, shorter, lifetime.
    private function documentCacheKey(): string
    {
        return 'cbox-id-client:discovery:kept:'.md5($this->issuer);
    }

    private function jwksCacheKey(string $uri): string
    {
        return 'cbox-id-client:jwks:kept:'.md5($uri);
    }

    private static function configuredKeep(): int
    {
        $container = Container::getInstance();
        $config = $container->bound('config') ? $container->make('config') : null;
        $value = $config instanceof Repository ? $config->get('cbox-id-client.cache_keep') : null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : self::DEFAULT_KEEP_SECONDS;
    }
}
