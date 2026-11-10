<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Illuminate\Http\Client\Response;

/**
 * Cbox ID's catalog envelope, checked: `data` a list of apps, `meta.revision` and
 * `meta.refresh_after`. The person's own read (`/api/v1/me/apps`) and the app's machine
 * read (`/api/v1/app-catalog/for-user`) answer the same shape.
 *
 * Each app may also carry `purpose` (one line: "Identity and sign-in"), `hue` (the app's
 * fixed colour) and, for an app the organization does not have yet, `enabled: false`
 * with an `admission_url` (its signup page) instead of a `launch_url`. All four are
 * optional: an older Cbox ID that sends none of them answers enabled apps only.
 *
 * @internal
 */
final class CatalogPayload
{
    /**
     * @return array{apps:list<array{key:string,name:string,description:?string,launch_url:?string,icon_url:?string,icon_dark_url:?string,purpose:?string,hue:?string,admission_url:?string,enabled:bool}>,revision:string,refresh_after:int}
     *
     * @throws \RuntimeException when the answer is not a well-formed catalog
     */
    public static function parse(Response $response, string $issuer): array
    {
        $issuer = rtrim($issuer, '/');
        $rows = $response->json('data');
        $revision = $response->json('meta.revision');
        $refresh = $response->json('meta.refresh_after');

        if (! $response->successful() || ! is_array($rows) || ! array_is_list($rows) || ! is_string($revision)
            || ! preg_match('/^[a-f0-9]{64}$/D', $revision) || ! is_int($refresh) || $refresh < 1) {
            throw new \RuntimeException('ID app catalog response is unavailable or malformed.');
        }

        $apps = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['key'] ?? null) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $row['key'])
                || ! is_string($row['name'] ?? null) || $row['name'] === '' || isset($apps[$row['key']])) {
                throw new \RuntimeException('ID returned an invalid app catalog entry.');
            }

            $enabled = $row['enabled'] ?? true;

            if (! is_bool($enabled)) {
                throw new \RuntimeException('ID returned an invalid app catalog entry.');
            }

            $launch = $row['launch_url'] ?? null;
            $admission = $row['admission_url'] ?? null;

            // An app the organization has opens at its launch URL; one it does not have yet
            // opens its admission (signup) page. Each must have the URL it opens.
            if (($launch !== null && ! self::secureUrl($launch)) || ($admission !== null && ! self::secureUrl($admission))
                || ($enabled && $launch === null) || (! $enabled && $admission === null)) {
                throw new \RuntimeException('ID returned an invalid app catalog entry.');
            }

            $description = $row['description'] ?? null;
            $purpose = $row['purpose'] ?? null;
            $apps[$row['key']] = [
                'key' => $row['key'], 'name' => $row['name'], 'description' => is_string($description) ? $description : null,
                'launch_url' => is_string($launch) ? $launch : null,
                'icon_url' => self::icon($row['icon_url'] ?? null, $issuer),
                'icon_dark_url' => self::icon($row['icon_dark_url'] ?? null, $issuer),
                'purpose' => is_string($purpose) && $purpose !== '' ? mb_substr($purpose, 0, 120) : null,
                'hue' => self::hue($row['hue'] ?? null),
                'admission_url' => is_string($admission) ? $admission : null,
                'enabled' => $enabled,
            ];
        }

        return ['apps' => array_values($apps), 'revision' => $revision, 'refresh_after' => $refresh];
    }

    /**
     * Whether a kept catalog has this SDK's shape: an answer cached by an older version
     * (without `enabled`) is treated as missing and read again.
     */
    public static function isCurrent(mixed $payload): bool
    {
        if (! is_array($payload) || ! is_array($payload['apps'] ?? null)) {
            return false;
        }

        foreach ($payload['apps'] as $app) {
            if (! is_array($app) || ! array_key_exists('enabled', $app) || ! array_key_exists('admission_url', $app)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The app's fixed hue: a hex colour (`#4f46e5`) or a bare OKLCH hue angle (`258`).
     * Anything else is dropped, never passed to a page's styles.
     */
    private static function hue(mixed $hue): ?string
    {
        if (is_int($hue) || is_float($hue)) {
            $hue = (string) $hue;
        }

        return is_string($hue) && preg_match('/\A(#[0-9a-fA-F]{3,8}|\d{1,3}(\.\d{1,3})?)\z/', $hue) === 1 ? $hue : null;
    }

    /** An icon is served by ID itself: null, or an HTTPS URL on the issuer. */
    private static function icon(mixed $url, string $issuer): ?string
    {
        if ($url === null) {
            return null;
        }

        if (! self::secureUrl($url) || ! str_starts_with($url, $issuer.'/')) {
            throw new \RuntimeException('ID returned an invalid catalog icon origin.');
        }

        return $url;
    }

    /** @phpstan-assert-if-true string $url */
    private static function secureUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null && ! str_contains($url, '\\');
    }
}
