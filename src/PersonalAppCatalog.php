<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\AppCatalog\CatalogPayload;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Support\HttpTimeouts;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/** The environment's published, person-filtered account catalog. */
final readonly class PersonalAppCatalog
{
    public function __construct(private Factory $http, private string $issuer) {}

    /** @return array{apps:list<array{key:string,name:string,description:?string,launch_url:string,icon_url:?string,icon_dark_url:?string}>,revision:string,refresh_after:int} */
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
        $catalog = CatalogPayload::parse($response, $issuer);

        return [...$catalog, 'refresh_after' => min(60, $catalog['refresh_after'], max(0, $grant->expiresAt - time()))];
    }
}
