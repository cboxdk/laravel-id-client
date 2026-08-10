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
     * The signature algorithm assumed for a JWKS key that omits `alg`. RFC 7517 §4.4
     * makes `alg` optional, so verification must not depend on the instance emitting
     * it on every key — but the value is pinned here rather than taken from the token.
     */
    private const DEFAULT_JWK_ALG = 'RS256';

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

        // AN `openid` REQUEST WITHOUT AN ID_TOKEN IS A PROTOCOL VIOLATION, and refusing it
        // is the difference between an authenticated login and a bearer token.
        //
        // This used to be `is_string($idToken) ? verify() : []`, so a token response that
        // omitted the id_token skipped verification entirely and the subject was taken
        // from UserInfo instead. Nothing failed: the login succeeded, the signature was
        // never checked, and the `nonce` pulled from the session a few lines above was
        // simply never used. An OIDC login degraded silently into an OAuth one — and
        // silently is the problem, because the surface that would tell you is the one
        // that stopped running.
        //
        // Asked of the SCOPES WE REQUESTED rather than of the response: a deployment that
        // has deliberately configured a non-OIDC scope set gets the old behaviour, and one
        // that asked for `openid` gets what OIDC Core §3.1.3.3 promises it.
        if (! is_string($idToken) && in_array('openid', $this->scopes(), true)) {
            throw AuthenticationFailed::because('Cbox ID returned no id_token for an OpenID Connect request.');
        }

        $claims = is_string($idToken) ? $this->verifyIdToken($idToken, is_string($nonce) ? $nonce : null) : [];

        // THE SUBJECT IS WHATEVER THE SIGNATURE SAID, and UserInfo cannot move it.
        //
        // This read `$sub` AFTER `array_merge($claims, $userinfo)` — UserInfo second, so
        // its `sub` overwrote the one that arrived inside a signature. Verifying the
        // id_token buys exactly one thing, that its claims are bound to a key, and an
        // unsigned bearer-authenticated response overriding them gives that away: a token
        // response pairing a signed assertion for one person with an access token whose
        // UserInfo answers for another logged in the other. The refusal below even said
        // "The verified token carried no subject", which by then was not what happened.
        //
        // OIDC Core §5.3.2 is explicit: the UserInfo `sub` MUST be verified to match the
        // ID Token's exactly, and on a mismatch the response "MUST NOT be used".
        $verifiedSub = $claims['sub'] ?? null;

        $userinfo = $this->userinfo($accessToken);
        $userinfoSub = $userinfo['sub'] ?? null;

        if (is_string($verifiedSub) && is_string($userinfoSub) && ! hash_equals($verifiedSub, $userinfoSub)) {
            throw AuthenticationFailed::because('The UserInfo subject does not match the verified id_token.');
        }

        // Enriches (email/name/org a minimal id_token may omit) and never replaces: the
        // verified claims are re-applied on top, so the merge cannot move `sub`, `iss`,
        // `aud` or anything else the signature covered.
        $claims = array_merge($userinfo, $claims);

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
     *
     * `client_id` is always sent, even without a `$returnTo`: Cbox ID validates
     * `post_logout_redirect_uri` against the registered allow-list of THAT client
     * (OIDC RP-Initiated Logout 1.0 §2). A request that names no client leaves it
     * no list to check, so it drops the return URL and the user lands on a bare
     * "you are signed out" page. `$idTokenHint` — the user's `id_token`, when you
     * still hold it — is the spec's other way to identify the client, and also
     * tells the server whose session is ending.
     */
    public function logoutUrl(?string $returnTo = null, ?string $idTokenHint = null): ?string
    {
        try {
            $endpoint = $this->discovery->endpoint('end_session_endpoint');
        } catch (Throwable) {
            return null;
        }

        $params = ['client_id' => $this->clientId()];

        if ($returnTo !== null) {
            $params['post_logout_redirect_uri'] = $returnTo;
        }

        if ($idTokenHint !== null && $idTokenHint !== '') {
            $params['id_token_hint'] = $idTokenHint;
        }

        return $endpoint.'?'.http_build_query($params);
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
     * RFC 7009 token revocation (confidential client auth). Revokes an access or
     * refresh token; revoking a refresh token also drops the whole token family, so
     * this is what a real "sign out everywhere" does.
     *
     * Per RFC 7009 the instance answers 200 for an unknown or already-revoked token,
     * so a successful call means "the token is not valid any more", not "it existed".
     * `$tokenTypeHint` (`access_token` / `refresh_token`) only tells the instance
     * which store to search first.
     */
    public function revoke(string $token, ?string $tokenTypeHint = null): void
    {
        $params = ['token' => $token];

        if ($tokenTypeHint !== null && $tokenTypeHint !== '') {
            $params['token_type_hint'] = $tokenTypeHint;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->timeout($this->timeout())
            ->post($this->discovery->endpoint('revocation_endpoint'), $params);

        if (! $response->successful()) {
            throw AuthenticationFailed::because('Revocation request failed.');
        }
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
        $jwks = $this->discovery->jwks();

        // The instance can roll its signing key inside our JWKS cache TTL. Without a
        // refetch on a kid miss, every login fails until the TTL lapses.
        if (! $this->jwksCarriesKid($jwks, $this->idTokenKid($idToken))) {
            $jwks = $this->discovery->refreshJwks() ?? $jwks;
        }

        try {
            $claims = $this->asArray(get_object_vars(JWT::decode($idToken, JWK::parseKeySet($jwks, self::DEFAULT_JWK_ALG))));
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
     * The `kid` from an id_token's (unverified) header, or null when it carries none.
     * Only used to decide whether the cached JWKS can possibly hold the signing key —
     * never to choose an algorithm.
     */
    private function idTokenKid(string $idToken): ?string
    {
        $segment = explode('.', $idToken)[0];

        if ($segment === '') {
            return null;
        }

        $remainder = strlen($segment) % 4;
        $padded = $remainder === 0 ? $segment : $segment.str_repeat('=', 4 - $remainder);
        $json = base64_decode(strtr($padded, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        $header = json_decode($json, true);
        $kid = is_array($header) ? ($header['kid'] ?? null) : null;

        return is_string($kid) && $kid !== '' ? $kid : null;
    }

    /**
     * @param  array<string, mixed>  $jwks
     */
    private function jwksCarriesKid(array $jwks, ?string $kid): bool
    {
        // No kid to look up — nothing a refetch could improve; let the decoder decide.
        if ($kid === null) {
            return true;
        }

        $keys = $jwks['keys'] ?? null;

        if (! is_array($keys)) {
            return false;
        }

        foreach ($keys as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
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
