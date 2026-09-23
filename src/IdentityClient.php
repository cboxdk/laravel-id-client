<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\ApiKeys\ApiKeyVerifier;
use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Enums\Prompt;
use Cbox\Id\Client\Exceptions\ApiKeyRejected;
use Cbox\Id\Client\Exceptions\ApiKeyVerificationUnavailable;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Support\Pkce;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Cbox\Id\Client\ValueObjects\Organization;
use Cbox\Id\Client\ValueObjects\RefreshedTokens;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;
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
     * The scopes THIS authorization actually asked for.
     *
     * Stashed beside the state and the nonce because `redirect(scopes: …)` accepts a
     * per-call override and the redemption half has to judge the answer against what was
     * asked, not against what the config says. Without it, a caller who overrode the
     * scopes was checked against a configuration they had deliberately stepped around —
     * and once the server stopped returning an `id_token` to a request that never asked
     * for `openid`, that mismatch turned a documented call into an unrecoverable throw.
     */
    private const SCOPES_KEY = 'cbox-id-client.scopes';

    /**
     * The organization THIS authorization asked to be bound to, when it asked.
     *
     * Checked against the `org` the tokens come back with. Cbox ID refuses with
     * `access_denied` when the person is not a member, so a mismatch is not a refusal it
     * forgot — it is a response for a different request than the one this browser made,
     * and a switch to organization B that silently lands in A is exactly the confusion a
     * tenant boundary exists to prevent.
     */
    private const ORGANIZATION_KEY = 'cbox-id-client.organization';

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
        private readonly SessionIdentityStore $sessions = new SessionIdentityStore,
    ) {}

    /**
     * Start a login: redirect the user to Cbox ID's authorize endpoint. Stashes the PKCE
     * verifier, CSRF state and nonce in the session for {@see authenticate()}.
     *
     * `prompt` maps to OIDC `prompt` — `login` forces a fresh sign-in (so the user can
     * authenticate as a different account), `select_account` shows an account chooser,
     * `none` is silent auth — plus Cbox ID's own `select_organization` (always show the
     * hosted organization picker) and `create_organization` (the hosted "create a team"
     * step). `maxAge` forces re-auth if the instance session is older than N seconds;
     * `loginHint` pre-fills the identifier.
     *
     * `organization` binds the grant to that organization: the person must be an active
     * member, or the callback comes back with `access_denied`. `organizationHint` only
     * preselects it in the picker.
     *
     * @param  list<string>|null  $scopes  overrides the configured default scopes
     */
    public function redirect(
        ?array $scopes = null,
        ?string $state = null,
        string|Prompt|null $prompt = null,
        ?int $maxAge = null,
        ?string $loginHint = null,
        ?string $organization = null,
        ?string $organizationHint = null,
    ): RedirectResponse {
        $verifier = Pkce::verifier();
        $state ??= bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        session()->put(self::STATE_KEY, $state);
        session()->put(self::VERIFIER_KEY, $verifier);
        session()->put(self::NONCE_KEY, $nonce);
        session()->put(self::SCOPES_KEY, $scopes ?? $this->scopes());

        if ($organization !== null && $organization !== '') {
            session()->put(self::ORGANIZATION_KEY, $organization);
        } else {
            // A leftover from an abandoned switch must not judge this unrelated login.
            session()->forget(self::ORGANIZATION_KEY);
        }

        $query = http_build_query(array_filter([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $scopes ?? $this->scopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Pkce::challenge($verifier),
            'code_challenge_method' => 'S256',
            'prompt' => $prompt instanceof Prompt ? $prompt->value : $prompt,
            'max_age' => $maxAge !== null ? (string) $maxAge : null,
            'login_hint' => $loginHint,
            'organization' => $organization,
            'organization_hint' => $organizationHint,
        ], static fn (?string $v): bool => $v !== null && $v !== ''));

        return new RedirectResponse($this->discovery->endpoint('authorization_endpoint').'?'.$query);
    }

    /**
     * Switch the signed-in person to another organization they belong to.
     *
     * A new authorization bound to that organization rather than a local flag, because
     * the organization is IN the token: `org`, `org_role`, and the roles and permissions
     * resolved there all change with it, and only Cbox ID can re-issue them. With a live
     * Cbox ID session this is a round trip the person does not see. Once your callback
     * runs {@see authenticate()}, the session remembers the new organization.
     *
     * @param  list<string>|null  $scopes
     */
    public function switchOrganization(string $organizationId, ?array $scopes = null, string|Prompt|null $prompt = null): RedirectResponse
    {
        return $this->redirect($scopes, prompt: $prompt, organization: $organizationId);
    }

    /**
     * Send the person to Cbox ID's hosted organization picker.
     *
     * @param  list<string>|null  $scopes
     */
    public function selectOrganization(?array $scopes = null, ?string $organizationHint = null): RedirectResponse
    {
        return $this->redirect($scopes, prompt: Prompt::SelectOrganization, organizationHint: $organizationHint);
    }

    /**
     * Send the person to Cbox ID's hosted "create an organization" step. They become its
     * Owner, and the authorization continues bound to the new organization.
     *
     * @param  list<string>|null  $scopes
     */
    public function createOrganization(?array $scopes = null): RedirectResponse
    {
        return $this->redirect($scopes, prompt: Prompt::CreateOrganization);
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

        // What we ASKED for, falling back to the configured set for a session started
        // before this key existed — an upgrade mid-flight must not fail a live sign-in.
        $requested = session()->pull(self::SCOPES_KEY);
        $requested = is_array($requested) ? array_values(array_filter($requested, 'is_string')) : $this->scopes();
        $requestedOrganization = session()->pull(self::ORGANIZATION_KEY);

        if (! is_string($state) || ! is_string($expected) || ! hash_equals($expected, $state)) {
            throw InvalidState::because('The login state did not match — the request may be forged or stale.');
        }

        if ($request->has('error')) {
            // After the state check, deliberately: an error nobody's browser asked for is
            // a forged callback, and it gets the same answer as any other.
            $description = $request->query('error_description');

            throw AuthenticationFailed::fromCallback(
                $request->string('error')->toString(),
                is_string($description) ? $description : null,
            );
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
        if (! is_string($idToken) && in_array('openid', $requested, true)) {
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

        if (is_string($requestedOrganization) && $requestedOrganization !== '' && ($claims['org'] ?? null) !== $requestedOrganization) {
            throw AuthenticationFailed::because('Cbox ID bound this sign-in to a different organization than the one requested.');
        }

        $user = new CboxUser(
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

        if ($this->remembersIdentity()) {
            $this->sessions->remember(Identity::fromPrincipal($user));
        }

        return $user;
    }

    /**
     * Exchange a refresh token for fresh tokens (OAuth 2.0 `refresh_token` grant).
     *
     * Cbox ID rotates refresh tokens and detects reuse, so ALWAYS persist the returned
     * `refreshToken` and discard the one you passed — presenting a rotated token again
     * revokes the whole family. Cbox ID only issues a refresh token when the login asked
     * for `offline_access`.
     *
     * A returned id_token is verified (signature, issuer, audience, expiry) before it is
     * handed back; the nonce is not re-checked, because RFC 6749 has none on this leg and
     * OIDC Core §12.2 says a refreshed id_token need not carry one.
     *
     * `remember: true` also refreshes what the session remembers about the person —
     * their organization tier, roles and permissions as they are NOW — by reading
     * UserInfo with the new access token. It refuses to overwrite a different subject.
     *
     * @throws AuthenticationFailed `isInvalidGrant()` when the person must sign in again
     */
    public function refresh(string $refreshToken, bool $remember = false): RefreshedTokens
    {
        $params = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId(),
            'refresh_token' => $refreshToken,
        ];

        // Public clients too, like revocation: a first-party app with no secret still
        // holds refresh tokens, and the token endpoint accepts it without one.
        $secret = $this->config['client_secret'] ?? null;

        if (is_string($secret) && $secret !== '') {
            $params['client_secret'] = $secret;
        }

        $response = Http::asForm()->timeout($this->timeout())->post($this->discovery->endpoint('token_endpoint'), $params);

        if (! $response->successful()) {
            // `invalid_grant` means the token is spent, revoked or replayed; a 5xx means
            // the same token is still good in a moment. The exception keeps which.
            throw AuthenticationFailed::fromResponse('Token refresh failed', $response);
        }

        $tokens = $this->asArray($response->json());
        $accessToken = $tokens['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw AuthenticationFailed::because('The refresh response carried no access token.');
        }

        $idToken = is_string($tokens['id_token'] ?? null) && $tokens['id_token'] !== '' ? $tokens['id_token'] : null;
        $claims = $idToken !== null ? $this->verifyIdToken($idToken, null) : [];

        $refreshed = new RefreshedTokens(
            accessToken: $accessToken,
            refreshToken: is_string($tokens['refresh_token'] ?? null) && $tokens['refresh_token'] !== '' ? $tokens['refresh_token'] : $refreshToken,
            idToken: $idToken,
            expiresIn: is_numeric($tokens['expires_in'] ?? null) ? (int) $tokens['expires_in'] : 0,
            scope: is_string($tokens['scope'] ?? null) ? $tokens['scope'] : null,
            claims: $claims,
        );

        if ($remember) {
            $this->rememberRefreshed($refreshed);
        }

        return $refreshed;
    }

    /**
     * Whoever is acting in this request: a verified bearer token or API key when the
     * route checked one, otherwise the person this session signed in (and only while the
     * same local user is logged in), otherwise null.
     */
    public function principal(): ?Principal
    {
        $request = request();

        return app(CurrentPrincipal::class)->resolve($request->user(), $request);
    }

    /**
     * Ask Cbox ID whether a customer API key is live for this application, and that it
     * carries every permission named. Cached briefly; see {@see ApiKeyVerifier}.
     *
     * @param  list<string>  $requiredPermissions
     *
     * @throws ApiKeyRejected
     * @throws ApiKeyVerificationUnavailable
     */
    public function verifyApiKey(string $key, array $requiredPermissions = []): VerifiedApiKey
    {
        return app(VerifiesApiKeys::class)->verify($key, $requiredPermissions);
    }

    /** The organization the current principal acts for, or null. */
    public function currentOrganization(): ?Organization
    {
        return $this->principal()?->organization();
    }

    /**
     * Remember a principal in the session as the signed-in identity. {@see authenticate()}
     * does this for you unless `cbox-id-client.session.remember` is off.
     */
    public function rememberIdentity(Principal $principal): void
    {
        $this->sessions->remember(Identity::fromPrincipal($principal));
    }

    /**
     * Forget the remembered identity. Laravel's `Logout` event already does this; call
     * it yourself if your application signs people out some other way.
     */
    public function forgetIdentity(): void
    {
        $this->sessions->forget();
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
     *
     * PASS THE HINT IF YOU WANT "SIGN OUT EVERYWHERE". Cbox ID revokes every session
     * the person holds only when a hint it can VERIFY names the subject holding the
     * browser; with no hint it signs this browser out and leaves their other devices
     * alone. That is deliberate rather than an omission: this endpoint is
     * unauthenticated and reached by a redirect, so a request carrying no proof of who
     * it concerns could otherwise be forged into ending anyone's sessions everywhere.
     * `CboxUser::$idToken` is the value to pass. See laravel-id UPGRADING.md for 1.8.0.
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
            throw AuthenticationFailed::fromResponse('Machine token request failed', $response);
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
            throw AuthenticationFailed::fromResponse('Userinfo request failed', $response);
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
            throw AuthenticationFailed::fromResponse('Introspection request failed', $response);
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
        // PUBLIC CLIENTS TOO. `clientSecret()` is `requiredString()`, so a deployment
        // configured without one — which is what a first-party app shipping no secret IS —
        // threw before the request left the process, and every sign-out left the refresh
        // token valid for its whole lifetime. Cbox ID's revocation endpoint accepts a
        // public client and advertises `none` among its revocation auth methods; RFC 7009
        // §2.1 scopes each revocation to the calling client, so the only capability this
        // opens is destroying a token you are already holding.
        $params = ['token' => $token, 'client_id' => $this->clientId()];

        if ($tokenTypeHint !== null && $tokenTypeHint !== '') {
            $params['token_type_hint'] = $tokenTypeHint;
        }

        $request = Http::asForm()->timeout($this->timeout());
        $secret = $this->config['client_secret'] ?? null;

        if (is_string($secret) && $secret !== '') {
            $request = $request->withBasicAuth($this->clientId(), $secret);
        }

        $response = $request->post($this->discovery->endpoint('revocation_endpoint'), $params);

        if (! $response->successful()) {
            throw AuthenticationFailed::fromResponse('Revocation request failed', $response);
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
            throw AuthenticationFailed::fromResponse('Token exchange failed', $response);
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

    private function rememberRefreshed(RefreshedTokens $tokens): void
    {
        $current = $this->sessions->identity();
        $claims = array_merge($this->userinfo($tokens->accessToken), $tokens->claims);
        $subject = $claims['sub'] ?? null;

        // The same person or nobody. A refresh token that answers for someone else is
        // not a refresh of this session, and adopting it would swap identities under a
        // logged-in local user.
        if (! is_string($subject) || ($current !== null && ! hash_equals($current->subject, $subject))) {
            throw AuthenticationFailed::because('The refreshed tokens are for a different subject than this session.');
        }

        $identity = Identity::fromClaims($claims);

        if ($identity !== null) {
            $this->sessions->remember($identity);
        }
    }

    private function remembersIdentity(): bool
    {
        $session = $this->config['session'] ?? null;

        return ! is_array($session) || ($session['remember'] ?? true) !== false;
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

        // A string too — `CBOX_ID_SCOPES="openid profile email organizations"` — for a
        // published config file that predates the env variable and passes it straight on.
        if (is_string($scopes)) {
            $scopes = preg_split('/[\s,]+/', $scopes, -1, PREG_SPLIT_NO_EMPTY);
        }

        $scopes = is_array($scopes) ? array_values(array_filter($scopes, static fn (mixed $s): bool => is_string($s) && $s !== '')) : [];

        return $scopes === [] ? ['openid', 'profile', 'email'] : $scopes;
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
            throw NotConfigured::key($key);
        }

        return $value;
    }
}
