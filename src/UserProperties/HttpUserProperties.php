<?php

declare(strict_types=1);

namespace Cbox\Id\Client\UserProperties;

use Cbox\Id\Client\Contracts\UserProperties;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\UserPropertiesFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use InvalidArgumentException;
use JsonException;
use stdClass;

class HttpUserProperties implements UserProperties
{
    public function __construct(private readonly Factory $http, private readonly string $issuer, private readonly int $timeout = 10) {}

    public function read(string $user, string $namespace, PropertyToken $token): PropertyDocument
    {
        return $this->request('GET', $user, $namespace, $token);
    }

    public function patch(string $user, string $namespace, PropertyPatch $patch, PropertyToken $token): PropertyDocument
    {
        return $this->request('PATCH', $user, $namespace, $token, $patch);
    }

    private function request(string $method, string $user, string $namespace, PropertyToken $token, ?PropertyPatch $patch = null): PropertyDocument
    {
        $parts = parse_url($this->issuer);
        if (! is_array($parts) || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (($parts['scheme'] ?? '') !== 'https' && ! (($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['127.0.0.1', 'localhost', '[::1]'], true)))) {
            throw ClientConfigurationException::because('User properties require a secure configured ID issuer.');
        }
        if ($user === '' || strlen($user) > 256 || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $namespace) || $token->accessToken === '') {
            throw new InvalidArgumentException('User properties require a subject, namespace and access token.');
        }
        $url = rtrim($this->issuer, '/').'/oauth/users/'.rawurlencode($user).'/properties/'.rawurlencode($namespace);
        $request = $this->http->acceptJson()->withoutRedirecting()->timeout($this->timeout)->connectTimeout(min(3, $this->timeout));
        $request = $request->withToken($token->accessToken, $token->proof === null ? 'Bearer' : 'DPoP');
        if ($token->proof !== null) {
            $request = $request->withHeaders(['DPoP' => ($token->proof)($method, $url, $token->accessToken)]);
        }
        $options = [];
        if ($patch !== null) {
            $body = json_encode(['version' => $patch->version, 'set' => $patch->set, 'remove' => $patch->remove], JSON_THROW_ON_ERROR);
            if (strlen($body) > 32768) {
                throw new InvalidArgumentException('A user property patch must fit within 32 KiB.');
            }
            $request = $request->withBody($body, 'application/json');
        }
        try {
            $response = $request->send($method, $url, $options);
        } catch (ConnectionException) {
            throw new UserPropertiesFailed(null, 'unavailable');
        }
        if (! $response->successful()) {
            // No remote response text or token-bearing exception chain escapes here.
            throw new UserPropertiesFailed($response->status(), match ($response->status()) {
                401 => 'unauthorized', 403 => 'forbidden', 404 => 'not_found',
                409 => 'version_conflict', 422 => 'invalid_properties', 429 => 'rate_limited',
                default => 'unavailable',
            });
        }
        try {
            $body = strlen($response->body()) <= 65536 ? json_decode($response->body(), false, 20, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $body = null;
        }
        if (! $body instanceof stdClass || ($body->user_id ?? null) !== $user || ($body->namespace ?? null) !== $namespace
            || ! ($body->data ?? null) instanceof stdClass || ! is_int($body->version ?? null) || $body->version < 0) {
            throw new UserPropertiesFailed(502, 'malformed_response');
        }

        return new PropertyDocument($user, $namespace, $body->data, $body->version);
    }
}
