<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Authz;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Support\Discovery;
use Illuminate\Support\Facades\Http;

/**
 * Publishes this app's authorization manifest (its declared roles + permissions) to
 * Cbox ID over the PUSH transport. It mints a client-credentials token with the
 * `apps.manifest` scope, then POSTs the manifest to `{issuer}/api/v1/apps/manifest`.
 * The app owns what roles mean; Cbox ID owns who holds them.
 */
final class ManifestPublisher
{
    /**
     * @param  list<array<string, mixed>>  $permissions
     * @param  list<array<string, mixed>>  $roles
     */
    public function __construct(
        private readonly Discovery $discovery,
        private readonly string $issuer,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly array $permissions,
        private readonly array $roles,
        private readonly int $timeout = 10,
    ) {}

    /**
     * The manifest as it will be sent — roles + permissions plus a content-derived
     * version, so republishing an unchanged catalog is a no-op server-side.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $body = [
            'permissions' => $this->permissions,
            'roles' => $this->roles,
        ];

        $body['version'] = substr(hash('sha256', (string) json_encode($body)), 0, 16);

        return $body;
    }

    /**
     * Push the manifest. Returns the server's sync summary.
     *
     * @return array<string, mixed>
     */
    public function publish(): array
    {
        if ($this->issuer === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw ClientConfigurationException::because('Publishing a manifest needs issuer, client_id and client_secret.');
        }

        $response = Http::withToken($this->accessToken())
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->issuer, '/').'/api/v1/apps/manifest', $this->manifest());

        if (! $response->successful()) {
            throw ClientConfigurationException::because('Manifest push failed: HTTP '.$response->status().' '.$response->body());
        }

        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        $summary = [];
        foreach ($json as $key => $value) {
            if (is_string($key)) {
                $summary[$key] = $value;
            }
        }

        return $summary;
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post($this->discovery->endpoint('token_endpoint'), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'apps.manifest',
            ]);

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw ClientConfigurationException::because('Could not obtain an apps.manifest access token — check the client credentials and that the client holds the apps.manifest scope.');
        }

        return $token;
    }
}
