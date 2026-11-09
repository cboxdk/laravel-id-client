<?php

declare(strict_types=1);

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\Contracts\PermissionDecisions;
use Cbox\Id\Client\Exceptions\TokenRejected;
use Cbox\Id\Client\Facades\CboxIdApi;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Management\ManagementClients;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Support\InternalRoutes;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * CBOX_INTERNAL_ROUTES: HTTPS calls to a listed public host leave for its address
 * inside the cluster, carrying the public host. Nothing above the transport may notice:
 * issuers, discovery and token checks keep seeing the public URL.
 */

const CBOX_ID_SVC = 'http://cbox-id.cbox-id.svc.cluster.local';

/** Boot the application again with these routes, the way an env var would set them. */
function bootWithInternalRoutes(mixed $routes, array $config = []): void
{
    test()->configBeforeBoot = ['cbox-id-client.internal_routes' => $routes] + $config;
    test()->refreshApplication();
}

/** The one recorded request, or a failure naming how many there were. */
function onlyRequest(): Request
{
    $recorded = Http::recorded();
    expect($recorded)->toHaveCount(1);

    return $recorded[0][0];
}

it('sends a mapped host to its internal address with the public host and forwarded headers', function (): void {
    bootWithInternalRoutes('cbox.cboxid.com='.CBOX_ID_SVC.',app.cboxbilling.com=http://cboxbilling.cboxbilling.svc:8080');
    Http::fake();

    Http::withHeaders(['X-Forwarded-Proto' => 'http'])->get('https://cbox.cboxid.com/oauth/token?a=1&b=two');

    $request = onlyRequest();
    expect($request->url())->toBe(CBOX_ID_SVC.'/oauth/token?a=1&b=two')
        ->and($request->header('Host'))->toBe(['cbox.cboxid.com'])
        ->and($request->header('X-Forwarded-Proto'))->toBe(['https'])
        ->and($request->header('X-Forwarded-Host'))->toBe(['cbox.cboxid.com'])
        ->and($request->header('X-Forwarded-Port'))->toBe(['443']);
});

it('keeps the internal port and routes the application\'s own calls too', function (): void {
    bootWithInternalRoutes('app.cboxbilling.com=http://cboxbilling.cboxbilling.svc:8080');
    Http::fake();

    Http::post('https://app.cboxbilling.com:443/api/v1/usage', ['n' => 1]);

    expect(onlyRequest()->url())->toBe('http://cboxbilling.cboxbilling.svc:8080/api/v1/usage')
        ->and(onlyRequest()->header('Host'))->toBe(['app.cboxbilling.com']);
});

it('leaves every other request alone', function (string $url): void {
    bootWithInternalRoutes('cbox.cboxid.com='.CBOX_ID_SVC);
    Http::fake();

    Http::withHeaders(['X-Forwarded-Proto' => 'keep'])->get($url);

    expect(onlyRequest()->url())->toBe($url)
        ->and(onlyRequest()->header('X-Forwarded-Proto'))->toBe(['keep'])
        ->and(onlyRequest()->hasHeader('X-Forwarded-Host'))->toBeFalse();
})->with([
    'an unmapped host' => 'https://api.cboxtax.com/v1/quote',
    'a subdomain of a mapped host' => 'https://eu.cbox.cboxid.com/oauth/token',
    'a host that only ends like one' => 'https://evilcbox.cboxid.com/oauth/token',
    'plain http to a mapped host' => 'http://cbox.cboxid.com/oauth/token',
    'another port on a mapped host' => 'https://cbox.cboxid.com:8443/oauth/token',
]);

it('registers nothing when unset', function (): void {
    bootWithInternalRoutes('');

    expect(app(Factory::class)->getGlobalMiddleware())->toBe([])
        ->and(app(InternalRoutes::class)->isEmpty())->toBeTrue();
});

it('ignores invalid entries, logs why, and keeps the valid ones', function (): void {
    $log = tempnam(sys_get_temp_dir(), 'cbox-routes-');
    bootWithInternalRoutes(implode(',', [
        'cbox.cboxid.com='.CBOX_ID_SVC,
        'app.cboxbilling.com=https://billing.example.com',
        '*.cboxtax.com=http://tax.tax.svc',
        'app.cboxengage.com=http://engage.engage.svc/prefix',
        'no-equals-sign',
        'api.cboxtax.com=ftp://tax.tax.svc',
    ]), [
        'logging.default' => 'routes',
        'logging.channels.routes' => ['driver' => 'single', 'path' => $log],
    ]);
    Http::fake();

    Http::get('https://app.cboxbilling.com/x');

    expect(app(InternalRoutes::class)->all())->toBe(['cbox.cboxid.com' => CBOX_ID_SVC])
        ->and(onlyRequest()->url())->toBe('https://app.cboxbilling.com/x');

    $logged = (string) file_get_contents($log);
    expect(substr_count($logged, 'WARNING: Cbox internal route ignored'))->toBe(5)
        ->and($logged)->toContain('billing.example.com')
        ->and($logged)->toContain('*.cboxtax.com');
    @unlink($log);
});

it('accepts loopback hosts only when allowed, and an array config', function (): void {
    expect(InternalRoutes::fromConfig('id.test=http://localhost:8000')->isEmpty())->toBeTrue()
        ->and(InternalRoutes::fromConfig('id.test=http://localhost:8000', allowLoopback: true)->all())
        ->toBe(['id.test' => 'http://localhost:8000'])
        ->and(InternalRoutes::fromConfig(['ID.Test' => CBOX_ID_SVC.'/', 'x' => 1])->all())
        ->toBe(['id.test' => CBOX_ID_SVC])
        ->and(InternalRoutes::fromConfig(['x' => 1])->rejected)->toHaveCount(1)
        ->and(InternalRoutes::fromConfig(null)->isEmpty())->toBeTrue();
});

describe('the SDK behind internal routes', function (): void {
    beforeEach(function (): void {
        bootWithInternalRoutes('id.test='.CBOX_ID_SVC, [
            'cbox-id-client.issuer' => 'https://id.test',
            'cbox-id-client.client_id' => 'client_1',
            'cbox-id-client.client_secret' => 'secret_1',
            'cbox-id-client.redirect' => 'https://app.test/callback',
        ]);
        Cache::flush();
        fakeCbox();
    });

    it('verifies tokens against the public issuer while discovery and JWKS travel inside', function (): void {
        $verified = (new AccessTokenVerifier(new Discovery('https://id.test', 3600, 10), 'https://id.test', 'https://api.cboxtax.com'))
            ->verify(accessToken());

        expect($verified->subject)->toBe('user_01');

        $urls = Http::recorded()->map(static fn (array $pair): string => $pair[0]->url())->all();
        expect($urls)->toBe([
            CBOX_ID_SVC.'/.well-known/openid-configuration',
            CBOX_ID_SVC.'/.well-known/jwks.json',
        ]);
        Http::assertSent(static fn (Request $request): bool => $request->header('Host') === ['id.test']);
    });

    it('still refuses a token from another issuer', function (): void {
        (new AccessTokenVerifier(new Discovery('https://id.test', 3600, 10), 'https://id.test', 'https://api.cboxtax.com'))
            ->verify(accessToken(['iss' => CBOX_ID_SVC]));
    })->throws(TokenRejected::class, 'issuer did not match');

    it('fetches machine tokens and userinfo through the internal address', function (): void {
        $client = app(IdentityClient::class);

        expect($client->machineToken(['api.read']))->toBe('at_1');
        $client->userInfo('at_1');

        Http::assertSent(static fn (Request $request): bool => $request->url() === CBOX_ID_SVC.'/oauth/token'
            && $request->header('Host') === ['id.test'] && $request->header('X-Forwarded-Proto') === ['https']);
        Http::assertSent(static fn (Request $request): bool => $request->url() === CBOX_ID_SVC.'/oauth/userinfo');
        Http::assertNotSent(static fn (Request $request): bool => str_starts_with($request->url(), 'https://id.test'));
    });

    it('keeps the https check on permission decisions and routes the call', function (): void {
        Http::fake(['*/oauth/decisions' => Http::response(['mode' => 'rbac', 'subject' => ['type' => 'user', 'id' => 'user-1'], 'organization' => 'org-1', 'client_id' => 'client_1', 'org_role' => 'member', 'organization_active' => true, 'allowed' => true, 'results' => [['permission' => 'stache:exec', 'allowed' => true]]])]);

        expect(app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec'])->allowed())->toBeTrue();

        Http::assertSent(static fn (Request $request): bool => $request->url() === CBOX_ID_SVC.'/oauth/decisions'
            && $request->header('Host') === ['id.test']);
    });

    it('routes the management transport, which uses the container\'s factory', function (): void {
        config(['cbox-id-client.management.key' => 'cbid_env_1']);
        $this->app->forgetInstance(ManagementClients::class);
        Http::fake(['*/api/v1/apps/app_1' => Http::response(['data' => appData()])]);

        CboxIdApi::environment()->apps->get('app_1');

        Http::assertSent(static fn (Request $request): bool => $request->url() === CBOX_ID_SVC.'/api/v1/apps/app_1'
            && $request->header('Host') === ['id.test'] && $request->hasHeader('Authorization', 'Bearer cbid_env_1'));
    });
});
