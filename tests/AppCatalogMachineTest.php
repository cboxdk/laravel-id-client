<?php

declare(strict_types=1);

use Cbox\Id\Client\AppCatalog\AppCatalogStore;
use Cbox\Id\Client\AppCatalog\Http\AppCatalogController;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Testing\TestKeys;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
 * The app catalog in machine mode (the default): the app reads the person's catalog
 * with its own `app_catalog:resolve` token, caches it, serves the last good answer when
 * Cbox ID cannot answer, and falls back to the person's grant while the client has not
 * been given the scope.
 */

const MACHINE_ISSUER = 'https://id.test';

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => MACHINE_ISSUER,
        'cbox-id-client.client_id' => 'work-browser',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
        'cbox-id-client.scopes' => ['openid', 'profile', 'email'],
        'cbox-id-client.app_catalog.enabled' => true,
        'cbox-id-client.app_catalog.mode' => 'machine',
        'session.driver' => 'array',
        'app.url' => 'https://app.test',
    ]);
    app()->forgetInstance(IdentityClient::class);
    CboxId::clearResolvedInstances();
    Cache::flush();
    Http::preventStrayRequests();

    $this->id = new FakeMachineCatalogId;
    $this->id->install();

    CboxId::appCatalogRoutes();
    app('router')->getRoutes()->refreshNameLookups();
    app('url')->forceRootUrl('https://app.test');
});

/** A Cbox ID with the machine catalog endpoint. Fields steer the next answers. */
final class FakeMachineCatalogId
{
    /** @var int|null the status the token endpoint answers a client_credentials request with */
    public ?int $tokenStatus = null;

    public string $tokenError = 'invalid_scope';

    public int $catalogStatus = 200;

    /** @var array<string, mixed> */
    public array $catalogBody = [];

    public bool $catalogDown = false;

    /** @var list<array<string, mixed>>|null the apps the machine read answers; null for {@see apps()} */
    public ?array $apps = null;

    public int $catalogReads = 0;

    public int $tokenRequests = 0;

    public int $personalReads = 0;

    public int $refreshAfter = 120;

    /** @var list<string> */
    public array $bearers = [];

    public function install(): void
    {
        Http::fake(function (ClientRequest $request) {
            $url = $request->url();

            return match (true) {
                str_ends_with($url, '/.well-known/openid-configuration') => Http::response([
                    'issuer' => MACHINE_ISSUER,
                    'authorization_endpoint' => MACHINE_ISSUER.'/oauth/authorize',
                    'token_endpoint' => MACHINE_ISSUER.'/oauth/token',
                    'userinfo_endpoint' => MACHINE_ISSUER.'/oauth/userinfo',
                    'jwks_uri' => MACHINE_ISSUER.'/.well-known/jwks.json',
                ]),
                str_ends_with($url, '/.well-known/jwks.json') => Http::response(TestKeys::pair()['jwks']),
                str_ends_with($url, '/oauth/userinfo') => Http::response(['sub' => 'usr_1', 'org' => 'org_1']),
                str_starts_with($url, MACHINE_ISSUER.'/api/v1/app-catalog/for-user') => $this->forUser($request),
                str_starts_with($url, MACHINE_ISSUER.'/api/v1/me/apps') => $this->personal(),
                $url === MACHINE_ISSUER.'/oauth/token' => $this->token($request),
                default => Http::response(['error' => 'unexpected'], 500),
            };
        });
    }

    private function token(ClientRequest $request)
    {
        if ($request['grant_type'] === 'authorization_code') {
            return Http::response([
                'access_token' => 'at_login', 'expires_in' => 300,
                'id_token' => JWT::encode(['iss' => MACHINE_ISSUER, 'aud' => 'work-browser', 'sub' => 'usr_1', 'org' => 'org_1', 'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 300], TestKeys::pair()['private'], 'RS256', TestKeys::KID),
            ]);
        }

        expect($request['grant_type'])->toBe('client_credentials')
            ->and($request['scope'])->toBe('app_catalog:resolve');
        $this->tokenRequests++;

        if ($this->tokenStatus !== null) {
            return Http::response(['error' => $this->tokenError], $this->tokenStatus);
        }

        return Http::response(['access_token' => 'mt_'.$this->tokenRequests, 'token_type' => 'Bearer', 'expires_in' => 3600]);
    }

    private function forUser(ClientRequest $request)
    {
        $this->catalogReads++;
        $this->bearers[] = $request->header('Authorization')[0] ?? '';

        if ($this->catalogDown) {
            throw new ConnectionException('timed out');
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        expect($query)->toBe(['user_id' => 'usr_1', 'organization_id' => 'org_1']);

        if ($this->catalogStatus !== 200) {
            return Http::response($this->catalogBody, $this->catalogStatus);
        }

        return Http::response(['data' => $this->apps ?? self::apps(), 'meta' => ['revision' => str_repeat('d', 64), 'refresh_after' => $this->refreshAfter]]);
    }

    private function personal()
    {
        $this->personalReads++;

        return Http::response(['data' => self::apps(), 'meta' => ['revision' => str_repeat('e', 64), 'refresh_after' => 45]]);
    }

    /** @return list<array<string, mixed>> */
    public static function apps(): array
    {
        return [
            ['key' => 'work', 'name' => 'Work', 'description' => 'Time', 'launch_url' => 'https://work.test/', 'icon_url' => 'https://id.test/app-icons/1/a', 'icon_dark_url' => null],
            ['key' => 'billing', 'name' => 'Billing', 'description' => null, 'launch_url' => 'https://billing.test/', 'icon_url' => null, 'icon_dark_url' => null],
        ];
    }
}

function machineSignIn(object $test): void
{
    $user = LocalUser::withId(7);
    $sessions = app(SessionIdentityStore::class);
    $sessions->remember(new Identity('usr_1', ['org' => 'org_1']));
    $sessions->bindTo($user);
    $test->actingAs($user);
}

it('reads the catalog with the app\'s own token and keeps nothing in the session', function (): void {
    machineSignIn($this);

    $response = $this->getJson('https://app.test/apps/catalog')->assertOk();

    expect($response->json('apps.*.key'))->toBe(['work', 'billing'])
        ->and($response->json('revision'))->toBe(str_repeat('d', 64))
        ->and($response->json('refresh_after'))->toBe(120)
        ->and($response->getContent())->not->toContain('mt_1')
        ->and($this->id->bearers)->toBe(['Bearer mt_1'])
        ->and($this->id->personalReads)->toBe(0)
        ->and(session()->has(AppCatalogStore::SESSION_KEY))->toBeFalse();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('answers from the cache until refresh_after, at least a minute', function (): void {
    machineSignIn($this);
    $this->id->refreshAfter = 10;

    expect($this->getJson('https://app.test/apps/catalog')->assertOk()->json('refresh_after'))->toBe(60);
    $this->travel(30)->seconds();
    expect($this->getJson('https://app.test/apps/catalog')->assertOk()->json('refresh_after'))->toBe(30)
        ->and($this->id->catalogReads)->toBe(1)
        ->and($this->id->tokenRequests)->toBe(1);

    $this->travel(31)->seconds();
    $this->getJson('https://app.test/apps/catalog')->assertOk();
    expect($this->id->catalogReads)->toBe(2)->and($this->id->tokenRequests)->toBe(1);
});

it('serves the last good answer while Cbox ID errors or times out', function (string $failure): void {
    machineSignIn($this);
    $this->getJson('https://app.test/apps/catalog')->assertOk();
    $this->travel(121)->seconds();

    if ($failure === 'timeout') {
        $this->id->catalogDown = true;
    } else {
        $this->id->catalogStatus = (int) $failure;
    }

    $response = $this->getJson('https://app.test/apps/catalog')->assertOk();
    expect($response->json('apps.*.key'))->toBe(['work', 'billing'])
        ->and($response->json('refresh_after'))->toBe(30)
        ->and($this->id->catalogReads)->toBe(2);

    // Kept for a day, then gone.
    $this->travel(86400)->seconds();
    $this->getJson('https://app.test/apps/catalog')->assertStatus(503)->assertExactJson(['error' => 'catalog_unavailable']);
})->with(['500', '502', '429', 'timeout']);

it('answers 503 when Cbox ID cannot answer and nothing was kept', function (): void {
    machineSignIn($this);
    $this->id->catalogStatus = 500;

    $this->getJson('https://app.test/apps/catalog')->assertStatus(503)->assertExactJson(['error' => 'catalog_unavailable']);

    $this->id->catalogStatus = 200;
    $this->id->catalogDown = true;
    $this->getJson('https://app.test/apps/catalog')->assertStatus(503);
});

it('answers 503 when the machine token cannot be had right now', function (): void {
    machineSignIn($this);
    $this->id->tokenStatus = 503;
    $this->id->tokenError = 'temporarily_unavailable';

    $this->getJson('https://app.test/apps/catalog')->assertStatus(503);
    expect($this->id->personalReads)->toBe(0);
});

it('sends the person to sign in when they are no longer a member, and drops the kept answer', function (): void {
    machineSignIn($this);
    $this->getJson('https://app.test/apps/catalog')->assertOk();
    $this->travel(121)->seconds();
    $this->id->catalogStatus = 404;

    $this->getJson('https://app.test/apps/catalog?return_to=/x')->assertUnauthorized()
        ->assertExactJson(['signin_url' => '/apps/signin?return_to=%2Fx&organization=org_1']);

    // Nothing stale is served for someone who left.
    $this->id->catalogStatus = 500;
    $this->getJson('https://app.test/apps/catalog')->assertStatus(503);
});

it('asks for a new machine token once when Cbox ID refuses the kept one', function (): void {
    machineSignIn($this);
    $this->id->catalogStatus = 401;

    $this->getJson('https://app.test/apps/catalog')->assertStatus(503);
    expect($this->id->tokenRequests)->toBe(2)->and($this->id->catalogReads)->toBe(2);
});

it('falls back to the person\'s grant while the client lacks app_catalog:resolve, and warns once an hour', function (string $how): void {
    Log::spy();
    machineSignIn($this);
    app(AppCatalogStore::class)->rememberGrant(session()->driver(), new ResourceAuthorizationResult(
        'grant-token', 'usr_1', 'org_1', MACHINE_ISSUER.'/mcp', ['account:apps:read'], time() + 3600, [],
    ));

    if ($how === 'token') {
        $this->id->tokenStatus = 400;
    } else {
        $this->id->catalogStatus = 403;
        $this->id->catalogBody = ['error' => $how];
    }

    $response = $this->getJson('https://app.test/apps/catalog')->assertOk();
    expect($response->json('revision'))->toBe(str_repeat('e', 64))->and($this->id->personalReads)->toBe(1);

    // The machine read is not retried on every request, and the warning is not repeated.
    $reads = $this->id->catalogReads;
    $tokens = $this->id->tokenRequests;
    $this->getJson('https://app.test/apps/catalog')->assertOk();
    expect($this->id->catalogReads)->toBe($reads)->and($this->id->tokenRequests)->toBe($tokens);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'app_catalog:resolve'))->once();

    // While the fallback is in use, a sign-in prepares a grant again.
    parse_str((string) parse_url(app(IdentityClient::class)->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
    expect(explode(' ', $query['scope']))->toContain('account:apps:read');

    // Once Cbox ID gives the scope, the machine read takes over again.
    $this->id->tokenStatus = null;
    $this->id->catalogStatus = 200;
    $this->travel(301)->seconds();
    expect($this->getJson('https://app.test/apps/catalog')->assertOk()->json('revision'))->toBe(str_repeat('d', 64));
    parse_str((string) parse_url(app(IdentityClient::class)->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
    expect(explode(' ', $query['scope']))->not->toContain('account:apps:read');
})->with(['token', 'insufficient_scope', 'invalid_scope']);

it('does not ask for account:apps:read at login, and the callback does no catalog work', function (): void {
    parse_str((string) parse_url(app(IdentityClient::class)->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
    expect($query['scope'])->toBe('openid profile email');

    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.verifier' => 'ver_1', 'cbox-id-client.nonce' => 'nonce_1']);
    app(IdentityClient::class)->authenticate(Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']));

    expect(session()->has(AppCatalogStore::SESSION_KEY))->toBeFalse()
        ->and(session()->has(AppCatalogController::SIGNIN_SENT_KEY))->toBeFalse()
        ->and($this->id->tokenRequests)->toBe(0)
        ->and(app(IdentityClient::class)->rememberAppCatalogSignIn(new CboxUser('usr_1', null, null, 'org_1', ['sub' => 'usr_1'], 'at_login', 'rt_0', null, 300)))->toBeFalse();
});

it('works on the cookie session driver, since nothing is kept in the session', function (): void {
    config(['session.driver' => 'cookie']);
    machineSignIn($this);

    $this->getJson('https://app.test/apps/catalog')->assertOk();
});

it('registers the routes without building the identity client', function (): void {
    app()->bind(IdentityClient::class, fn () => throw new RuntimeException('The identity client was built while the routes loaded.'));
    CboxId::clearResolvedInstances();
    config(['cbox-id-client.app_catalog.path' => '/switcher/catalog']);

    CboxId::appCatalogRoutes();

    expect(app('router')->getRoutes()->match(Request::create('https://app.test/switcher/catalog'))->getName())->toBe('cbox-id.apps.catalog');
});

it('reads again past an answer an older SDK cached without the new fields', function (): void {
    machineSignIn($this);

    $this->getJson('https://app.test/apps/catalog')->assertOk();
    expect($this->id->catalogReads)->toBe(1);

    // Rewrite the kept answer into the 0.19 shape: no enabled, no admission_url.
    $store = Cache::store();
    $key = collect(array_keys((fn () => $this->storage)->call($store->getStore())))
        ->first(fn (string $k) => str_contains($k, 'app-catalog:machine:catalog:'));
    $kept = $store->get($key);
    $kept['payload']['apps'] = array_map(fn (array $app) => array_diff_key($app, ['enabled' => 1, 'admission_url' => 1, 'purpose' => 1, 'hue' => 1]), $kept['payload']['apps']);
    $store->put($key, $kept, 3600);

    $this->getJson('https://app.test/apps/catalog')->assertOk()->assertJsonPath('apps.0.enabled', true);
    expect($this->id->catalogReads)->toBe(2);
});

it('leaves out apps the organization does not have unless the switcher asks for them', function (): void {
    machineSignIn($this);
    $this->id->apps = [
        ...FakeMachineCatalogId::apps(),
        ['key' => 'engage', 'name' => 'Engage', 'enabled' => false, 'admission_url' => 'https://engage.test/signup'],
    ];

    // An older switcher reads launch_url as a string: it never sees an app without one.
    expect($this->getJson('https://app.test/apps/catalog')->assertOk()->json('apps.*.key'))->toBe(['work', 'billing'])
        ->and($this->getJson('https://app.test/apps/catalog?include=available')->assertOk()->json('apps.2'))
        ->toMatchArray(['key' => 'engage', 'enabled' => false, 'launch_url' => null, 'admission_url' => 'https://engage.test/signup']);
});
