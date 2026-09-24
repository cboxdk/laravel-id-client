<?php

declare(strict_types=1);

namespace Cbox\Id\Client\BackchannelLogout;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\LogoutTokenRejected;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Support\Discovery;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use stdClass;
use Throwable;

/**
 * Validates an OIDC Back-Channel Logout 1.0 logout token, strictly (§2.6).
 *
 * A logout token ends sessions, so a forged or replayed one is a denial of service
 * against your users, and an ID Token accepted here as if it were one would let anybody
 * who ever saw an ID Token sign its owner out. In order:
 *
 * 1. Signature against the issuer's JWKS, algorithm pinned exactly as for the ID Token
 *    (the key decides; `alg: none` and HMAC confusion cannot pass), with the same
 *    refetch-on-unknown-`kid` the login flow uses. Expiry and a future `iat` are refused
 *    by the JWT library.
 * 2. `typ`, when present, is `logout+jwt` — Cbox ID always sends it, and an ID Token's
 *    header says otherwise.
 * 3. `iss` is the configured issuer; `aud` is (or contains) this client.
 * 4. `iat` and `exp` are present; `iat` is not older than `max_age` seconds.
 * 5. `jti` is present.
 * 6. `events` is an object carrying the back-channel logout member, whose value is an
 *    object.
 * 7. There is NO `nonce` — §2.4 forbids it so an ID Token can never be replayed here.
 * 8. `sub`, `sid` or both are present and are strings.
 * 9. The `jti` has not been seen: remembered until the token could no longer be valid.
 *    Checked LAST, so a token refused for any other reason does not use up its `jti`.
 *
 * Every refusal is a {@see LogoutTokenRejected} whose message names the failed check.
 */
class LogoutTokenVerifier
{
    public const EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    public const TYPE = 'logout+jwt';

    private const DEFAULT_JWK_ALG = 'RS256';

    /** Allowance for clocks, on the replay window only — never on the checks. */
    private const REPLAY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly Discovery $discovery,
        private readonly string $issuer,
        private readonly string $clientId,
        private readonly Cache $cache,
        private readonly int $maxAge = 300,
    ) {}

    /**
     * @throws LogoutTokenRejected
     * @throws ClientConfigurationException when the issuer's keys cannot be read (retryable)
     */
    public function verify(string $jwt): LogoutToken
    {
        if ($this->issuer === '') {
            throw NotConfigured::key('issuer', 'verify back-channel logout tokens');
        }

        if ($this->clientId === '') {
            throw NotConfigured::key('client_id', 'verify back-channel logout tokens');
        }

        $jwt = trim($jwt);

        if ($jwt === '') {
            throw LogoutTokenRejected::because('No logout_token was sent.');
        }

        $header = $this->header($jwt);
        $typ = $header['typ'] ?? null;

        if ($typ !== null && (! is_string($typ) || strtolower($typ) !== self::TYPE)) {
            throw LogoutTokenRejected::because('The token is not typed logout+jwt.');
        }

        $claims = $this->decode($jwt, is_string($header['kid'] ?? null) ? $header['kid'] : null);

        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw LogoutTokenRejected::because('The token issuer did not match.');
        }

        $aud = $claims['aud'] ?? null;

        if ($aud !== $this->clientId && ! (is_array($aud) && in_array($this->clientId, $aud, true))) {
            throw LogoutTokenRejected::because('The token audience is not this client.');
        }

        $issuedAt = $claims['iat'] ?? null;

        if (! is_int($issuedAt)) {
            throw LogoutTokenRejected::because('The token carries no iat.');
        }

        if (Carbon::now()->getTimestamp() - $issuedAt > $this->maxAge) {
            throw LogoutTokenRejected::because('The token was issued too long ago.');
        }

        $expiresAt = $claims['exp'] ?? null;

        if (! is_int($expiresAt)) {
            throw LogoutTokenRejected::because('The token carries no exp.');
        }

        $jti = $claims['jti'] ?? null;

        if (! is_string($jti) || $jti === '') {
            throw LogoutTokenRejected::because('The token carries no jti.');
        }

        $events = $claims['events'] ?? null;

        if (! $events instanceof stdClass || ! property_exists($events, self::EVENT)) {
            throw LogoutTokenRejected::because('The token carries no back-channel logout event.');
        }

        if (! $events->{self::EVENT} instanceof stdClass) {
            throw LogoutTokenRejected::because('The back-channel logout event is not a JSON object.');
        }

        if (array_key_exists('nonce', $claims)) {
            throw LogoutTokenRejected::because('The token carries a nonce, which a logout token must not.');
        }

        $subject = $claims['sub'] ?? null;
        $sid = $claims['sid'] ?? null;

        if (($subject !== null && ! is_string($subject)) || ($sid !== null && ! is_string($sid))) {
            throw LogoutTokenRejected::because('The token sub or sid is not a string.');
        }

        if (($subject === null || $subject === '') && ($sid === null || $sid === '')) {
            throw LogoutTokenRejected::because('The token names neither a sub nor a sid.');
        }

        $ttl = max(1, $expiresAt - Carbon::now()->getTimestamp() + self::REPLAY_MARGIN_SECONDS);

        if (! $this->cache->add('cbox-id-client:logout-jti:'.hash('sha256', $this->issuer.'|'.$jti), true, $ttl)) {
            throw LogoutTokenRejected::because('The token has already been used.');
        }

        return new LogoutToken(
            issuer: $this->issuer,
            subject: $subject !== '' ? $subject : null,
            sid: $sid !== '' ? $sid : null,
            jti: $jti,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            claims: $claims,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $jwt, ?string $kid): array
    {
        $jwks = $this->discovery->jwks();

        if ($kid !== null && ! $this->carriesKid($jwks, $kid)) {
            $jwks = $this->discovery->refreshJwks() ?? $jwks;
        }

        try {
            $keys = JWK::parseKeySet($jwks, self::DEFAULT_JWK_ALG);
        } catch (Throwable $e) {
            throw ClientConfigurationException::because('The Cbox ID signing keys could not be read: '.$e->getMessage());
        }

        try {
            $decoded = JWT::decode($jwt, $keys);
        } catch (Throwable $e) {
            throw LogoutTokenRejected::because('The token could not be verified: '.$e->getMessage());
        }

        $claims = [];

        foreach (get_object_vars($decoded) as $key => $value) {
            $claims[(string) $key] = $value;
        }

        return $claims;
    }

    /**
     * The UNVERIFIED header — read only for `typ` and to decide whether a JWKS refetch
     * could help, never to choose an algorithm.
     *
     * @return array<string, mixed>
     */
    private function header(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw LogoutTokenRejected::because('The logout_token is not a JWT.');
        }

        $segment = strtr($parts[0], '-_', '+/');
        $json = base64_decode($segment.str_repeat('=', (4 - strlen($segment) % 4) % 4), true);
        $header = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($header)) {
            throw LogoutTokenRejected::because('The logout_token is not a JWT.');
        }

        $out = [];

        foreach ($header as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /** @param array<string, mixed> $jwks */
    private function carriesKid(array $jwks, string $kid): bool
    {
        foreach ((array) ($jwks['keys'] ?? []) as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
    }
}
