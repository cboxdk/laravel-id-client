<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Authz;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Support\Claims;
use Cbox\Id\Client\Support\Discovery;
use Illuminate\Support\Facades\Http;

/**
 * Publishes this app's authorization manifest (its declared roles + permissions) to
 * Cbox ID over the PUSH transport. It mints a client-credentials token with the
 * `apps.manifest` scope, then POSTs the manifest to `{issuer}/api/v1/apps/manifest`.
 * The app owns what roles mean; Cbox ID owns who holds them.
 */
class ManifestPublisher
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
     * The manifest as it will be sent — roles + permissions as declared, plus a
     * `version` that is the first 16 hex characters of {@see checksum()}.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [
            'permissions' => $this->permissions,
            'roles' => $this->roles,
            'version' => substr($this->checksum(), 0, 16),
        ];
    }

    /**
     * The manifest's content checksum, byte-for-byte the one Cbox ID computes.
     *
     * The canonical form is a cross-SDK contract (the `manifest_hash.json` fixture every
     * SDK asserts): permissions `{key, description}` sorted by key; roles `{key, name,
     * description, permissions}` sorted by key with their permissions de-duplicated and
     * sorted; an empty description is null; PHP's default JSON encoding. A staff-only
     * role adds `"tenant_assignable": false` — and ONLY a staff-only role, so every
     * manifest that declares none hashes exactly as it always has and no app re-syncs for
     * nothing. It used to be a hash of the config as written, which changed with the
     * ORDER of the config and matched no other SDK.
     *
     * @throws ClientConfigurationException for a role whose `tenant_assignable` is not a
     *                                      boolean — Cbox ID refuses those, and a staff role
     *                                      declared as the string "false" must fail here,
     *                                      at deploy, not become assignable by every tenant.
     */
    public function checksum(): string
    {
        $permissions = [];

        foreach ($this->permissions as $permission) {
            $permissions[] = [
                'key' => Claims::requiredString($permission, 'key'),
                'description' => Claims::string($permission, 'description'),
            ];
        }

        usort($permissions, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        $roles = [];

        foreach ($this->roles as $role) {
            $key = Claims::requiredString($role, 'key');
            $granted = Claims::strings($role, 'permissions');
            sort($granted);

            $canonical = [
                'key' => $key,
                'name' => Claims::requiredString($role, 'name'),
                'description' => Claims::string($role, 'description'),
                'permissions' => $granted,
            ];

            if (array_key_exists('tenant_assignable', $role)) {
                if (! is_bool($role['tenant_assignable'])) {
                    throw ClientConfigurationException::because("Manifest role \"{$key}\": tenant_assignable must be true or false.");
                }

                if ($role['tenant_assignable'] === false) {
                    $canonical['tenant_assignable'] = false;
                }
            }

            $roles[] = $canonical;
        }

        usort($roles, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        return hash('sha256', (string) json_encode(['permissions' => $permissions, 'roles' => $roles]));
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
