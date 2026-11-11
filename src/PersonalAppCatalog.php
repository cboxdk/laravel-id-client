<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\AppCatalog\CatalogPayload;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Support\ApiUrl;
use Cbox\Id\Client\Support\HttpTimeouts;
use Cbox\Id\Client\Support\PublicId;
use Cbox\Id\Client\Support\Scopes;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/** The environment's published, person-filtered account catalog. */
final readonly class PersonalAppCatalog
{
    public function __construct(private Factory $http, private string $issuer) {}

    /** @return array{apps:list<array{key:string,name:string,description:?string,launch_url:?string,icon_url:?string,icon_dark_url:?string,purpose:?string,hue:?string,admission_url:?string,enabled:bool}>,revision:string,refresh_after:int} */
    public function read(ResourceAuthorizationResult $grant, string $subject, string $organization): array
    {
        $issuer = rtrim($this->issuer, '/');
        if (! PublicId::same($grant->subject, $subject) || ! PublicId::same($grant->organizationId, $organization) || $grant->expiresAt <= time()
            || $grant->resource !== $issuer.'/mcp' || ! Scopes::holds($grant->scopes, 'account:apps:read')) {
            throw AuthenticationFailed::because('Account catalog authorization does not match the current session.');
        }
        // The grant is bound to its resource's origin and never sent anywhere else: it goes
        // to the /v1 API only when it was issued for it, and to the issuer otherwise.
        $api = ApiUrl::for($issuer);
        $url = $api->v1 && self::origin($api->base) === self::origin($grant->resource) ? $api->to('/me/apps') : $issuer.'/api/v1/me/apps';
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

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return is_array($parts) ? strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').':'.($parts['port'] ?? 443)) : '';
    }
}
