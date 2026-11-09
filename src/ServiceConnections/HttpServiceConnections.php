<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ServiceConnections;

use Cbox\Id\Client\Contracts\ServiceConnections;
use Cbox\Id\Client\Exceptions\ServiceConnectionFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\HttpTimeouts;
use Cbox\Id\Client\ValueObjects\ServiceConnection;
use Cbox\Id\Client\ValueObjects\ServiceConnectionLease;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

class HttpServiceConnections implements ServiceConnections
{
    public function __construct(private readonly Factory $http, private readonly IdentityClient $identity, private readonly string $issuer, private readonly string $clientId) {}

    public function connect(#[\SensitiveParameter] string $accessToken, string $organization, string $service, string $audience): ServiceConnection
    {
        $body = $this->request($accessToken, 'POST', $service);
        if (($body['organization_id'] ?? null) !== $organization || ($body['service'] ?? null) !== $service
            || ($body['audience'] ?? null) !== $audience || ($body['consumer_client_id'] ?? null) !== $this->clientId
            || ! array_key_exists('revoked_at', $body) || $body['revoked_at'] !== null
            || ! is_string($body['id'] ?? null) || $body['id'] === '') {
            throw new ServiceConnectionFailed('ID returned a connection outside the requested scope.');
        }
        $scopes = $this->scopes($body['scopes'] ?? null);

        return new ServiceConnection($body['id'], $organization, $service, $this->clientId, $audience, $scopes);
    }

    public function status(ServiceConnection $connection): bool
    {
        $this->owned($connection);
        $body = $this->request($this->identity->machineToken(['service_connections.lease']), 'POST', $connection->id, 'status', machine: true);
        if (($body['active'] ?? null) === false) {
            return false;
        }
        if (($body['active'] ?? null) !== true || ($body['id'] ?? null) !== $connection->id
            || ($body['organization_id'] ?? null) !== $connection->organizationId || ($body['service'] ?? null) !== $connection->service
            || ($body['consumer_client_id'] ?? null) !== $connection->consumerClientId || ($body['audience'] ?? null) !== $connection->audience
            || ! array_key_exists('revoked_at', $body) || $body['revoked_at'] !== null
            || $this->scopes($body['scopes'] ?? null) !== $connection->scopes) {
            throw new ServiceConnectionFailed('ID returned an invalid connection status.');
        }

        return true;
    }

    public function lease(ServiceConnection $connection): ServiceConnectionLease
    {
        $this->owned($connection);
        $body = $this->request($this->identity->machineToken(['service_connections.lease']), 'POST', $connection->id, 'token', machine: true);
        if (! is_string($body['access_token'] ?? null) || $body['access_token'] === ''
            || ($body['token_type'] ?? null) !== 'Bearer' || ($body['audience'] ?? null) !== $connection->audience
            || ! is_int($body['expires_in'] ?? null) || $body['expires_in'] < 1
            || ! is_string($body['scope'] ?? null) || $this->scopes(explode(' ', $body['scope'])) !== $connection->scopes) {
            throw new ServiceConnectionFailed('ID returned an invalid connection lease.');
        }

        return new ServiceConnectionLease($body['access_token'], time() + $body['expires_in'], $connection);
    }

    public function revoke(#[\SensitiveParameter] string $accessToken, ServiceConnection $connection): void
    {
        $this->owned($connection);
        $body = $this->request($accessToken, 'DELETE', $connection->id);
        if (($body['revoked'] ?? null) !== true) {
            throw new ServiceConnectionFailed('ID did not confirm connection revocation.');
        }
    }

    private function owned(ServiceConnection $connection): void
    {
        if ($connection->consumerClientId !== $this->clientId || $connection->id === '') {
            throw new ServiceConnectionFailed('The connection belongs to a different client.');
        }
    }

    /** @return list<string> */
    private function scopes(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new ServiceConnectionFailed('ID returned invalid connection scopes.');
        }
        $scopes = [];
        foreach ($value as $scope) {
            if (! is_string($scope) || $scope === '' || preg_match('/\s/', $scope) || in_array($scope, $scopes, true)) {
                throw new ServiceConnectionFailed('ID returned invalid connection scopes.');
            }
            $scopes[] = $scope;
        }
        sort($scopes);

        return $scopes;
    }

    /** @return array<string, mixed> */
    private function request(#[\SensitiveParameter] string $token, string $method, string $path, string $suffix = '', bool $machine = false): array
    {
        $issuer = rtrim($this->issuer, '/');
        if ($token === '' || $this->clientId === '' || filter_var($issuer, FILTER_VALIDATE_URL) === false
            || parse_url($issuer, PHP_URL_SCHEME) !== 'https' || parse_url($issuer, PHP_URL_USER) !== null
            || parse_url($issuer, PHP_URL_QUERY) !== null || parse_url($issuer, PHP_URL_FRAGMENT) !== null || str_contains($issuer, '\\')) {
            throw new ServiceConnectionFailed('Service connections require a configured HTTPS issuer and client.');
        }
        // Only the fixed protocol suffix may introduce a path separator.
        $path = rawurlencode($path).($suffix === '' ? '' : '/'.$suffix);
        try {
            $response = $this->http->acceptJson()->withToken($token)->withoutRedirecting()->connectTimeout(HttpTimeouts::connect())->timeout(20)
                ->send($method, $issuer.'/api/v1/service-connections/'.$path);
        } catch (ConnectionException) {
            throw new ServiceConnectionFailed('ID service connections are unavailable.');
        }
        if ($machine && $response->status() === 401) {
            // The kept machine token was refused: the next call asks Cbox ID for a new one.
            $this->identity->forgetMachineToken(['service_connections.lease']);
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || array_is_list($body)) {
            throw new ServiceConnectionFailed('ID refused the connection request or returned an invalid response.');
        }
        $result = [];
        foreach ($body as $key => $value) {
            if (! is_string($key)) {
                throw new ServiceConnectionFailed('ID returned an invalid connection response.');
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
