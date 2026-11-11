<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;

/**
 * Where the SDK's own API calls go: the app catalog, the manifest push, service
 * connections and pipe leases.
 *
 * Without `CBOX_ID_API_URL` (`cbox-id-client.api_url`) nothing changes: the calls go to
 * `{issuer}/api/v1/…`, the environment's own host, with today's paths. With it set (for
 * Cbox's own deployment `https://api.cboxid.com`) they go to `{api_url}/v1/…` with the
 * `/v1` paths, and Cbox ID works out the environment from the credential.
 *
 * The protocol endpoints (`/oauth/*`, `/.well-known/*`, the Frontend API) stay on the
 * issuer either way.
 */
final readonly class ApiUrl
{
    private function __construct(
        /** The base every path is appended to: `{api_url}/v1` or `{issuer}/api/v1`. */
        public string $base,
        /** Whether the calls go to the `/v1` API (so take the `/v1` paths). */
        public bool $v1,
    ) {}

    /**
     * The base for this issuer. `$apiUrl` null reads `cbox-id-client.api_url`; empty means
     * the issuer's `/api/v1`.
     */
    public static function for(string $issuer, ?string $apiUrl = null): self
    {
        $api = rtrim(trim($apiUrl ?? self::configured()), '/');

        if ($api !== '') {
            // Accept the URL with or without its version: `https://api.cboxid.com/v1` and
            // `https://api.cboxid.com` are the same setting.
            return new self((str_ends_with($api, '/v1') ? substr($api, 0, -3) : $api).'/v1', true);
        }

        return new self(rtrim($issuer, '/').'/api/v1', false);
    }

    /** `cbox-id-client.api_url`, or the empty string. */
    public static function configured(): string
    {
        $container = Container::getInstance();
        $config = $container->bound('config') ? $container->make('config') : null;
        $value = $config instanceof Repository ? $config->get('cbox-id-client.api_url') : null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * The URL of a call: `$v1Path` on the `/v1` API, `$legacyPath` (default the same path)
     * on `{issuer}/api/v1`. Paths start with a slash.
     */
    public function to(string $v1Path, ?string $legacyPath = null): string
    {
        return $this->base.($this->v1 ? $v1Path : ($legacyPath ?? $v1Path));
    }
}
