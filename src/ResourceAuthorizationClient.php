<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Support\Pkce;
use Cbox\Id\Client\ValueObjects\PendingResourceAuthorization;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Acquires a dedicated resource bearer for an already authenticated person. */
final readonly class ResourceAuthorizationClient
{
    /** @param array<string,mixed> $config */
    public function __construct(private array $config, private Discovery $discovery) {}

    /**
     * Context is retained server-side; never use callback query parameters as a return target.
     * Use a server-side session driver and session blocking on authorize/callback routes.
     *
     * @param  list<string>  $scopes
     * @param  array<string,string>  $context
     */
    public function redirect(string $resource, string $organizationId, string $expectedSubject, string $redirectUri, array $scopes, array $context = []): RedirectResponse
    {
        if (! filter_var($resource, FILTER_VALIDATE_URL) || parse_url($resource, PHP_URL_SCHEME) !== 'https'
            || parse_url($resource, PHP_URL_FRAGMENT) !== null || rtrim($resource, '/') === rtrim($this->required('issuer'), '/')
            || parse_url($resource, PHP_URL_USER) !== null
            || ! filter_var($redirectUri, FILTER_VALIDATE_URL) || parse_url($redirectUri, PHP_URL_SCHEME) !== 'https'
            || parse_url($redirectUri, PHP_URL_FRAGMENT) !== null || parse_url($redirectUri, PHP_URL_USER) !== null || $organizationId === '' || $expectedSubject === '' || $scopes === []) {
            throw AuthenticationFailed::because('Invalid resource authorization parameters.');
        }
        foreach ($scopes as $scope) {
            if (! preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/D', $scope) || in_array($scope, ['openid', 'offline_access'], true)) {
                throw AuthenticationFailed::because('Resource authorization requires explicit API scopes.');
            }
        }
        $this->required('client_secret');
        if (config('session.driver') === 'cookie') {
            throw AuthenticationFailed::because('Resource authorization requires server-side sessions.');
        }
        if (parse_url($this->required('issuer'), PHP_URL_SCHEME) !== 'https') {
            throw AuthenticationFailed::because('Resource authorization requires HTTPS identity.');
        }
        $outstanding = session()->get('cbox-resource', []);
        if (is_array($outstanding)) {
            foreach ($outstanding as $key => $entry) {
                $pendingEntry = PendingResourceAuthorization::fromStored($entry);
                if ($pendingEntry === null || $pendingEntry->expires < time()) {
                    session()->forget('cbox-resource.'.$key);
                }
            }
            $remaining = session()->get('cbox-resource', []);
            if (is_array($remaining) && count($remaining) >= 10) {
                throw AuthenticationFailed::because('Too many outstanding resource authorizations.');
            }
        }
        $state = bin2hex(random_bytes(32));
        $verifier = Pkce::verifier();
        session()->put('cbox-resource.'.$state, (new PendingResourceAuthorization($resource, $organizationId, $expectedSubject, $redirectUri, $scopes, $context, $verifier, time() + 600))->toArray());

        $endpoint = $this->endpoint('authorization_endpoint');

        return new RedirectResponse($endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query([
            'response_type' => 'code', 'client_id' => $this->required('client_id'), 'redirect_uri' => $redirectUri,
            'resource' => $resource, 'organization' => $organizationId, 'scope' => implode(' ', $scopes),
            'state' => $state, 'code_challenge' => Pkce::challenge($verifier), 'code_challenge_method' => 'S256',
        ]));
    }

    public function authenticate(Request $request): ResourceAuthorizationResult
    {
        if (config('session.driver') === 'cookie') {
            throw AuthenticationFailed::because('Resource authorization requires server-side sessions.');
        }
        $state = $request->query('state');
        if (! is_string($state) || ! preg_match('/^[a-f0-9]{64}$/D', $state)) {
            throw InvalidState::because('Invalid resource authorization state.');
        }
        $pending = PendingResourceAuthorization::fromStored(session()->pull('cbox-resource.'.$state));
        if (! $pending instanceof PendingResourceAuthorization || $pending->expires < time()) {
            throw InvalidState::because('Expired or consumed resource authorization state.');
        }
        $code = $request->query('code');
        if ($request->has('error') || ! is_string($code) || $code === '' || ($request->has('iss') && $request->query('iss') !== $this->required('issuer'))) {
            throw AuthenticationFailed::because('Resource authorization was refused.');
        }
        try {
            $response = Http::asForm()->withoutRedirecting()->timeout(10)->post($this->endpoint('token_endpoint'), [
                'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $this->required('client_id'),
                'client_secret' => $this->required('client_secret'), 'redirect_uri' => $pending->redirectUri,
                'code_verifier' => $pending->verifier, 'resource' => $pending->resource,
            ]);
            $bearer = $response->json('access_token');
            $tokenType = $response->json('token_type');
            if (! $response->successful() || ! is_string($bearer) || ! is_string($tokenType) || strcasecmp($tokenType, 'Bearer') !== 0) {
                throw AuthenticationFailed::because('Resource token exchange failed.');
            }
            $this->endpoint('jwks_uri');
            $verified = (new AccessTokenVerifier($this->discovery, $this->required('issuer'), $pending->resource))->verify($bearer, $pending->scopes);
            if ($verified->subject !== $pending->expectedSubject || $verified->organizationId !== $pending->organizationId
                || $verified->clientId !== $this->required('client_id') || $verified->subject === $verified->clientId
                || isset($verified->claims['act']) || $verified->claims['aud'] !== $pending->resource || $verified->expiresAt <= time()) {
                throw AuthenticationFailed::because('Resource token binding did not match.');
            }

            return new ResourceAuthorizationResult($bearer, $verified->subject, $pending->organizationId, $pending->resource, $verified->scopes, $verified->expiresAt, $pending->context);
        } catch (\Throwable) {
            // Token responses and transport exceptions may contain credentials.
            throw AuthenticationFailed::because('Resource authorization could not be verified.');
        }
    }

    private function endpoint(string $name): string
    {
        $endpoint = $this->discovery->endpoint($name);
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || parse_url($endpoint, PHP_URL_USER) !== null || parse_url($endpoint, PHP_URL_FRAGMENT) !== null) {
            throw AuthenticationFailed::because('Resource authorization requires HTTPS endpoints.');
        }

        return $endpoint;
    }

    private function required(string $key): string
    {
        $value = $this->config[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw AuthenticationFailed::because('Resource authorization is not configured.');
        }

        return $value;
    }
}
