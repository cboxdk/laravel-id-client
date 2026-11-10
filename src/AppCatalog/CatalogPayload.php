<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Illuminate\Http\Client\Response;

/**
 * Cbox ID's catalog envelope, checked: `data` a list of apps, `meta.revision` and
 * `meta.refresh_after`. The person's own read (`/api/v1/me/apps`) and the app's machine
 * read (`/api/v1/app-catalog/for-user`) answer the same shape.
 *
 * @internal
 */
final class CatalogPayload
{
    /**
     * @return array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int}
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
                || ! is_string($row['name'] ?? null) || $row['name'] === '' || isset($apps[$row['key']])
                || ! self::secureUrl($row['launch_url'] ?? null)) {
                throw new \RuntimeException('ID returned an invalid app catalog entry.');
            }

            $description = $row['description'] ?? null;
            $apps[$row['key']] = [
                'key' => $row['key'], 'name' => $row['name'], 'description' => is_string($description) ? $description : null,
                'launch_url' => $row['launch_url'],
                'icon_url' => self::icon($row['icon_url'] ?? null, $issuer),
                'icon_dark_url' => self::icon($row['icon_dark_url'] ?? null, $issuer),
            ];
        }

        return ['apps' => array_values($apps), 'revision' => $revision, 'refresh_after' => $refresh];
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
