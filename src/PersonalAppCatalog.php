<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Support\HttpTimeouts;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/** The environment's published, person-filtered account catalog. */
final readonly class PersonalAppCatalog
{
    public function __construct(private Factory $http, private string $issuer) {}

    /** @return array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string}>,revision:string,refresh_after:int} */
    public function read(ResourceAuthorizationResult $grant, string $subject, string $organization): array
    {
        $issuer = rtrim($this->issuer, '/');
        if ($grant->subject !== $subject || $grant->organizationId !== $organization || $grant->expiresAt <= time()
            || $grant->resource !== $issuer.'/mcp' || ! in_array('account:apps:read', $grant->scopes, true)) {
            throw AuthenticationFailed::because('Account catalog authorization does not match the current session.');
        }
        $url = $issuer.'/api/v1/me/apps';
        try {
            $response = $this->http->acceptJson()->withoutRedirecting()->timeout(5)->connectTimeout(HttpTimeouts::connect())
                ->withHeaders($grant->requestHeaders('GET', $url))->get($url, ['organization_id' => $organization]);
        } catch (ConnectionException) {
            throw new \RuntimeException('ID app catalog is unavailable.');
        }
        if ($response->unauthorized() || $response->forbidden()) {
            throw AuthenticationFailed::because('ID refused this app catalog grant.');
        }
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
            $icon = $row['icon_url'] ?? null;
            if ($icon !== null && (! self::secureUrl($icon) || ! str_starts_with($icon, $issuer.'/'))) {
                throw new \RuntimeException('ID returned an invalid catalog icon origin.');
            }
            $description = $row['description'] ?? null;
            $apps[$row['key']] = ['key' => $row['key'], 'name' => $row['name'], 'description' => is_string($description) ? $description : null,
                'launch_url' => $row['launch_url'], 'icon_url' => $icon];
        }

        return ['apps' => array_values($apps), 'revision' => $revision, 'refresh_after' => min(60, $refresh, max(0, $grant->expiresAt - time()))];
    }

    /** @phpstan-assert-if-true string $url */
    private static function secureUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null && ! str_contains($url, '\\');
    }
}
