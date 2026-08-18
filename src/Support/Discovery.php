<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves the Cbox ID instance's OIDC endpoints from its discovery document
 * (`{issuer}/.well-known/openid-configuration`) and its JWKS, both cached. This is
 * what lets you configure only the issuer URL — every endpoint is discovered.
 */
class Discovery
{
    /**
     * How long to wait before a second JWKS refetch after a `kid` miss. Without it a
     * token bearing a bogus kid would force a JWKS request on every verification.
     */
    private const REFETCH_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly string $issuer,
        private readonly int $cacheTtl,
        private readonly int $timeout,
    ) {
        self::assertSecureIssuer($issuer);
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
     * An empty issuer is left alone: that is "not configured yet", and the callers
     * already answer it with a message naming the config key.
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
        /** @var array<string, mixed> $doc */
        $doc = Cache::remember('cbox-id-client:discovery:'.md5($this->issuer), $this->cacheTtl, function (): array {
            $response = Http::timeout($this->timeout)->get(rtrim($this->issuer, '/').'/.well-known/openid-configuration');

            if (! $response->successful()) {
                throw ClientConfigurationException::because('Could not load the Cbox ID discovery document from '.$this->issuer.'.');
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

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
        });

        return $doc;
    }

    /**
     * @return array<string, mixed>
     */
    public function jwks(): array
    {
        $uri = $this->endpoint('jwks_uri');

        /** @var array<string, mixed> $jwks */
        $jwks = Cache::remember($this->jwksCacheKey($uri), $this->cacheTtl, function () use ($uri): array {
            $response = Http::timeout($this->timeout)->get($uri);

            if (! $response->successful()) {
                throw ClientConfigurationException::because('Could not load the Cbox ID signing keys (JWKS).');
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

            return $json;
        });

        return $jwks;
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

        Cache::forget($this->jwksCacheKey($uri));

        return $this->jwks();
    }

    private function jwksCacheKey(string $uri): string
    {
        return 'cbox-id-client:jwks:'.md5($uri);
    }
}
