<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Grants;

use Cbox\Id\Client\Contracts\BackchannelAuthentication;
use Cbox\Id\Client\Exceptions\GrantFailed;
use Cbox\Id\Client\Exceptions\InvalidBindingMessage;
use Cbox\Id\Client\ValueObjects\PendingBackchannelAuthentication;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;
use Illuminate\Http\Client\Factory;

/**
 * OpenID CIBA Core 1.0, poll or ping mode, against a Cbox ID issuer.
 *
 * Outside a Laravel application (a CLI, a worker):
 *
 *     $ciba = HttpBackchannelAuthentication::make('https://id.example.com',
 *         ClientAuthentication::clientSecretBasic($clientId, $secret));
 */
class HttpBackchannelAuthentication extends PollingGrant implements BackchannelAuthentication
{
    public const string GRANT_TYPE = 'urn:openid:params:grant-type:ciba';

    /** The longest binding message Cbox ID shows. */
    public const int BINDING_MESSAGE_MAX = 255;

    /** CIBA Core §7.1: a client_notification_token is at most 1024 characters. */
    public const int CLIENT_NOTIFICATION_TOKEN_MAX = 1024;

    public static function make(string $issuer, ClientAuthentication $client, ?Timer $timer = null, int $timeout = 10): self
    {
        $http = new Factory;

        return new self($http, new AuthorizationServerMetadata($http, $issuer, $timeout), $client, $timer ?? new SystemTimer, $timeout);
    }

    public function start(
        string $loginHint,
        string $scope,
        string $bindingMessage,
        ?string $resource = null,
        ?int $requestedExpiry = null,
        ?string $nonce = null,
        ?string $clientNotificationToken = null,
    ): PendingBackchannelAuthentication {
        $loginHint = trim($loginHint);

        if ($loginHint === '') {
            throw new GrantFailed('A backchannel request needs a login_hint: a Cbox ID user id or a confirmed email.', 'invalid_request');
        }

        if (mb_strlen($bindingMessage) > self::BINDING_MESSAGE_MAX) {
            throw new InvalidBindingMessage(
                'The binding_message is longer than '.self::BINDING_MESSAGE_MAX.' characters.',
                'invalid_binding_message',
            );
        }

        if ($clientNotificationToken !== null && mb_strlen($clientNotificationToken) > self::CLIENT_NOTIFICATION_TOKEN_MAX) {
            throw new GrantFailed('The client_notification_token is longer than '.self::CLIENT_NOTIFICATION_TOKEN_MAX.' characters.', 'invalid_request');
        }

        if ($requestedExpiry !== null && $requestedExpiry < 1) {
            throw new GrantFailed('requested_expiry must be a positive number of seconds.', 'invalid_request');
        }

        $params = array_filter([
            'login_hint' => $loginHint,
            'scope' => trim($scope),
            'binding_message' => $bindingMessage,
            'resource' => $resource ?? '',
            'requested_expiry' => $requestedExpiry !== null ? (string) $requestedExpiry : '',
            'nonce' => $nonce ?? '',
            'client_notification_token' => $clientNotificationToken ?? '',
        ], static fn (string $value): bool => $value !== '');

        $response = $this->send($this->metadata->backchannelAuthenticationEndpoint(), $params);

        if (! $response->successful()) {
            throw $this->startFailure($response);
        }

        $body = self::body($response);
        $authReqId = self::nonEmptyString($body['auth_req_id'] ?? null);
        $expiresIn = self::positiveInt($body['expires_in'] ?? null);
        $interval = $body['interval'] ?? self::DEFAULT_INTERVAL;

        if ($authReqId === null || $expiresIn === null || ! is_int($interval) || $interval < 0) {
            throw new GrantFailed('Cbox ID returned an invalid backchannel authentication response.');
        }

        return new PendingBackchannelAuthentication(
            $authReqId,
            $expiresIn,
            $interval,
            $this->timer->now() + $expiresIn,
            $resource !== null && $resource !== '' ? $resource : null,
        );
    }

    public function poll(PendingBackchannelAuthentication $request, ?int $interval = null): PollResult
    {
        if ($request->authReqId === '') {
            throw new GrantFailed('The backchannel request has no auth_req_id.', 'invalid_request');
        }

        return $this->pollToken(array_filter([
            'grant_type' => self::GRANT_TYPE,
            'auth_req_id' => $request->authReqId,
            'resource' => $request->resource ?? '',
        ], static fn (string $value): bool => $value !== ''), $interval ?? $request->interval);
    }

    public function waitForToken(PendingBackchannelAuthentication $request, ?int $maxWait = null): TokenResult
    {
        return $this->wait(
            fn (int $interval): PollResult => $this->poll($request, $interval),
            $request->expiresAt,
            $request->interval,
            $maxWait,
        );
    }
}
