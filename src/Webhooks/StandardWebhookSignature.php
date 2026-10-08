<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Webhooks;

use Cbox\Id\Client\IdentityClient;

/**
 * The Standard Webhooks signature (https://www.standardwebhooks.com/) — what Cbox ID signs
 * a delivery with when its endpoint's `signature_scheme` is `standard_webhooks`, next to
 * the Cbox scheme {@see IdentityClient::verifyWebhook()} checks.
 *
 * - `webhook-id`: the delivery id — the same on every retry, so dedupe on it;
 * - `webhook-timestamp`: unix seconds of this attempt;
 * - `webhook-signature`: a space-delimited list of `<version>,<signature>`; `v1` is
 *   base64(HMAC-SHA256 over `"{id}.{timestamp}.{raw body}"`). Other versions are skipped.
 *
 * The secret is `whsec_` + base64, and the HMAC key is the base64-DECODED bytes — the detail
 * hand-written verifiers get wrong. A Cbox-scheme (hex) secret converts losslessly with
 * {@see self::secretFor()}, which is what an endpoint moved to Standard Webhooks without a
 * new secret is signed with.
 *
 * The same byte layout as Cbox ID's own sender, proven against the specification's
 * published vector. Only `hash_hmac`, `hash_equals` and base64.
 */
class StandardWebhookSignature
{
    public const SECRET_PREFIX = 'whsec_';

    public const ID_HEADER = 'webhook-id';

    public const TIMESTAMP_HEADER = 'webhook-timestamp';

    public const SIGNATURE_HEADER = 'webhook-signature';

    /** Five minutes, the tolerance the reference libraries use. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * The `whsec_` form of an endpoint secret: as is when it already is one; a Cbox-scheme
     * secret (64 hex characters, used as bytes) as `whsec_` + base64 of those bytes.
     */
    public static function secretFor(string $endpointSecret): string
    {
        return str_starts_with($endpointSecret, self::SECRET_PREFIX)
            ? $endpointSecret
            : self::SECRET_PREFIX.base64_encode($endpointSecret);
    }

    /**
     * The `webhook-signature` value for one message — what a test of your receiver sends.
     * Null when the secret is not a usable `whsec_` secret.
     */
    public static function sign(string $id, int $timestamp, string $payload, string $secret): ?string
    {
        $key = self::key($secret);

        return $key === null ? null : 'v1,'.self::mac($id, $timestamp, $payload, $key);
    }

    /**
     * Whether a received delivery is authentic and fresh. `$payload` must be the RAW body;
     * `$headers` a plain map or `$request->headers->all()`, matched case-insensitively.
     *
     * False — never an exception — when the secret is not a `whsec_` secret, a header is
     * missing, the timestamp is not unix seconds within `$tolerance` of now (either way),
     * there is no `v1` entry, or no `v1` entry matches (compared in constant time).
     *
     * @param  array<array-key, mixed>  $headers
     */
    public static function verify(string $payload, array $headers, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        $key = self::key($secret);
        $id = self::header($headers, self::ID_HEADER);
        $timestamp = self::header($headers, self::TIMESTAMP_HEADER);
        $signatures = self::header($headers, self::SIGNATURE_HEADER);

        if ($key === null || $id === null || $timestamp === null || $signatures === null || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = self::mac($id, (int) $timestamp, $payload, $key);

        foreach (preg_split('/\s+/', $signatures, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            [$version, $signature] = array_pad(explode(',', $entry, 2), 2, '');

            if ($version === 'v1' && $signature !== '' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** base64(HMAC-SHA256(key, "{id}.{timestamp}.{payload}")). */
    private static function mac(string $id, int $timestamp, string $payload, string $key): string
    {
        return base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$payload, $key, true));
    }

    /**
     * The HMAC key: the base64-decoded bytes after `whsec_`. The prefix is required — a hex
     * Cbox secret is also valid base64, and keying with its decoding would fail every
     * delivery as a silent mismatch; convert it with {@see self::secretFor()}.
     */
    private static function key(string $secret): ?string
    {
        if (! str_starts_with($secret, self::SECRET_PREFIX)) {
            return null;
        }

        $key = base64_decode(substr($secret, strlen(self::SECRET_PREFIX)), true);

        return $key === false || $key === '' ? null : $key;
    }

    /** @param array<array-key, mixed> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (! is_string($key) || strcasecmp($key, $name) !== 0) {
                continue;
            }

            $value = is_array($value) ? ($value[0] ?? null) : $value;

            return is_string($value) && $value !== '' ? $value : null;
        }

        return null;
    }
}
