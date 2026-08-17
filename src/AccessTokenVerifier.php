<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\Exceptions\TokenRejected;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;

/**
 * Verifies an access token presented TO your API, locally.
 *
 * The mirror image of {@see IdentityClient::authenticate()}, which verifies a token
 * minted FOR you at the end of a login. Same signature check against the same JWKS,
 * three deliberate differences:
 *
 * - **The audience is a resource, not a client id.** An id_token is addressed to the
 *   application; an access token is addressed to the API it may be spent at. Cbox ID
 *   sets `aud` on every access token — the requested resource, or the issuer when
 *   none was asked for (RFC 9068 §2.2) — so this can require it without exceptions.
 * - **No nonce.** A nonce binds a login to a redirect. An access token has no
 *   redirect, and demanding one would reject every valid machine token.
 * - **Scopes can be required.** A login asks who you are; an API call asks whether
 *   you may. That question has no equivalent in the login flow.
 *
 * **LOCAL, AND THAT IS THE POINT.** Introspection would answer the same question with
 * a round trip to Cbox ID on every request — putting another service on the hot path
 * of one that is supposed to be arithmetic, and making this API unavailable whenever
 * that one is. Cbox ID's issuer takes the same view from the other side: it embeds
 * coarse entitlements in the token precisely so a resource server can decide without
 * calling back. The cost is that a revoked token lives until it expires, which is
 * answered with short lifetimes rather than with a call per request.
 */
readonly class AccessTokenVerifier
{
    /**
     * RFC 7517 §4.4 makes `alg` optional on a JWKS key, so verification must not
     * depend on the instance emitting one. Same assumption the login flow makes.
     */
    private const DEFAULT_JWK_ALG = 'RS256';

    /**
     * @param  string  $audience  This API's resource identifier. Distinct per
     *                            environment when environments are separated that
     *                            way — a sandbox token then cannot be spent in
     *                            production, enforced by the signature rather than
     *                            by a configuration convention beside it.
     */
    public function __construct(
        private Discovery $discovery,
        private string $issuer,
        private string $audience,
    ) {}

    /**
     * @param  list<string>  $requiredScopes  All must be present.
     *
     * @throws TokenRejected
     */
    public function verify(string $token, array $requiredScopes = []): VerifiedToken
    {
        $claims = $this->decode($token);

        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw TokenRejected::because('The token issuer did not match.');
        }

        // The confused-deputy defence, and the reason `aud` is not optional here. A
        // token minted to be spent at another service must not be accepted at this
        // one just because both trust the same issuer.
        $audience = $claims['aud'] ?? null;
        $matches = $audience === $this->audience
            || (is_array($audience) && in_array($this->audience, $audience, true));

        if (! $matches) {
            throw TokenRejected::because('The token was not minted for this API.');
        }

        // RFC 9449: a `cnf` claim means the token is bound to the holder's DPoP key,
        // and a bearer presentation of it is not what the issuer authorised.
        // Accepting it here without validating the proof would silently downgrade a
        // sender-constrained token to a bearer one — undoing the protection while
        // appearing to honour it. Refused until the proof check exists.
        if (isset($claims['cnf'])) {
            throw TokenRejected::because(
                'This token is sender-constrained (DPoP) and this API only accepts bearer tokens. '
                .'Request a token without a DPoP binding, or add proof validation here before accepting one.'
            );
        }

        $scopes = $this->scopesOf($claims);
        $missing = array_values(array_diff($requiredScopes, $scopes));

        if ($missing !== []) {
            throw TokenRejected::because('The token is missing required scope(s): '.implode(', ', $missing));
        }

        $subject = $claims['sub'] ?? null;

        if (! is_string($subject) || $subject === '') {
            throw TokenRejected::because('The verified token carried no subject.');
        }

        return new VerifiedToken(
            subject: $subject,
            clientId: is_string($claims['client_id'] ?? null) ? $claims['client_id'] : '',
            organizationId: is_string($claims['org'] ?? null) ? $claims['org'] : null,
            organizationName: is_string($claims['org_name'] ?? null) ? $claims['org_name'] : null,
            audience: $this->audience,
            scopes: $scopes,
            entitlements: $this->entitlementsOf($claims),
            id: is_string($claims['jti'] ?? null) ? $claims['jti'] : '',
            expiresAt: is_int($claims['exp'] ?? null) ? $claims['exp'] : 0,
            claims: $claims,
        );
    }

    /**
     * Signature and expiry, against the instance's published keys.
     *
     * @return array<string, mixed>
     */
    private function decode(string $token): array
    {
        $jwks = $this->discovery->jwks();

        // The instance can roll its signing key inside our JWKS cache TTL. Without a
        // refetch on a kid miss, every request fails until the TTL lapses — the same
        // trap the login flow already learned.
        if (! $this->carriesKid($jwks, $this->kidOf($token))) {
            $jwks = $this->discovery->refreshJwks() ?? $jwks;
        }

        try {
            // JWT::decode enforces exp and nbf itself and throws on a bad signature.
            return $this->asArray(get_object_vars(JWT::decode($token, JWK::parseKeySet($jwks, self::DEFAULT_JWK_ALG))));
        } catch (Throwable $e) {
            throw TokenRejected::because('The token could not be verified: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    private function scopesOf(array $claims): array
    {
        // OAuth puts scopes in a space-separated STRING, not an array. Reading it as
        // an array yields an empty scope set, and an empty scope set silently passes
        // every check that asks for nothing.
        $scope = $claims['scope'] ?? '';

        return is_string($scope) && $scope !== ''
            ? array_values(array_filter(explode(' ', $scope)))
            : [];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     */
    private function entitlementsOf(array $claims): array
    {
        $out = [];

        foreach ($claims as $key => $value) {
            if (str_starts_with($key, 'ent_')) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $jwks */
    private function carriesKid(array $jwks, ?string $kid): bool
    {
        if ($kid === null) {
            return true;
        }

        foreach ((array) ($jwks['keys'] ?? []) as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `kid` from the token's UNVERIFIED header — only ever to decide whether the
     * cached key set can hold the signing key, never to choose an algorithm.
     */
    private function kidOf(string $token): ?string
    {
        $segment = explode('.', $token)[0];

        if ($segment === '') {
            return null;
        }

        $remainder = strlen($segment) % 4;
        $padded = $remainder === 0 ? $segment : $segment.str_repeat('=', 4 - $remainder);
        $json = base64_decode(strtr($padded, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        $header = json_decode($json, true);
        $kid = is_array($header) ? ($header['kid'] ?? null) : null;

        return is_string($kid) ? $kid : null;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function asArray(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
