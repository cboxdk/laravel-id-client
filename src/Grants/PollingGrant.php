<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Grants;

use Cbox\Id\Client\Exceptions\AuthorizationDenied;
use Cbox\Id\Client\Exceptions\AuthorizationExpired;
use Cbox\Id\Client\Exceptions\GrantFailed;
use Cbox\Id\Client\Exceptions\GrantRateLimited;
use Cbox\Id\Client\Exceptions\GrantTimedOut;
use Cbox\Id\Client\Exceptions\InvalidBindingMessage;
use Cbox\Id\Client\Exceptions\InvalidClient;
use Cbox\Id\Client\Exceptions\InvalidGrant;
use Cbox\Id\Client\Exceptions\InvalidTarget;
use Cbox\Id\Client\Exceptions\UnknownUser;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

/**
 * What the CIBA and device grants share: client authentication on every request, the
 * token-endpoint poll with its `authorization_pending` / `slow_down` protocol, the
 * mapping of OAuth errors to exceptions, and the blocking wait loop.
 */
abstract class PollingGrant
{
    /** Seconds added to the interval on every `slow_down` (RFC 8628 §3.5, CIBA Core §11). */
    public const int SLOW_DOWN_STEP = 5;

    /** The interval when Cbox ID names none (RFC 8628 §3.2). */
    public const int DEFAULT_INTERVAL = 5;

    public function __construct(
        protected readonly Factory $http,
        protected readonly AuthorizationServerMetadata $metadata,
        protected readonly ClientAuthentication $client,
        protected readonly Timer $timer = new SystemTimer,
        protected readonly int $timeout = 10,
    ) {}

    /**
     * POST an authenticated form to a Cbox ID endpoint.
     *
     * @param  array<string, string>  $params
     */
    protected function send(string $url, array $params): Response
    {
        $request = $this->client->authenticate(
            $this->http->asForm()->acceptJson()->withoutRedirecting()->timeout($this->timeout),
        );

        try {
            return $request->post($url, array_merge($params, $this->client->parameters($this->metadata->tokenEndpoint())));
        } catch (ConnectionException) {
            throw new GrantFailed('Cbox ID could not be reached.', 'temporarily_unavailable');
        }
    }

    /**
     * One token-endpoint poll.
     *
     * @param  array<string, string>  $params
     */
    protected function pollToken(array $params, int $interval): PollResult
    {
        $response = $this->send($this->metadata->tokenEndpoint(), $params);
        $body = self::body($response);

        if ($response->successful()) {
            return new PollResult($this->tokens($body), $interval);
        }

        $error = $body['error'] ?? null;

        if ($error === 'authorization_pending') {
            return new PollResult(null, $interval);
        }

        if ($error === 'slow_down' || $response->status() === 429) {
            return new PollResult(null, max($interval + self::SLOW_DOWN_STEP, $this->retryAfter($response) ?? 0), true);
        }

        throw $this->failure($response, $body);
    }

    /**
     * Sleep, poll, repeat — until tokens, an error, the request's expiry or `$maxWait`.
     * It never sleeps past either deadline: when the next poll would land after one, it
     * stops before sleeping.
     *
     * @param  Closure(int): PollResult  $poll
     */
    protected function wait(Closure $poll, int $expiresAt, int $interval, ?int $maxWait): TokenResult
    {
        $started = $this->timer->now();
        $interval = max(1, $interval);

        while (true) {
            $now = $this->timer->now();

            if ($now + $interval > $expiresAt) {
                throw new AuthorizationExpired('The request expired before the person answered.', 'expired_token');
            }

            if ($maxWait !== null && $now + $interval - $started > $maxWait) {
                throw new GrantTimedOut("Nobody answered within {$maxWait} seconds.", 'authorization_pending');
            }

            $this->timer->sleep($interval);
            $result = $poll($interval);

            if ($result->tokens !== null) {
                return $result->tokens;
            }

            $interval = max(1, $result->interval);
        }
    }

    /** The exception for a refused start or poll. */
    protected function startFailure(Response $response): GrantFailed
    {
        $body = self::body($response);

        if ($response->status() === 429) {
            $description = self::description($body);

            return new GrantRateLimited(
                'Cbox ID is rate limiting new requests for this person or client.',
                $this->retryAfter($response) ?? self::DEFAULT_INTERVAL,
                $description,
            );
        }

        return $this->failure($response, $body);
    }

    /** @param array<string, mixed> $body */
    protected function failure(Response $response, array $body): GrantFailed
    {
        $error = $body['error'] ?? null;
        $error = is_string($error) && preg_match('/^[a-z0-9_.:-]{1,64}$/i', $error) === 1 ? $error : 'server_error';
        $description = self::description($body);
        $message = "Cbox ID refused the request ({$error})".($description !== null ? ": {$description}" : '.');
        $status = $response->status();

        return match ($error) {
            'access_denied' => new AuthorizationDenied($message, $error, $description, $status),
            'expired_token' => new AuthorizationExpired($message, $error, $description, $status),
            'invalid_grant' => new InvalidGrant($message, $error, $description, $status),
            'invalid_target' => new InvalidTarget($message, $error, $description, $status),
            'invalid_client' => new InvalidClient($message, $error, $description, $status),
            'unknown_user_id' => new UnknownUser($message, $error, $description, $status),
            'invalid_binding_message' => new InvalidBindingMessage($message, $error, $description, $status),
            default => new GrantFailed($message, $error, $description, $status),
        };
    }

    /** @return array<string, mixed> */
    protected static function body(Response $response): array
    {
        $json = $response->json();
        $body = [];

        foreach (is_array($json) ? $json : [] as $key => $value) {
            $body[(string) $key] = $value;
        }

        return $body;
    }

    protected static function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    protected static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $body */
    private function tokens(array $body): TokenResult
    {
        $accessToken = self::nonEmptyString($body['access_token'] ?? null);
        $tokenType = self::nonEmptyString($body['token_type'] ?? null);
        $expiresIn = $body['expires_in'] ?? null;

        if ($accessToken === null || $tokenType === null || ($expiresIn !== null && ! is_int($expiresIn))) {
            throw new GrantFailed('Cbox ID returned an invalid token response.');
        }

        return new TokenResult(
            $accessToken,
            $tokenType,
            $expiresIn,
            self::nonEmptyString($body['scope'] ?? null),
            self::nonEmptyString($body['refresh_token'] ?? null),
            self::nonEmptyString($body['id_token'] ?? null),
        );
    }

    /** Seconds from a `Retry-After` header: delta-seconds or an HTTP date. */
    private function retryAfter(Response $response): ?int
    {
        $value = trim($response->header('Retry-After'));

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $at = strtotime($value);

        return $at === false ? null : max(0, $at - $this->timer->now());
    }

    /** @param array<string, mixed> $body */
    private static function description(array $body): ?string
    {
        $description = $body['error_description'] ?? null;

        return is_string($description) && $description !== '' ? mb_substr($description, 0, 300) : null;
    }
}
