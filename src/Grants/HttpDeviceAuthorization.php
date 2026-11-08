<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Grants;

use Cbox\Id\Client\Contracts\DeviceAuthorization;
use Cbox\Id\Client\Exceptions\GrantFailed;
use Cbox\Id\Client\ValueObjects\PendingDeviceAuthorization;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;
use Illuminate\Http\Client\Factory;

/**
 * The RFC 8628 device authorization grant against a Cbox ID issuer.
 *
 * Outside a Laravel application (a CLI):
 *
 *     $device = HttpDeviceAuthorization::make('https://id.example.com', ClientAuthentication::none($clientId));
 */
class HttpDeviceAuthorization extends PollingGrant implements DeviceAuthorization
{
    public const string GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:device_code';

    public static function make(string $issuer, ClientAuthentication $client, ?Timer $timer = null, int $timeout = 10): self
    {
        $http = new Factory;

        return new self($http, new AuthorizationServerMetadata($http, $issuer, $timeout), $client, $timer ?? new SystemTimer, $timeout);
    }

    public function start(string $scope = '', ?string $resource = null): PendingDeviceAuthorization
    {
        $params = array_filter([
            'scope' => trim($scope),
            'resource' => $resource ?? '',
        ], static fn (string $value): bool => $value !== '');

        $response = $this->send($this->metadata->deviceAuthorizationEndpoint(), $params);

        if (! $response->successful()) {
            throw $this->startFailure($response);
        }

        $body = self::body($response);
        $deviceCode = self::nonEmptyString($body['device_code'] ?? null);
        $userCode = self::nonEmptyString($body['user_code'] ?? null);
        $verificationUri = self::nonEmptyString($body['verification_uri'] ?? null);
        $complete = $body['verification_uri_complete'] ?? null;
        $expiresIn = self::positiveInt($body['expires_in'] ?? null);
        $interval = $body['interval'] ?? self::DEFAULT_INTERVAL;

        if ($deviceCode === null || $userCode === null || $verificationUri === null || $expiresIn === null
            || ($complete !== null && ! is_string($complete)) || ! is_int($interval) || $interval < 0) {
            throw new GrantFailed('Cbox ID returned an invalid device authorization response.');
        }

        return new PendingDeviceAuthorization(
            $deviceCode,
            $userCode,
            $verificationUri,
            $complete !== '' ? $complete : null,
            $expiresIn,
            $interval,
            $this->timer->now() + $expiresIn,
            $resource !== null && $resource !== '' ? $resource : null,
        );
    }

    public function poll(PendingDeviceAuthorization $authorization, ?int $interval = null): PollResult
    {
        if ($authorization->deviceCode === '') {
            throw new GrantFailed('The device authorization has no device_code.', 'invalid_request');
        }

        return $this->pollToken(array_filter([
            'grant_type' => self::GRANT_TYPE,
            'device_code' => $authorization->deviceCode,
            'resource' => $authorization->resource ?? '',
        ], static fn (string $value): bool => $value !== ''), $interval ?? $authorization->interval);
    }

    public function waitForToken(PendingDeviceAuthorization $authorization, ?int $maxWait = null): TokenResult
    {
        return $this->wait(
            fn (int $interval): PollResult => $this->poll($authorization, $interval),
            $authorization->expiresAt,
            $authorization->interval,
            $maxWait,
        );
    }
}
