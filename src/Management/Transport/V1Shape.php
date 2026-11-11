<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use Cbox\Id\Client\Support\PublicId;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use JsonException;

/**
 * A `/v1` answer, read back into the `/api/v1` shape the management clients have always
 * handed out — so moving to `/v1` is invisible to the app.
 *
 * Cbox ID builds every `/v1` answer from the `/api/v1` one (its `V1Responses` middleware);
 * this is the inverse:
 *
 *  - a list (`{"object":"list","data":[…],"has_more":…,"next_cursor":…}`) becomes
 *    `{"data":[…],"meta":{"has_more":…,"next_cursor":…}}`;
 *  - a resource (the object itself, with `object`) becomes `{"data":{…}}` without `object`,
 *    or stays bare where `/api/v1` answered bare;
 *  - a held action (`object: "approval"`) becomes `{"error":"approval_required","message":…,
 *    "approval":{…}}`;
 *  - an RFC 9457 problem becomes `{"error","message","errors",…}`;
 *  - every public id (`usr_01j…`) in an `id`, `*_id` or `*_ids` field, at any depth, is read
 *    through {@see PublicId::raw()} back to the bare ULID that token claims and the app's own
 *    tables hold.
 *
 * Ids are only unwrapped where Cbox ID wraps them: never inside a customer's own data
 * ({@see self::OPAQUE}), never in a field that holds the caller's own value
 * ({@see self::VERBATIM}), and only for a prefix Cbox ID registers ({@see self::PREFIXES}),
 * so `doc_01j…` in an app's own field passes through as it was sent.
 *
 * @internal
 */
final class V1Shape
{
    /** The prefixes Cbox ID gives its records' public ids (its `PublicId::PREFIXES`). */
    public const array PREFIXES = [
        'org', 'usr', 'mbr', 'ivt', 'prj', 'env', 'rol', 'perm', 'rla', 'app', 'apir', 'scp',
        'conn', 'dir', 'dusr', 'dgrp', 'vdom', 'whe', 'whd', 'idevt', 'alog', 'flag', 'ses',
        'dvc', 'sva', 'vsec', 'pipe', 'hook', 'lstr', 'prov', 'sod', 'arc', 'ari', 'rdr', 'apr',
        'sup', 'capp', 'fga', 'okey', 'ekey', 'ckey', 'pkey', 'pat', 'sapp', 'brd', 'mfa', 'psk',
    ];

    /** Keys whose contents are a customer's own data: Cbox ID never rewrites ids there. */
    public const array OPAQUE = ['metadata', 'custom_attributes', 'properties', 'claims', 'actor', 'targets', 'context', 'settings', 'raw'];

    /**
     * `*_id` fields that hold a value the caller chose (an OAuth client id, an app's own
     * resource or subject id), which Cbox ID never prefixes and so never needs unwrapping.
     */
    public const array VERBATIM = ['client_id', 'external_id', 'resource_id', 'subject_id', 'object_id', 'actor_id', 'target_id'];

    /**
     * The `/api/v1` shape of a `/v1` response.
     *
     * @param  string  $shape  how `/api/v1` answers this operation: `list` (`{"data": […], "meta": …}`),
     *                         `data` (`{"data": …}`) or `bare` (the value itself)
     */
    public static function toLegacy(Response $response, string $shape = 'data'): Response
    {
        $text = $response->body();

        if ($text === '') {
            return $response;
        }

        try {
            $body = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $response;
        }

        if (! is_array($body) || array_is_list($body)) {
            return $response;
        }

        return self::respond($response, self::legacyBody($body, $response->status(), $shape));
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public static function legacyBody(array $body, int $status, string $shape = 'data'): array
    {
        $object = $body['object'] ?? null;

        if ($status >= 400 && (array_key_exists('detail', $body) || array_key_exists('title', $body) || array_key_exists('type', $body))) {
            $detail = $body['detail'] ?? null;
            unset($body['type'], $body['title'], $body['status'], $body['detail']);

            return self::raw(['error' => $body['error'] ?? null, 'message' => $detail, ...$body]);
        }

        if ($object === 'approval' && $status === 202) {
            $detail = $body['detail'] ?? null;
            unset($body['object'], $body['detail']);

            return ['error' => 'approval_required', 'message' => $detail, 'approval' => self::raw($body)];
        }

        if ($object === 'list' && array_key_exists('data', $body)) {
            $data = self::raw(is_array($body['data']) ? array_values($body['data']) : []);

            // A list `/api/v1` answers without `meta` (a role list, say) gets none here either.
            return $shape === 'list'
                ? ['data' => $data, 'meta' => ['has_more' => ($body['has_more'] ?? false) === true, 'next_cursor' => $body['next_cursor'] ?? null]]
                : ['data' => $data];
        }

        if (! is_string($object)) {
            // Cbox ID passed it through as `/api/v1` answered it: only the ids changed.
            return self::raw($body);
        }

        if ($shape === 'bare') {
            return self::raw($body);
        }

        unset($body['object']);

        return ['data' => self::raw($body)];
    }

    /**
     * Every Cbox ID public id in `$node` read back as the bare ULID.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    public static function raw(array $node): array
    {
        foreach ($node as $key => $value) {
            if (! is_string($key)) {
                if (is_array($value)) {
                    $node[$key] = self::raw($value);
                }

                continue;
            }

            if (in_array($key, self::OPAQUE, true) || in_array($key, self::VERBATIM, true)) {
                continue;
            }

            $single = $key === 'id' || str_ends_with($key, '_id');

            if ($single && is_string($value)) {
                $node[$key] = self::rawId($value);

                continue;
            }

            if (str_ends_with($key, '_ids') && is_array($value) && array_is_list($value)) {
                $node[$key] = array_map(static fn (mixed $one): mixed => is_string($one) ? self::rawId($one) : $one, $value);

                continue;
            }

            if (is_array($value)) {
                $node[$key] = self::raw($value);
            }
        }

        return $node;
    }

    /** The bare ULID of a Cbox ID public id; anything else as it is. */
    public static function rawId(string $id): string
    {
        $underscore = strpos($id, '_');

        if ($underscore === false || ! in_array(substr($id, 0, $underscore), self::PREFIXES, true)) {
            return $id;
        }

        return PublicId::raw($id);
    }

    /** Whether `$id` is a Cbox ID public id (`usr_01j…`), which app code must never see. */
    public static function isPublicId(string $id): bool
    {
        return self::rawId($id) !== $id;
    }

    /** @param array<array-key, mixed> $body */
    private static function respond(Response $response, array $body): Response
    {
        $headers = $response->toPsrResponse()->getHeaders();

        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower((string) $name), ['content-type', 'content-length'], true)) {
                unset($headers[$name]);
            }
        }

        $headers['Content-Type'] = ['application/json'];
        $json = json_encode($body === [] ? (object) [] : $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return new Response(new Psr7Response($response->status(), $headers, $json, $response->toPsrResponse()->getProtocolVersion(), $response->toPsrResponse()->getReasonPhrase()));
    }
}
