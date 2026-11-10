<?php

declare(strict_types=1);

use Cbox\Id\Client\AppCatalog\AppCatalog;
use Cbox\Id\Client\AppCatalog\AppCatalogContext;
use Cbox\Id\Client\AppCatalog\AppCatalogStore;
use Cbox\Id\Client\AppCatalog\CatalogGrantExchange;
use Cbox\Id\Client\AppCatalog\CatalogUnavailable;
use Cbox\Id\Client\AppCatalog\Http\AppCatalogController;
use Cbox\Id\Client\AppCatalog\SignInRequired;
use Cbox\Id\Client\AppCatalog\StoredLoginToken;
use Cbox\Id\Client\AppCatalog\StoredRefreshToken;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\PersonalAppCatalog;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Testing\TestKeys;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Firebase\JWT\JWT;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
 * The app switcher's catalog: the sign-in exchange, silent renewal with the person's
 * refresh token, the routes, and every binding check the per-app copies had.
 *
 * Cbox ID is faked at the HTTP layer and behaves like the real one where it matters:
 * catalog grants are signed and bound to the DPoP key in the request's proof, refresh
 * tokens rotate, and presenting a spent one is recorded as a replay.
 */

const CATALOG_ISSUER = 'https://id.test';
const CATALOG_CLIENT = 'work-browser';

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => CATALOG_ISSUER,
        'cbox-id-client.client_id' => CATALOG_CLIENT,
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
        'cbox-id-client.scopes' => ['openid', 'profile', 'email', 'offline_access'],
        'cbox-id-client.app_catalog.enabled' => true,
        'session.driver' => 'array',
        'app.url' => 'https://app.test',
    ]);
    app()->forgetInstance(IdentityClient::class);
    CboxId::clearResolvedInstances();
    Cache::flush();
    Http::preventStrayRequests();

    $this->id = new FakeCatalogId;
    $this->id->install();

    CboxId::appCatalogRoutes();
    app('router')->getRoutes()->refreshNameLookups();
    app('url')->forceRootUrl('https://app.test');
});

/**
 * A Cbox ID that mints real catalog grants, rotates refresh tokens and remembers
 * replays. Fields steer the next answers.
 */
final class FakeCatalogId
{
    /** @var array<string, mixed> claim overrides for the next catalog grants */
    public array $grantClaims = [];

    /** @var array<string, mixed> response overrides for the exchange */
    public array $exchangeResponse = [];

    public int $exchangeStatus = 200;

    public int $refreshStatus = 200;

    public int $catalogStatus = 200;

    public bool $refreshIssues = true;

    public bool $connectionFails = false;

    public int $grantLifetime = 3600;

    /** @var list<string> */
    public array $spent = [];

    public int $replays = 0;

    public int $refreshes = 0;

    public int $exchanges = 0;

    public int $catalogReads = 0;

    /** @var list<string> */
    public array $subjectTokens = [];

    public string $liveRefresh = 'rt_0';

    public int $generation = 0;

    /** @var list<array<string, mixed>> */
    public array $apps = [
        ['key' => 'work', 'name' => 'Work', 'description' => 'Time', 'launch_url' => 'https://work.test/', 'icon_url' => 'https://id.test/app-icons/1/a', 'icon_dark_url' => 'https://id.test/app-icons/1/b'],
        ['key' => 'billing', 'name' => 'Billing', 'description' => null, 'launch_url' => 'https://billing.test/', 'icon_url' => null, 'icon_dark_url' => null],
        ['key' => 'agents', 'name' => 'Agents', 'description' => null, 'launch_url' => 'https://agents.test/', 'icon_url' => null],
    ];

    public function install(): void
    {
        Http::fake(function (ClientRequest $request) {
            if ($this->connectionFails) {
                throw new ConnectionException('down');
            }

            return match (true) {
                str_ends_with($request->url(), '/.well-known/openid-configuration') => Http::response([
                    'issuer' => CATALOG_ISSUER,
                    'authorization_endpoint' => CATALOG_ISSUER.'/oauth/authorize',
                    'token_endpoint' => CATALOG_ISSUER.'/oauth/token',
                    'userinfo_endpoint' => CATALOG_ISSUER.'/oauth/userinfo',
                    'jwks_uri' => CATALOG_ISSUER.'/.well-known/jwks.json',
                ]),
                str_ends_with($request->url(), '/.well-known/jwks.json') => Http::response(TestKeys::pair()['jwks']),
                str_ends_with($request->url(), '/oauth/userinfo') => Http::response(['sub' => 'usr_1', 'org' => 'org_1']),
                str_starts_with($request->url(), CATALOG_ISSUER.'/api/v1/me/apps') => $this->catalog(),
                $request->url() === CATALOG_ISSUER.'/oauth/token' => $this->token($request),
                default => Http::response(['error' => 'unexpected'], 500),
            };
        });
    }

    /** The token response of a login: an id_token, an access token, a refresh token. */
    public function loginResponse(bool $withRefresh = true): array
    {
        return array_filter([
            'access_token' => 'at_login',
            'id_token' => self::sign(['iss' => CATALOG_ISSUER, 'aud' => CATALOG_CLIENT, 'sub' => 'usr_1', 'org' => 'org_1', 'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 300]),
            'refresh_token' => $withRefresh ? $this->liveRefresh : null,
            'expires_in' => 300,
        ]);
    }

    private function token(ClientRequest $request)
    {
        return match ($request['grant_type']) {
            'authorization_code' => isset($request['resource']) ? $this->exchangeAnswer($request, 'authorization_code') : Http::response($this->loginResponse($this->refreshIssues)),
            'refresh_token' => $this->refresh($request),
            'urn:ietf:params:oauth:grant-type:token-exchange' => $this->exchangeAnswer($request, 'exchange'),
            default => Http::response(['error' => 'unsupported_grant_type'], 400),
        };
    }

    private function refresh(ClientRequest $request)
    {
        $this->refreshes++;

        if (in_array($request['refresh_token'], $this->spent, true)) {
            $this->replays++;

            return Http::response(['error' => 'invalid_grant'], 400);
        }

        if ($this->refreshStatus !== 200) {
            return Http::response(['error' => $this->refreshStatus >= 500 ? 'server_error' : 'invalid_grant'], $this->refreshStatus);
        }

        if ($request['refresh_token'] !== $this->liveRefresh) {
            return Http::response(['error' => 'invalid_grant'], 400);
        }

        $this->spent[] = $this->liveRefresh;
        $this->liveRefresh = 'rt_'.++$this->generation;

        return Http::response(['access_token' => 'at_refreshed_'.$this->generation, 'refresh_token' => $this->liveRefresh, 'expires_in' => 300, 'token_type' => 'Bearer']);
    }

    private function exchangeAnswer(ClientRequest $request, string $kind)
    {
        $this->exchanges++;

        if ($kind === 'exchange') {
            $this->subjectTokens[] = $request['subject_token'];
            expect($request['scope'])->toBe('account:apps:read')
                ->and($request['resource'])->toBe(CATALOG_ISSUER.'/mcp')
                ->and($request['client_id'])->toBe(CATALOG_CLIENT)
                ->and($request['subject_token_type'])->toBe('urn:ietf:params:oauth:token-type:access_token');
        }

        if ($this->exchangeStatus !== 200) {
            return Http::response(['error' => 'invalid_grant'], $this->exchangeStatus);
        }

        $proof = $request->header('DPoP')[0] ?? '';
        $jwk = json_decode((string) base64_decode(strtr(explode('.', $proof)[0], '-_', '+/')), true)['jwk'];
        $thumbprint = rtrim(strtr(base64_encode(hash('sha256', json_encode(['crv' => $jwk['crv'], 'kty' => $jwk['kty'], 'x' => $jwk['x'], 'y' => $jwk['y']], JSON_THROW_ON_ERROR), true)), '+/', '-_'), '=');
        $token = self::sign(array_replace([
            'iss' => CATALOG_ISSUER, 'sub' => 'usr_1', 'org' => 'org_1', 'aud' => CATALOG_ISSUER.'/mcp',
            'client_id' => CATALOG_CLIENT, 'scope' => 'account:apps:read', 'iat' => time() - 1,
            'exp' => time() + $this->grantLifetime, 'jti' => bin2hex(random_bytes(6)), 'cnf' => ['jkt' => $thumbprint],
        ], $this->grantClaims));

        return Http::response(array_replace([
            'access_token' => $token, 'token_type' => 'DPoP',
            'issued_token_type' => 'urn:ietf:params:oauth:token-type:access_token',
        ], $this->exchangeResponse));
    }

    private function catalog()
    {
        $this->catalogReads++;

        if ($this->catalogStatus !== 200) {
            return Http::response(['error' => 'nope'], $this->catalogStatus);
        }

        return Http::response(['data' => $this->apps, 'meta' => ['revision' => str_repeat('c', 64), 'refresh_after' => 45]]);
    }

    /** @param array<string, mixed> $claims */
    public static function sign(array $claims): string
    {
        return JWT::encode(array_filter($claims, static fn (mixed $v): bool => $v !== null), TestKeys::pair()['private'], 'RS256', TestKeys::KID);
    }
}

function catalogSession(): Session
{
    $session = session()->driver();
    $session->setId(str_repeat('s', 40));

    return $session;
}

function catalogContext(): AppCatalogContext
{
    return new AppCatalogContext('usr_1', 'org_1');
}

function catalogUser(?string $refresh = 'rt_0', string $organization = 'org_1', array $claims = []): CboxUser
{
    return new CboxUser('usr_1', 'ada@id.test', 'Ada', $organization, ['sub' => 'usr_1', 'org' => $organization, ...$claims], 'at_login', $refresh, null, 300);
}

/** A grant as the store keeps it, expiring in `$seconds`. */
function catalogGrant(int $seconds = 3600, string $subject = 'usr_1', string $organization = 'org_1', array $scopes = ['account:apps:read']): ResourceAuthorizationResult
{
    return new ResourceAuthorizationResult('grant-token', $subject, $organization, CATALOG_ISSUER.'/mcp', $scopes, time() + $seconds, []);
}

function catalogService(int $lockWait = 10): AppCatalog
{
    return new AppCatalog(
        app(AppCatalogStore::class), app(CatalogGrantExchange::class), app(PersonalAppCatalog::class),
        app(IdentityClient::class), Cache::store()->getStore(), 300, $lockWait,
    );
}

/** Sign a local user in with a remembered Cbox ID identity, as an app's callback would. */
function signInLocally(object $test, array $claims = ['org' => 'org_1']): LocalUser
{
    $user = LocalUser::withId(7);
    $sessions = app(SessionIdentityStore::class);
    $sessions->remember(new Identity('usr_1', $claims));
    $sessions->bindTo($user);
    $test->actingAs($user);

    return $user;
}

// --- Sign-in ---------------------------------------------------------------------

it('adds only the catalog scope to the login, and leaves offline_access to the app', function (): void {
    parse_str((string) parse_url(app(IdentityClient::class)->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
    expect(explode(' ', $query['scope']))->toBe(['openid', 'profile', 'email', 'offline_access', 'account:apps:read']);

    config(['cbox-id-client.scopes' => ['openid', 'email'], 'cbox-id-client.app_catalog.enabled' => false]);
    app()->forgetInstance(IdentityClient::class);
    parse_str((string) parse_url(app(IdentityClient::class)->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
    expect($query['scope'])->toBe('openid email');
});

function catalogCallback(): Request
{
    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.verifier' => 'ver_1', 'cbox-id-client.nonce' => 'nonce_1']);

    return Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']);
}

it('makes the sign-in callback an SSO hop: one token call, no exchange', function (): void {
    app(IdentityClient::class)->authenticate(catalogCallback());

    $tokenCalls = Http::recorded(fn (ClientRequest $request): bool => $request->url() === CATALOG_ISSUER.'/oauth/token')->values();
    expect($tokenCalls)->toHaveCount(1)
        ->and($tokenCalls[0][0]['grant_type'])->toBe('authorization_code')
        ->and($this->id->exchanges)->toBe(0);

    // Everything else the callback asked is what the login itself needs.
    $other = Http::recorded(fn (ClientRequest $request): bool => $request->url() !== CATALOG_ISSUER.'/oauth/token')
        ->map(fn (array $pair): string => (string) parse_url($pair[0]->url(), PHP_URL_PATH))->unique()->sort()->values()->all();
    expect($other)->toBe(['/.well-known/jwks.json', '/.well-known/openid-configuration', '/oauth/userinfo']);
});

it('seals the login token and the refresh token at sign-in, and exchanges on the first read', function (): void {
    app(IdentityClient::class)->authenticate(catalogCallback());
    $session = session()->driver();
    $store = app(AppCatalogStore::class);

    expect($store->loginToken($session, catalogContext())?->token)->toBe('at_login')
        ->and($store->refreshToken($session, 'usr_1')?->token)->toBe('rt_0')
        ->and($store->grant($session, catalogContext()))->toBeNull()
        ->and(json_encode(session()->all()))->not->toContain('rt_0')->not->toContain('"at_login"');

    expect(catalogService()->read($session, catalogContext())['apps'])->toHaveCount(3)
        ->and($this->id->subjectTokens)->toBe(['at_login'])
        ->and($this->id->refreshes)->toBe(0)
        ->and($store->loginToken($session, catalogContext()))->toBeNull()
        ->and($store->grant($session, catalogContext()))->not->toBeNull();
});

it('keeps no refresh token when the login did not get one, and still exchanges the login token', function (): void {
    $this->id->refreshIssues = false;
    app(IdentityClient::class)->authenticate(catalogCallback());
    $session = session()->driver();

    expect(app(AppCatalogStore::class)->refreshToken($session, 'usr_1'))->toBeNull();
    expect(catalogService()->read($session, catalogContext())['apps'])->toHaveCount(3);
});

it('does nothing at sign-in while the catalog is off', function (): void {
    config(['cbox-id-client.app_catalog.enabled' => false]);
    app()->forgetInstance(IdentityClient::class);

    app(IdentityClient::class)->authenticate(catalogCallback());

    expect(session()->has(AppCatalogStore::SESSION_KEY))->toBeFalse();
});

it('refreshes first when the login token has expired, then exchanges', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberLoginToken($session, new StoredLoginToken('at_login', 'usr_1', 'org_1', time() + 10));
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));

    catalogService()->read($session, catalogContext());

    expect($this->id->refreshes)->toBe(1)->and($this->id->subjectTokens)->toBe(['at_refreshed_1']);
});

it('falls back to the refresh token when ID refuses the login token', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberLoginToken($session, new StoredLoginToken('at_login', 'usr_1', 'org_1', time() + 300));
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));
    $this->id->exchangeStatus = 400;

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(SignInRequired::class)
        ->and($this->id->subjectTokens)->toBe(['at_login', 'at_refreshed_1'])
        ->and($store->loginToken($session, catalogContext()))->toBeNull();
});

it('keeps the login token through an ID outage', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberLoginToken($session, new StoredLoginToken('at_login', 'usr_1', 'org_1', time() + 300));
    $this->id->exchangeStatus = 503;

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(CatalogUnavailable::class)
        ->and($store->loginToken($session, catalogContext())?->token)->toBe('at_login');
});

it('never exchanges a login token for another person or organization', function (): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberLoginToken($session, new StoredLoginToken('at_login', 'usr_1', 'org_1', time() + 300));

    expect(fn () => catalogService()->read($session, new AppCatalogContext('usr_1', 'org_2')))->toThrow(SignInRequired::class)
        ->and(fn () => catalogService()->read($session, new AppCatalogContext('usr_2', 'org_1')))->toThrow(SignInRequired::class)
        ->and($this->id->exchanges)->toBe(0);
});

it('refuses catalog grants that are not exactly this person, team, client, scope and key', function (array $claims): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberLoginToken($session, new StoredLoginToken('at_login', 'usr_1', 'org_1', time() + 300));
    $this->id->grantClaims = $claims;

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(SignInRequired::class)
        ->and(app(AppCatalogStore::class)->grant($session, catalogContext()))->toBeNull();
})->with([
    'subject' => [['sub' => 'usr_other']], 'team' => [['org' => 'org_other']],
    'client' => [['client_id' => 'other_client']], 'issuer' => [['iss' => 'https://other.test']],
    'audience' => [['aud' => 'https://other.test/mcp']], 'multiple audiences' => [['aud' => [CATALOG_ISSUER.'/mcp', 'https://other.test']]],
    'actor' => [['act' => ['sub' => 'support']]], 'expired' => [['exp' => time() - 100]],
    'scope' => [['scope' => 'openid']], 'expanded scope' => [['scope' => 'account:apps:read openid']],
    'holder' => [['cnf' => ['jkt' => 'wrong-key']]], 'missing holder' => [['cnf' => null]],
]);

it('refuses a bearer downgrade and a wrong issued token type', function (array $response): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberLoginToken($session, new StoredLoginToken('at_login', 'usr_1', 'org_1', time() + 300));
    $this->id->exchangeResponse = $response;

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(SignInRequired::class);
})->with([[['token_type' => 'Bearer']], [['issued_token_type' => 'urn:ietf:params:oauth:token-type:id_token']]]);

it('keeps nothing for a support session or a cookie session', function (): void {
    expect(CboxId::rememberAppCatalogSignIn(catalogUser(claims: ['act' => ['sub' => 'staff']])))->toBeFalse()
        ->and(session()->has(AppCatalogStore::SESSION_KEY))->toBeFalse();

    config(['session.driver' => 'cookie']);
    expect(CboxId::rememberAppCatalogSignIn(catalogUser()))->toBeFalse();
});

// --- Silent renewal ----------------------------------------------------------------

it('renews a missing grant on the server with the refresh token, and stores the rotated one', function (): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));

    $catalog = catalogService()->read($session, catalogContext());

    expect(array_column($catalog['apps'], 'key'))->toBe(['work', 'billing', 'agents'])
        ->and($this->id->refreshes)->toBe(1)
        ->and($this->id->subjectTokens)->toBe(['at_refreshed_1'])
        ->and(app(AppCatalogStore::class)->refreshToken($session, 'usr_1')?->token)->toBe('rt_1');

    // A held grant and a fresh list: nothing more is asked.
    catalogService()->read($session, catalogContext());
    expect($this->id->refreshes)->toBe(1)->and($this->id->catalogReads)->toBe(1);
});

it('renews within five minutes of expiry and not before', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));

    $store->rememberGrant($session, catalogGrant(301));
    catalogService()->read($session, catalogContext());
    expect($this->id->refreshes)->toBe(0);

    $store->rememberGrant($session, catalogGrant(299));
    catalogService()->read($session, catalogContext());
    expect($this->id->refreshes)->toBe(1)->and($store->grant($session, catalogContext())?->expiresAt)->toBeGreaterThan(time() + 3000);
});

it('never presents a spent refresh token when a stale request wrote the session back', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));
    $stale = $session->get(AppCatalogStore::SESSION_KEY.'.refresh');

    $this->id->grantLifetime = 200;
    catalogService()->read($session, catalogContext());

    // A page request that loaded the session before the renewal saves it after: the
    // session holds rt_0 again, and the grant it holds is the old one.
    $session->put(AppCatalogStore::SESSION_KEY.'.refresh', $stale);
    $session->forget(AppCatalogStore::SESSION_KEY.'.grants');

    catalogService()->read($session, catalogContext());

    expect($this->id->replays)->toBe(0)
        ->and($this->id->spent)->toBe(['rt_0', 'rt_1'])
        ->and($store->refreshToken($session, 'usr_1')?->token)->toBe('rt_2');
});

it('adopts the grant another tab renewed instead of refreshing again', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));
    catalogService()->read($session, catalogContext());

    // This request's session never saw the other tab's write.
    $session->forget(AppCatalogStore::SESSION_KEY.'.grants');
    catalogService()->read($session, catalogContext());

    expect($this->id->refreshes)->toBe(1)->and($this->id->exchanges)->toBe(1);
});

it('waits for the renewal lock and keeps a still valid grant when it cannot get it', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));
    $store->rememberGrant($session, catalogGrant(120));
    $lock = Cache::lock($store->lockKey($session), 30);
    $lock->get();

    expect(catalogService(lockWait: 0)->read($session, catalogContext())['apps'])->toHaveCount(3)
        ->and($this->id->refreshes)->toBe(0);

    $store->forgetGrant($session, catalogContext());
    expect(fn () => catalogService(lockWait: 0)->read($session, catalogContext()))->toThrow(CatalogUnavailable::class);

    $lock->release();
});

it('asks for a sign-in without spending anything when there is no refresh token', function (): void {
    expect(fn () => catalogService()->read(catalogSession(), catalogContext()))->toThrow(SignInRequired::class);

    expect($this->id->refreshes + $this->id->exchanges)->toBe(0);
});

it('keeps using a valid grant inside the renewal window when no renewal is possible', function (): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberGrant($session, catalogGrant(200));

    $catalog = catalogService()->read($session, catalogContext());

    expect($catalog['apps'])->toHaveCount(3)->and($catalog['refresh_after'])->toBe(15);
});

it('drops a refused refresh token and asks for a sign-in', function (): void {
    $session = catalogSession();
    $this->id->refreshStatus = 400;
    app(AppCatalogStore::class)->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(SignInRequired::class)
        ->and(app(AppCatalogStore::class)->refreshToken($session, 'usr_1'))->toBeNull();
});

it('keeps the refresh token through an ID outage and reports it as unavailable', function (int $status): void {
    $session = catalogSession();
    $this->id->refreshStatus = $status;
    app(AppCatalogStore::class)->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(CatalogUnavailable::class)
        ->and(app(AppCatalogStore::class)->refreshToken($session, 'usr_1')?->token)->toBe('rt_0');
})->with([500, 503, 429]);

it('reports a connection failure as unavailable', function (): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));
    $this->id->connectionFails = true;

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(CatalogUnavailable::class)
        ->and(app(AppCatalogStore::class)->refreshToken($session, 'usr_1')?->token)->toBe('rt_0');
});

it('does not spend a refresh token on another organization', function (): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));

    expect(fn () => catalogService()->read($session, new AppCatalogContext('usr_1', 'org_2')))->toThrow(SignInRequired::class)
        ->and($this->id->refreshes)->toBe(0);
});

it('never hands one person the refresh token kept for another', function (): void {
    $session = catalogSession();
    app(AppCatalogStore::class)->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));

    expect(app(AppCatalogStore::class)->refreshToken($session, 'usr_2'))->toBeNull()
        ->and(fn () => catalogService()->read($session, new AppCatalogContext('usr_2', 'org_1')))->toThrow(SignInRequired::class);
});

it('renews once when ID refuses a grant it issued', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));
    $store->rememberGrant($session, catalogGrant());
    $this->id->catalogStatus = 401;

    expect(fn () => catalogService()->read($session, catalogContext()))->toThrow(SignInRequired::class)
        ->and($this->id->refreshes)->toBe(1)
        ->and($this->id->catalogReads)->toBe(2);
});

it('refuses grants for another person, team or scope under the expected key', function (ResourceAuthorizationResult $grant): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberGrant($session, $grant);
    $sealed = $session->get(AppCatalogStore::SESSION_KEY.'.grants');
    $session->forget(AppCatalogStore::SESSION_KEY.'.grants');
    $bucket = array_key_first($sealed);
    $store->rememberGrant($session, catalogGrant());
    $mine = array_key_first($session->get(AppCatalogStore::SESSION_KEY.'.grants'));
    $session->put(AppCatalogStore::SESSION_KEY.'.grants.'.$mine.'.grant', $sealed[$bucket]['grant']);

    expect($store->grant($session, catalogContext()))->toBeNull();
})->with([
    'person' => fn () => catalogGrant(subject: 'usr_2'),
    'team' => fn () => catalogGrant(organization: 'org_2'),
    'wider scope' => fn () => catalogGrant(scopes: ['account:apps:read', 'openid']),
]);

it('refuses a cookie session driver', function (): void {
    config(['session.driver' => 'cookie']);

    expect(fn () => catalogService()->read(catalogSession(), catalogContext()))->toThrow(CatalogUnavailable::class);
});

// --- Routes --------------------------------------------------------------------------

it('serves the catalog as private JSON in Cbox ID order, without credentials', function (): void {
    signInLocally($this);
    app(AppCatalogStore::class)->rememberGrant(session()->driver(), catalogGrant());

    $response = $this->getJson('https://app.test/apps/catalog')->assertOk();

    expect($response->json('apps.*.key'))->toBe(['work', 'billing', 'agents'])
        ->and($response->json('apps.0'))->toBe(['key' => 'work', 'name' => 'Work', 'description' => 'Time', 'launch_url' => 'https://work.test/', 'icon_url' => 'https://id.test/app-icons/1/a', 'icon_dark_url' => 'https://id.test/app-icons/1/b'])
        ->and($response->json('revision'))->toBe(str_repeat('c', 64))
        ->and($response->json('refresh_after'))->toBe(45)
        ->and($response->getContent())->not->toContain('grant-token');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('answers 401 with where to sign in, never a reconnect link', function (): void {
    signInLocally($this);

    $response = $this->getJson('https://app.test/apps/catalog?return_to=/projects/42', ['Referer' => 'https://app.test/elsewhere'])->assertUnauthorized();

    expect($response->json())->toBe(['signin_url' => '/apps/signin?return_to=%2Fprojects%2F42&organization=org_1']);
});

it('sends the page to sign in at most once per five minutes, so a broken exchange cannot loop', function (): void {
    signInLocally($this);

    $this->getJson('https://app.test/apps/catalog')->assertUnauthorized();
    $this->getJson('https://app.test/apps/catalog')->assertStatus(503)->assertExactJson(['error' => 'catalog_unavailable']);

    session()->put(AppCatalogController::SIGNIN_SENT_KEY, time() - 301);
    $this->getJson('https://app.test/apps/catalog')->assertUnauthorized();
});

it('takes the return path from the page when the request names none, and only a local one', function (string $referer, string $expected): void {
    signInLocally($this);

    expect($this->getJson('https://app.test/apps/catalog', ['Referer' => $referer])->json('signin_url'))
        ->toBe('/apps/signin?return_to='.urlencode($expected).'&organization=org_1');
})->with([
    'same origin' => ['https://app.test/projects?tab=2', '/projects?tab=2'],
    'other host' => ['https://evil.test/projects', '/'],
    'other scheme' => ['http://app.test/projects', '/'],
]);

it('points at the app\'s own sign-in launch when one is configured', function (): void {
    Route::get('/login/launch', fn () => 'launch')->name('login.launch');
    app('router')->getRoutes()->refreshNameLookups();
    config(['cbox-id-client.app_catalog.signin_route' => 'login.launch']);
    signInLocally($this);

    expect($this->getJson('https://app.test/apps/catalog?return_to=/x')->json('signin_url'))->toBe('/login/launch?return_to=%2Fx&organization=org_1');
});

it('answers 503 while Cbox ID is unavailable', function (): void {
    signInLocally($this);
    app(AppCatalogStore::class)->rememberGrant(session()->driver(), catalogGrant());
    $this->id->catalogStatus = 500;

    $this->getJson('https://app.test/apps/catalog')->assertStatus(503)->assertExactJson(['error' => 'catalog_unavailable']);
});

it('refuses a guest, a support session and a session bound to another local user', function (): void {
    $this->getJson('https://app.test/apps/catalog')->assertUnauthorized();

    signInLocally($this, ['org' => 'org_1', 'act' => ['sub' => 'staff']]);
    $this->getJson('https://app.test/apps/catalog')->assertForbidden();

    signInLocally($this);
    $this->actingAs(LocalUser::withId(8));
    $this->getJson('https://app.test/apps/catalog')->assertForbidden();
});

it('starts the app sign-in for the organization and comes back to the page', function (): void {
    $response = $this->get('https://app.test/apps/signin?return_to=/projects/42&organization=org_1')->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['organization'])->toBe('org_1')
        ->and(explode(' ', $query['scope']))->toContain('account:apps:read')
        ->and(session('url.intended'))->toBe('https://app.test/projects/42');
});

it('only accepts a local return path and a well-formed organization at sign-in', function (string $returnTo): void {
    $response = $this->get('https://app.test/apps/signin?'.http_build_query(['return_to' => $returnTo, 'organization' => 'org 1/../x']))->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->not->toHaveKey('organization')->and(session('url.intended'))->toBe('https://app.test');
})->with(['https://evil.test/', '//evil.test/', '/\\evil.test', "/a\nb", 'relative']);

it('runs the browser authorization for a grant, bound to a DPoP key, and keeps it', function (): void {
    signInLocally($this);

    $location = (string) $this->get('https://app.test/apps/authorize?return_to=/projects/42')->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['resource'])->toBe(CATALOG_ISSUER.'/mcp')
        ->and($query['scope'])->toBe('account:apps:read')
        ->and($query['organization'])->toBe('org_1')
        ->and($query['redirect_uri'])->toBe('https://app.test/apps/callback')
        ->and($query)->toHaveKey('dpop_jkt');

    $this->get('https://app.test/apps/callback?code=c1&state='.$query['state'])->assertRedirect('/projects/42');

    expect(app(AppCatalogStore::class)->grant(session()->driver(), catalogContext()))->not->toBeNull();
});

it('keeps no grant from a callback for someone else or an unsolicited one', function (): void {
    signInLocally($this);
    $location = (string) $this->get('https://app.test/apps/authorize')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $this->get('https://app.test/apps/callback?code=c1&state='.str_repeat('a', 64))->assertRedirect('/');

    $this->id->grantClaims = ['sub' => 'usr_2'];
    $this->get('https://app.test/apps/callback?code=c1&state='.$query['state'])->assertRedirect('/');

    expect(app(AppCatalogStore::class)->grant(session()->driver(), catalogContext()))->toBeNull();
});

it('serves the catalog under a configured path', function (): void {
    config(['cbox-id-client.app_catalog.path' => '/switcher/apps']);
    CboxId::appCatalogRoutes();
    app('router')->getRoutes()->refreshNameLookups();
    signInLocally($this);
    app(AppCatalogStore::class)->rememberGrant(session()->driver(), catalogGrant());

    $this->getJson('https://app.test/switcher/apps')->assertOk();
});

it('forgets the grant and the refresh token at logout', function (): void {
    $session = catalogSession();
    $store = app(AppCatalogStore::class);
    $store->rememberRefreshToken($session, new StoredRefreshToken('rt_0', 'usr_1', 'org_1'));
    $store->rememberGrant($session, catalogGrant());

    event(new Logout('web', LocalUser::withId(7)));

    expect($session->has(AppCatalogStore::SESSION_KEY))->toBeFalse()
        ->and($store->refreshToken($session, 'usr_1'))->toBeNull();
});
