<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Grants;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Support\HttpTimeouts;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

/**
 * The endpoints of a Cbox ID issuer, read from its RFC 8414 metadata
 * (`{issuer}/.well-known/oauth-authorization-server`) on first use and kept for the
 * lifetime of this object.
 *
 * A metadata document that cannot be loaded, or that leaves an endpoint out, falls back
 * to Cbox ID's default path for it. A document that names a different issuer is refused
 * (RFC 8414 §3.3): following it would send this client's credentials to another tenant.
 * Every endpoint must be HTTPS, loopback excepted.
 */
class AuthorizationServerMetadata
{
    /** @var array<string, string> Endpoint field → default path below the issuer. */
    public const array DEFAULTS = [
        'token_endpoint' => '/oauth/token',
        'backchannel_authentication_endpoint' => '/oauth/backchannel_authentication',
        'device_authorization_endpoint' => '/oauth/device_authorization',
    ];

    /** @var array<string, mixed>|null */
    private ?array $document = null;

    public function __construct(
        private readonly Factory $http,
        private readonly string $issuer,
        private readonly int $timeout = 10,
    ) {}

    public function issuer(): string
    {
        return rtrim($this->issuer, '/');
    }

    public function tokenEndpoint(): string
    {
        return $this->endpoint('token_endpoint');
    }

    public function backchannelAuthenticationEndpoint(): string
    {
        return $this->endpoint('backchannel_authentication_endpoint');
    }

    public function deviceAuthorizationEndpoint(): string
    {
        return $this->endpoint('device_authorization_endpoint');
    }

    private function endpoint(string $field): string
    {
        $value = $this->document()[$field] ?? null;
        $url = is_string($value) && $value !== '' ? $value : $this->issuer().(self::DEFAULTS[$field] ?? '');

        if (! self::secure($url)) {
            throw ClientConfigurationException::because("The Cbox ID {$field} must be https.");
        }

        return $url;
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        if ($this->document !== null) {
            return $this->document;
        }

        $issuer = $this->issuer();

        if ($issuer === '') {
            throw NotConfigured::key('issuer', 'discover the Cbox ID endpoints');
        }

        if (! self::secure($issuer)) {
            throw ClientConfigurationException::because("The Cbox ID issuer must be https (got '{$issuer}').");
        }

        try {
            $response = $this->http->acceptJson()->withoutRedirecting()->timeout($this->timeout)->connectTimeout(HttpTimeouts::connect())
                ->get($issuer.'/.well-known/oauth-authorization-server');
        } catch (ConnectionException) {
            return $this->document = [];
        }

        $json = $response->successful() ? $response->json() : null;

        if (! is_array($json)) {
            return $this->document = [];
        }

        $claimed = $json['issuer'] ?? null;

        if (! is_string($claimed) || rtrim($claimed, '/') !== $issuer) {
            throw ClientConfigurationException::because("The Cbox ID metadata document is for a different issuer than {$issuer}.");
        }

        $document = [];

        foreach ($json as $key => $value) {
            $document[(string) $key] = $value;
        }

        return $this->document = $document;
    }

    private static function secure(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true));
    }
}
