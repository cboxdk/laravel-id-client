<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Support\Pkce;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turnkey Cbox ID consumer client. It speaks standard OpenID Connect against a Cbox
 * ID instance — so integrating is a login redirect and a callback, not a rewrite —
 * and adds the two conveniences a hosted-identity product needs: a redirect to the
 * instance's hosted profile-management page, and back-channel helpers (machine
 * tokens, userinfo, introspection, webhook signature verification).
 *
 * Login is hardened by default: PKCE (S256), a CSRF state check, a nonce, and full
 * id_token signature + issuer + audience verification against the instance's JWKS.
 */
class IdentityClient
{
    private const STATE_KEY = 'cbox-id-client.state';

    private const VERIFIER_KEY = 'cbox-id-client.verifier';

    private const NONCE_KEY = 'cbox-id-client.nonce';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly Discovery $discovery,
    ) {}

    /**
     * Begin login: redirect the user to Cbox ID's authorize endpoint. Stashes the
     * PKCE verifier, CSRF state and nonce in the session for {@see authenticate()}.
     *
     * @param  list<string>|null  $scopes  overrides the configured default scopes
     */
    /**
     * Start a login. `prompt` maps to OIDC `prompt` — pass `login` to force a fresh
     * sign-in (so the user can authenticate as a different account, à la Notion/Slack
     * "add account"), `select_account` for an account chooser, or `none` for silent
     * auth. `maxAge` forces re-auth if the instance session is older than N seconds;
     * `loginHint` pre-fills the identifier.
     *
     * @param  list<string>|null  $scopes
     */
    public function redirect(
        ?array $scopes = null,
        ?string $state = null,
        ?string $prompt = null,
        ?int $maxAge = null,
        ?string $loginHint = null,
    ): RedirectResponse {
        $verifier = Pkce::verifier();
        $state ??= bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        session()->put(self::STATE_KEY, $state);
        session()->put(self::VERIFIER_KEY, $verifier);
        session()->put(self::NONCE_KEY, $nonce);

        $query = http_build_query(array_filter([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $scopes ?? $this->scopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Pkce::challenge($verifier),
            'code_challenge_method' => 'S256',
            'prompt' => $prompt,
            'max_age' => $maxAge !== null ? (string) $maxAge : null,
            'login_hint' => $loginHint,
        ], static fn (?string $v): bool => $v !== null && $v !== ''));

        return new RedirectResponse($this->discovery->endpoint('authorization_endpoint').'?'.$query);
    }

    /**
     * Add / switch account: force a fresh sign-in so the user can authenticate as a
     * different Cbox ID account. Sugar over `redirect(prompt: 'login')`.
     *
     * @param  list<string>|null  $scopes
     */
    public function addAccount(?array $scopes = null, ?string $state = null): RedirectResponse
    {
        return $this->redirect($scopes, $state, prompt: 'login');
    }

    /**
     * Complete login on your callback route: verify state, exchange the code (with
     * the PKCE verifier), verify the id_token, and return the authenticated user.
     *
     * @throws InvalidState when the state does not match (forged/stale request)
     * @throws AuthenticationFailed on any other failure
     */
    public function authenticate(Request $request): CboxUser
    {
        $state = $request->query('state');
        $expected = session()->pull(self::STATE_KEY);
        $verifier = session()->pull(self::VERIFIER_KEY);
        $nonce = session()->pull(self::NONCE_KEY);

        if (! is_string($state) || ! is_string($expected) || ! hash_equals($expected, $state)) {
            throw InvalidState::because('The login state did not match — the request may be forged or stale.');
        }

        if ($request->has('error')) {
            throw AuthenticationFailed::because('Cbox ID returned an error: '.$request->string('error')->toString());
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '' || ! is_string($verifier)) {
            throw AuthenticationFailed::because('The callback was missing an authorization code.');
        }

        $tokens = $this->exchange($code, $verifier);
        $accessToken = $tokens['access_token'] ?? null;
        $idToken = $tokens['id_token'] ?? null;

        if (! is_string($accessToken)) {
            throw AuthenticationFailed::because('No access token was returned.');
        }

        $claims = is_string($idToken) ? $this->verifyIdToken($idToken, is_string($nonce) ? $nonce : null) : [];
        // Enrich with userinfo (email/name/org that may not be in a minimal id_token).
        $claims = array_merge($claims, $this->userinfo($accessToken));

        $sub = $claims['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw AuthenticationFailed::because('The verified token carried no subject.');
        }

        return new CboxUser(
            id: $sub,
            email: is_string($claims['email'] ?? null) ? $claims['email'] : null,
            name: is_string($claims['name'] ?? null) ? $claims['name'] : null,
            organizationId: is_string($claims['org'] ?? null) ? $claims['org'] : null,
            claims: $claims,
            accessToken: $accessToken,
            refreshToken: is_string($tokens['refresh_token'] ?? null) ? $tokens['refresh_token'] : null,
            idToken: is_string($idToken) ? $idToken : null,
            expiresIn: is_numeric($tokens['expires_in'] ?? null) ? (int) $tokens['expires_in'] : 0,
        );
    }

    /**
     * The URL of the Cbox ID hosted account/profile page (self-service password,
     * MFA, passkeys, sessions). A signed-in user is authenticated there by their
     * Cbox ID session; `returnTo` is passed so the page can link back to your app.
     */
    public function profileUrl(?string $returnTo = null): string
    {
        $url = rtrim($this->issuer(), '/').$this->accountPath();

        return $returnTo === null ? $url : $url.'?'.http_build_query(['return_to' => $returnTo]);
    }

    public function redirectToProfile(?string $returnTo = null): RedirectResponse
    {
        return new RedirectResponse($this->profileUrl($returnTo));
    }

    /**
     * The RP-initiated logout URL, or null when the instance advertises none.
     */
    public function logoutUrl(?string $returnTo = null): ?string
    {
        try {
            $endpoint = $this->discovery->endpoint('end_session_endpoint');
        } catch (Throwable) {
            return null;
        }

        return $returnTo === null ? $endpoint : $endpoint.'?'.http_build_query(['post_logout_redirect_uri' => $returnTo]);
    }

    /**
     * A machine (client-credentials) access token for calling Cbox ID APIs as your
     * app, not on a user's behalf.
     *
     * @param  list<string>  $scopes
     */
    public function machineToken(array $scopes = [], ?string $resource = null): string
    {
        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ];

        if ($scopes !== []) {
            $params['scope'] = implode(' ', $scopes);
        }

        if ($resource !== null) {
            $params['resource'] = $resource;
        }

        $response = Http::asForm()->timeout($this->timeout())->post($this->discovery->endpoint('token_endpoint'), $params);

        if (! $response->successful()) {
            throw AuthenticationFailed::because('Machine token request failed: '.$response->body());
        }

        $token = $response->json('access_token');

        if (! is_string($token)) {
            throw AuthenticationFailed::because('The token response had no access_token.');
        }

        return $token;
    }

    /**
     * The OIDC userinfo claims for an access token.
     *
     * @return array<string, mixed>
     */
    public function userinfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->timeout($this->timeout())->get($this->discovery->endpoint('userinfo_endpoint'));

        if (! $response->successful()) {
            throw AuthenticationFailed::because('Userinfo request failed.');
        }

        return $this->asArray($response->json());
    }

    /**
     * RFC 7662 token introspection (confidential client auth). Returns the raw
     * introspection response; `active` tells you if the token is currently valid.
     *
     * @return array<string, mixed>
     */
    public function introspect(string $token): array
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->timeout($this->timeout())
            ->post($this->discovery->endpoint('introspection_endpoint'), ['token' => $token]);

        if (! $response->successful()) {
            throw AuthenticationFailed::because('Introspection request failed.');
        }

        return $this->asArray($response->json());
    }

    /**
     * Verify a Cbox ID webhook / action signature (`X-Cbox-Signature: t=..,v1=..`):
     * an HMAC-SHA256 over `"{timestamp}.{raw body}"`, within a freshness window. Use
     * the raw request body, not a re-encoded one.
     */
    public function verifyWebhook(string $payload, ?string $signatureHeader, string $secret, int $toleranceSeconds = 300): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
    }

    /**
     * @return array<string, mixed>
     */
    private function exchange(string $code, string $verifier): array
    {
        $response = Http::asForm()->timeout($this->timeout())->post($this->discovery->endpoint('token_endpoint'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code_verifier' => $verifier,
        ]);

        if (! $response->successful()) {
            throw AuthenticationFailed::because('Token exchange failed: '.$response->body());
        }

        return $this->asArray($response->json());
    }

    /**
     * Verify the id_token signature against the JWKS (JWT::decode enforces the
     * signature + expiry), then assert issuer, audience and nonce.
     *
     * @return array<string, mixed>
     */
    private function verifyIdToken(string $idToken, ?string $nonce): array
    {
        try {
            $claims = $this->asArray(get_object_vars(JWT::decode($idToken, JWK::parseKeySet($this->discovery->jwks()))));
        } catch (Throwable $e) {
            throw AuthenticationFailed::because('The id_token could not be verified: '.$e->getMessage());
        }

        if (($claims['iss'] ?? null) !== $this->issuer()) {
            throw AuthenticationFailed::because('The id_token issuer did not match.');
        }

        $aud = $claims['aud'] ?? null;

        if ($aud !== $this->clientId() && ! (is_array($aud) && in_array($this->clientId(), $aud, true))) {
            throw AuthenticationFailed::because('The id_token audience did not match.');
        }

        if ($nonce !== null && ($claims['nonce'] ?? null) !== $nonce) {
            throw AuthenticationFailed::because('The id_token nonce did not match — possible replay.');
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function asArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    private function issuer(): string
    {
        return $this->requiredString('issuer');
    }

    private function clientId(): string
    {
        return $this->requiredString('client_id');
    }

    private function clientSecret(): string
    {
        return $this->requiredString('client_secret');
    }

    private function redirectUri(): string
    {
        return $this->requiredString('redirect');
    }

    private function accountPath(): string
    {
        $path = $this->config['account_path'] ?? '/settings';

        return is_string($path) && $path !== '' ? '/'.ltrim($path, '/') : '/settings';
    }

    /**
     * @return list<string>
     */
    private function scopes(): array
    {
        $scopes = $this->config['scopes'] ?? null;

        if (! is_array($scopes) || $scopes === []) {
            return ['openid', 'profile', 'email'];
        }

        return array_values(array_filter($scopes, 'is_string'));
    }

    private function timeout(): int
    {
        $timeout = $this->config['http_timeout'] ?? 10;

        return is_numeric($timeout) ? max(1, (int) $timeout) : 10;
    }

    private function requiredString(string $key): string
    {
        $value = $this->config[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw ClientConfigurationException::because("Cbox ID client config '{$key}' is not set.");
        }

        return $value;
    }
}
