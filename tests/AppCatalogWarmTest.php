<?php

declare(strict_types=1);

use Cbox\Id\Client\AppCatalog\AppCatalogContext;
use Cbox\Id\Client\AppCatalog\MachineAppCatalog;
use Cbox\Id\Client\AppCatalog\WarmAppCatalog;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Testing\TestKeys;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Illuminate\Support\defer;

/*
 * Opening the app switcher reads the kept catalog. A sign-in queues the first read, and
 * a kept catalog that has gone stale is served as it is and read again in the
 * background. Only a person the app has never read a catalog for waits for one.
 */

const WARM_ISSUER = 'https://id.test';

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => WARM_ISSUER,
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

    $this->catalogReads = 0;
    $this->catalogStatus = 200;
    $this->catalogDown = false;
    $this->act = null;
    $this->org = 'org_1';
    $reads = &$this->catalogReads;
    $status = &$this->catalogStatus;
    $down = &$this->catalogDown;
    $act = &$this->act;
    $org = &$this->org;

    Http::fake(static function (ClientRequest $request) use (&$reads, &$status, &$down, &$act, &$org) {
        $url = $request->url();

        if (str_starts_with($url, WARM_ISSUER.'/api/v1/app-catalog/for-user')) {
            $reads++;

            if ($down) {
                throw new ConnectionException('timed out');
            }

            return $status !== 200 ? Http::response([], $status) : Http::response([
                'data' => [['key' => 'work', 'name' => 'Work', 'description' => null, 'launch_url' => 'https://work.test/', 'icon_url' => null, 'icon_dark_url' => null]],
                'meta' => ['revision' => str_pad(dechex($reads), 64, 'd', STR_PAD_LEFT), 'refresh_after' => 120],
            ]);
        }

        return match (true) {
            str_ends_with($url, '/.well-known/openid-configuration') => Http::response([
                'issuer' => WARM_ISSUER,
                'authorization_endpoint' => WARM_ISSUER.'/oauth/authorize',
                'token_endpoint' => WARM_ISSUER.'/oauth/token',
                'userinfo_endpoint' => WARM_ISSUER.'/oauth/userinfo',
                'jwks_uri' => WARM_ISSUER.'/.well-known/jwks.json',
            ]),
            str_ends_with($url, '/.well-known/jwks.json') => Http::response(TestKeys::pair()['jwks']),
            $url === WARM_ISSUER.'/oauth/token' && $request['grant_type'] === 'authorization_code' => Http::response([
                'access_token' => 'at_login', 'expires_in' => 300,
                'id_token' => JWT::encode(array_filter([
                    'iss' => WARM_ISSUER, 'aud' => 'work-browser', 'sub' => 'usr_1', 'org' => $org, 'email' => 'a@id.test', 'name' => 'Ada',
                    'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 300, 'act' => $act,
                ]), TestKeys::pair()['private'], 'RS256', TestKeys::KID),
            ]),
            $url === WARM_ISSUER.'/oauth/token' => Http::response(['access_token' => 'mt_1', 'expires_in' => 900]),
            default => Http::response(['error' => 'unexpected'], 500),
        };
    });

    CboxId::appCatalogRoutes();
    app('router')->getRoutes()->refreshNameLookups();
    app('url')->forceRootUrl('https://app.test');
});

function warmCallback(): void
{
    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.verifier' => 'ver_1', 'cbox-id-client.nonce' => 'nonce_1']);
    app(IdentityClient::class)->authenticate(Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']));
}

function warmSignedIn(object $test): void
{
    $user = LocalUser::withId(7);
    $sessions = app(SessionIdentityStore::class);
    $sessions->remember(new Identity('usr_1', ['org' => 'org_1']));
    $sessions->bindTo($user);
    $test->actingAs($user);
}

it('queues the first catalog read at sign-in, and the callback itself reads nothing', function (): void {
    Queue::fake();

    warmCallback();

    Queue::assertPushed(WarmAppCatalog::class, static fn (WarmAppCatalog $job): bool => $job->subject === 'usr_1' && $job->organizationId === 'org_1');
    expect($this->catalogReads)->toBe(0);
});

it('opens the switcher from the kept catalog once the queued read has run', function (): void {
    (new WarmAppCatalog('usr_1', 'org_1'))->handle(app(MachineAppCatalog::class));
    $calls = Http::recorded()->count();
    warmSignedIn($this);

    $this->getJson('https://app.test/apps/catalog')->assertOk()->assertJsonPath('apps.0.key', 'work');

    expect(Http::recorded()->count())->toBe($calls);
});

it('queues nothing at sign-in when there is nothing to warm', function (Closure $arrange): void {
    Queue::fake();
    $arrange($this);
    app()->forgetInstance(IdentityClient::class);

    warmCallback();

    Queue::assertNotPushed(WarmAppCatalog::class);
})->with([
    'the catalog is off' => [static fn () => config(['cbox-id-client.app_catalog.enabled' => false])],
    'grant mode' => [static fn () => config(['cbox-id-client.app_catalog.mode' => 'grant', 'cbox-id-client.scopes' => ['openid', 'profile', 'email', 'account:apps:read']])],
    'no organization' => [static fn (object $test) => $test->org = null],
    'a support session' => [static fn (object $test) => $test->act = ['sub' => 'staff_1']],
    'warming is off' => [static fn () => config(['cbox-id-client.app_catalog.warm' => false])],
]);

it('reads after the response at sign-in in an app with no queue worker', function (): void {
    warmCallback();
    expect($this->catalogReads)->toBe(0);

    defer()->invoke();

    expect($this->catalogReads)->toBe(1);
});

it('never fails a sign-in because warming could not be queued', function (): void {
    config(['cbox-id-client.background.queue_connection' => 'missing']);
    Exceptions::fake();

    warmCallback();

    expect(app(SessionIdentityStore::class)->identity()?->subject)->toBe('usr_1');
});

it('serves a stale catalog at once and reads it again in the background', function (): void {
    Queue::fake();
    warmSignedIn($this);
    $first = $this->getJson('https://app.test/apps/catalog')->assertOk()->json('revision');
    $this->travel(3)->days();

    $response = $this->getJson('https://app.test/apps/catalog')->assertOk();

    expect($response->json('revision'))->toBe($first)
        ->and($response->json('refresh_after'))->toBe(MachineAppCatalog::STALE_REFRESH_AFTER)
        ->and($this->catalogReads)->toBe(1);
    Queue::assertPushed(WarmAppCatalog::class, 1);
});

it('shows the new catalog on the read after the background one', function (): void {
    warmSignedIn($this);
    $first = $this->getJson('https://app.test/apps/catalog')->json('revision');
    $this->travel(121)->seconds();

    // The sync driver: the stale read is answered, then the refresh runs after it.
    expect($this->getJson('https://app.test/apps/catalog')->json('revision'))->toBe($first);
    expect($this->catalogReads)->toBe(2)
        ->and($this->getJson('https://app.test/apps/catalog')->json('revision'))->not->toBe($first);
});

it('keeps the catalog for thirty days', function (): void {
    Queue::fake();
    warmSignedIn($this);
    $this->getJson('https://app.test/apps/catalog')->assertOk();
    $this->catalogDown = true;

    $this->travel(29)->days();
    $this->getJson('https://app.test/apps/catalog')->assertOk();

    $this->travel(2)->days();
    $this->getJson('https://app.test/apps/catalog')->assertStatus(503);
});

it('drops the kept catalog when the background read says the person left', function (): void {
    $catalog = app(MachineAppCatalog::class);
    $catalog->refresh(new AppCatalogContext('usr_1', 'org_1'));
    $this->catalogStatus = 404;

    (new WarmAppCatalog('usr_1', 'org_1'))->handle($catalog);
    warmSignedIn($this);

    $this->getJson('https://app.test/apps/catalog')->assertUnauthorized();
});

it('keeps the kept catalog when the background read fails', function (): void {
    $catalog = app(MachineAppCatalog::class);
    $catalog->refresh(new AppCatalogContext('usr_1', 'org_1'));
    $this->catalogDown = true;

    (new WarmAppCatalog('usr_1', 'org_1'))->handle($catalog);
    warmSignedIn($this);

    $this->getJson('https://app.test/apps/catalog')->assertOk();
});

it('queues one background read for a burst of stale reads', function (): void {
    Queue::fake();
    warmSignedIn($this);
    $this->getJson('https://app.test/apps/catalog');
    $this->travel(121)->seconds();

    $this->getJson('https://app.test/apps/catalog');
    $this->getJson('https://app.test/apps/catalog');

    Queue::assertPushed(WarmAppCatalog::class, 1);
});
