<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\SignIn\SignInMode;
use Cbox\Id\Client\SignIn\SignInRoundTrips;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
 * Redirect mode: an app with no sign-in page. `/login`, `/signup`, the launch and the
 * invitation entry answer with a redirect to Cbox ID; the only pages left are a failed
 * sign-in and "you are signed out".
 */

const SIGN_IN_ISSUER = 'https://login.test';

function useRedirectMode(array $config = []): void
{
    config([
        'cbox-id-client.issuer' => SIGN_IN_ISSUER,
        'cbox-id-client.client_id' => 'brain-web',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/login/callback',
        'cbox-id-client.scopes' => ['openid', 'profile', 'email'],
        'cbox-id-client.sign_in.mode' => 'redirect',
        'session.driver' => 'array',
        'app.url' => 'https://app.test',
        'app.name' => 'Brain',
        ...$config,
    ]);

    CboxId::fake();
    CboxId::signInRoutes();

    // The app's own callback, the way the cookbook writes it.
    Route::middleware('web')->get('/login/callback', function (Request $request) {
        try {
            $identity = CboxId::authenticate($request);
        } catch (Throwable $e) {
            return CboxId::signInFailed($e);
        }

        auth()->login(LocalUser::withId(7));

        return redirect()->intended('/home')->with('subject', $identity->id);
    });
    Route::middleware(['web', 'auth'])->get('/projects/{id}', fn (string $id) => 'project '.$id);

    app('router')->getRoutes()->refreshNameLookups();
    app('url')->forceRootUrl('https://app.test');
}

/** @return array<string, string> */
function signInAuthorizeQuery(string $location): array
{
    expect($location)->toStartWith(SIGN_IN_ISSUER.'/');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query;
}

it('registers nothing while the app draws its own sign-in page', function (): void {
    expect(SignInMode::configured())->toBe(SignInMode::Embedded);

    CboxId::signInRoutes();
    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('login'))->toBeFalse()->and(Route::has('signup'))->toBeFalse();
    $this->get('/login')->assertNotFound();
});

it('reads the mode loosely and treats anything else as embedded', function (mixed $value, SignInMode $mode): void {
    config(['cbox-id-client.sign_in.mode' => $value]);

    expect(SignInMode::configured())->toBe($mode)->and(app(IdentityClient::class)->signInMode())->toBe($mode);
})->with([
    ['redirect', SignInMode::Redirect],
    [' Redirect ', SignInMode::Redirect],
    ['embedded', SignInMode::Embedded],
    ['hosted', SignInMode::Embedded],
    [null, SignInMode::Embedded],
]);

it('sends a guest straight to Cbox ID with a code flow, PKCE, state and nonce', function (): void {
    useRedirectMode();

    $response = $this->get('https://app.test/login')->assertRedirect();
    $query = signInAuthorizeQuery((string) $response->headers->get('Location'));

    expect($query)->toMatchArray([
        'response_type' => 'code',
        'client_id' => 'brain-web',
        'redirect_uri' => 'https://app.test/login/callback',
        'scope' => 'openid profile email',
        'code_challenge_method' => 'S256',
    ])->toHaveKeys(['state', 'nonce', 'code_challenge'])
        ->not->toHaveKeys(['prompt', 'organization', 'login_hint'])
        ->and(session('cbox-id-client.state'))->toBe($query['state'])
        ->and(session('cbox-id-client.verifier'))->toBeString();

    expect((string) $response->getContent())->not->toContain('<form')->not->toContain('password');
});

it('keeps the page the guest asked for and lands there after the callback', function (): void {
    useRedirectMode();

    // The `auth` middleware keeps the intended URL and sends the guest to the `login` route.
    $this->get('https://app.test/projects/42')->assertRedirect('https://app.test/login');
    $this->get('https://app.test/login')->assertRedirect();

    CboxId::fake()->signIn('usr_1');
    $this->get('https://app.test/login/callback?code=c&state=s')->assertRedirect('https://app.test/projects/42');
});

it('takes return_to from the link, and only a path in this app', function (string $returnTo, ?string $intended): void {
    useRedirectMode();

    $this->get('https://app.test/login?return_to='.urlencode($returnTo))->assertRedirect();

    expect(session('url.intended'))->toBe($intended);
})->with([
    ['/orgs/acme/memories?q=x', 'https://app.test/orgs/acme/memories?q=x'],
    ['https://evil.test/', null],
    ['//evil.test/', null],
    ['/\\evil.test', null],
]);

it('passes the organization, the address and the prompt a link asks for', function (): void {
    useRedirectMode();

    $query = signInAuthorizeQuery((string) $this->get('https://app.test/login?organization=org_01J&login_hint=ada%40example.com&prompt=select_account&idp_hint=google')->headers->get('Location'));

    expect($query)->toMatchArray(['organization' => 'org_01J', 'login_hint' => 'ada@example.com', 'prompt' => 'select_account', 'idp_hint' => 'google'])
        ->and(session('cbox-id-client.organization'))->toBe('org_01J');
});

it('drops what a link has no business asking for', function (): void {
    useRedirectMode();

    $query = signInAuthorizeQuery((string) $this->get('https://app.test/login?organization=a%20b&login_hint=not-an-address&prompt=none&idp_hint=%3Cscript%3E')->headers->get('Location'));

    expect($query)->not->toHaveKeys(['organization', 'login_hint', 'prompt', 'idp_hint']);
});

it('sends somebody who is already signed in home instead of to Cbox ID', function (): void {
    useRedirectMode(['cbox-id-client.sign_in.home' => '/home']);
    $this->actingAs(LocalUser::withId(7));

    $this->get('https://app.test/login')->assertRedirect('https://app.test/home');
    $this->get('https://app.test/login?return_to=/projects/1')->assertRedirect('https://app.test/projects/1');
    $this->get('https://app.test/signup')->assertRedirect('https://app.test/home');

    // Asking for another account or organization is a sign-in, signed in or not.
    signInAuthorizeQuery((string) $this->get('https://app.test/login?prompt=select_account')->headers->get('Location'));
    signInAuthorizeQuery((string) $this->get('https://app.test/login?organization=org_2')->headers->get('Location'));
});

it('opens Cbox ID on create an account for sign-up', function (): void {
    useRedirectMode();

    $query = signInAuthorizeQuery((string) $this->get('https://app.test/signup?email=ada%40example.com&return_to=/welcome')->headers->get('Location'));

    expect($query)->toMatchArray(['prompt' => 'create', 'login_hint' => 'ada@example.com', 'response_type' => 'code'])
        ->toHaveKeys(['state', 'nonce', 'code_challenge'])
        ->and(session('url.intended'))->toBe('https://app.test/welcome');
});

it('falls back to sign-in when Cbox ID says sign-up is not open', function (): void {
    useRedirectMode();
    $discovery = new Discovery(SIGN_IN_ISSUER, 60, 5);
    Http::fake([SIGN_IN_ISSUER.'/.well-known/openid-configuration' => Http::response([
        'issuer' => SIGN_IN_ISSUER,
        'authorization_endpoint' => SIGN_IN_ISSUER.'/oauth/authorize',
        'prompt_values_supported' => ['none', 'login', 'select_account'],
    ])]);
    $client = new IdentityClient((array) config('cbox-id-client'), $discovery);
    app()->instance(IdentityClient::class, $client);
    CboxId::clearResolvedInstances();

    expect($client->offersSignUp())->toBeFalse()
        ->and(signInAuthorizeQuery((string) $this->get('https://app.test/signup?organization=org_1')->headers->get('Location')))->not->toHaveKeys(['prompt', 'organization']);
});

it('launches into an organization even while somebody is signed in here', function (): void {
    useRedirectMode();
    $this->actingAs(LocalUser::withId(7));

    $query = signInAuthorizeQuery((string) $this->get('https://app.test/login/launch?organization=org_9&return_to=/orgs/nine')->headers->get('Location'));

    expect($query)->toMatchArray(['organization' => 'org_9'])
        ->and(session('url.intended'))->toBe('https://app.test/orgs/nine');
});

it('brings an invited person in bound to the organization they joined', function (): void {
    useRedirectMode();

    $query = signInAuthorizeQuery((string) $this->get('https://app.test/login/invitation?organization=org_9&email=ada%40example.com')->headers->get('Location'));

    expect($query)->toMatchArray(['organization' => 'org_9', 'login_hint' => 'ada@example.com'])->not->toHaveKey('prompt');

    // Without an organization it is an ordinary sign-in.
    expect(signInAuthorizeQuery((string) $this->get('https://app.test/login/invitation')->headers->get('Location')))->not->toHaveKey('organization');
});

it('answers an Inertia visit with a location the page can follow', function (): void {
    useRedirectMode();

    $response = $this->get('https://app.test/login', ['X-Inertia' => 'true'])->assertStatus(409);

    signInAuthorizeQuery((string) $response->headers->get('X-Inertia-Location'));
});

it('leaves a route the app keeps for itself alone', function (): void {
    config(['cbox-id-client.sign_in.signup_path' => null, 'cbox-id-client.sign_in.launch_path' => '']);
    useRedirectMode();

    expect(Route::has('login'))->toBeTrue()
        ->and(Route::has('signup'))->toBeFalse()
        ->and(Route::has('login.launch'))->toBeFalse();
});

it('serves the defaults to an app whose published config predates the block', function (): void {
    config(['cbox-id-client.sign_in' => ['mode' => 'redirect']]);
    useRedirectMode();

    expect(route('login', absolute: false))->toBe('/login')
        ->and(route('signup', absolute: false))->toBe('/signup')
        ->and(route('login.launch', absolute: false))->toBe('/login/launch')
        ->and(route('login.invitation', absolute: false))->toBe('/login/invitation')
        ->and(route('login.error', absolute: false))->toBe('/login/error')
        ->and(route('login.signed-out', absolute: false))->toBe('/login/signed-out');
});

// --- Failures ------------------------------------------------------------------------

it('lands an error from Cbox ID on the error page, not back at Cbox ID', function (): void {
    useRedirectMode();
    $this->get('https://app.test/login')->assertRedirect();

    CboxId::fake()->failSignIn('access_denied');
    $this->get('https://app.test/login/callback?error=access_denied&state=s')->assertRedirect('https://app.test/login/error');

    $page = $this->get('https://app.test/login/error')->assertOk()
        ->assertSee('No access with this account')
        ->assertSee('access_denied')
        ->assertSee('https://app.test/login?prompt=select_account', false)
        ->assertDontSee('<form', false)
        ->assertDontSee('<script', false)
        ->assertDontSee(SIGN_IN_ISSUER, false);

    expect($page->headers->get('Cache-Control'))->toContain('no-store');
    $this->assertGuest();
});

it('shows only an error code, never what Cbox ID said about the deployment', function (): void {
    useRedirectMode();

    $failure = AuthenticationFailed::fromCallback('invalid_scope', 'this application is not registered for the requested scope(s): groups');
    expect(parse_url(CboxId::signInFailed($failure)->getTargetUrl(), PHP_URL_PATH))->toBe('/login/error');

    $this->get('https://app.test/login/error')->assertOk()->assertSee('invalid_scope')->assertDontSee('not registered');
});

it('replaces an error code that is not one', function (): void {
    useRedirectMode();

    $failure = AuthenticationFailed::fromCallback('<b>x</b>');
    CboxId::signInFailed($failure);

    $this->get('https://app.test/login/error')->assertOk()->assertSee('sign_in_failed')->assertDontSee('<b>x</b>', false);
});

it('starts over once on a stale state, and stops when sign-ins keep coming back', function (): void {
    useRedirectMode();

    expect(parse_url(CboxId::signInFailed(InvalidState::because('stale'))->getTargetUrl(), PHP_URL_PATH))->toBe('/login');

    // A callback that fails every time, with a Cbox ID session that answers silently.
    foreach ([1, 2, 3] as $ignored) {
        $this->get('https://app.test/login')->assertRedirect();
        CboxId::fake()->failSignIn('server_error');
        $this->get('https://app.test/login/callback?error=server_error&state=s')->assertRedirect('https://app.test/login/error');
        // An app that sends failures back to /login instead would be here again at once.
    }

    $this->get('https://app.test/login')->assertOk()->assertSee('Sign-in did not complete')->assertSee('sign_in_loop');

    // Showing the page resets the count: "Try again" is a real attempt.
    expect(session()->has(SignInRoundTrips::KEY))->toBeFalse();
    $this->get('https://app.test/login')->assertRedirect();
});

it('forgets round trips older than a minute', function (): void {
    useRedirectMode();
    session()->put(SignInRoundTrips::KEY, [time() - 61, time() - 90, time() - 120]);

    $this->get('https://app.test/login')->assertRedirect();
});

it('shows the error page when sign-in cannot start at all', function (): void {
    useRedirectMode();
    // A deployment with no client configured: the real client refuses to build the request.
    app()->instance(IdentityClient::class, new IdentityClient([], new Discovery(SIGN_IN_ISSUER, 60, 5)));
    CboxId::clearResolvedInstances();

    $this->get('https://app.test/login')->assertStatus(503)->assertSee('Sign-in is unavailable')->assertSee('sign_in_unavailable');
    $this->get('https://app.test/signup')->assertStatus(503);
});

it('has a signed-out page that does not sign the person back in', function (): void {
    useRedirectMode();

    $this->get('https://app.test/login/signed-out')->assertOk()
        ->assertSee('You are signed out')
        ->assertSee('https://app.test/login', false)
        ->assertDontSee('Use another account');
});

it('draws the notice with the app\'s own view when one is configured', function (): void {
    useRedirectMode(['cbox-id-client.sign_in.view' => 'custom-notice']);
    app('view')->addLocation(__DIR__.'/Fixtures/views');

    $this->get('https://app.test/login/signed-out')->assertOk()->assertSee('custom:signed_out:Brain');
});

// --- The callback --------------------------------------------------------------------

it('does one code exchange on the callback and nothing else at the token endpoint', function (): void {
    config([
        'cbox-id-client.issuer' => SIGN_IN_ISSUER,
        'cbox-id-client.client_id' => 'brain-web',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/login/callback',
        'cbox-id-client.scopes' => ['profile'],
        'cbox-id-client.sign_in.mode' => 'redirect',
        'session.driver' => 'array',
    ]);
    app()->forgetInstance(IdentityClient::class);
    CboxId::clearResolvedInstances();
    Http::preventStrayRequests();
    Http::fake([
        SIGN_IN_ISSUER.'/.well-known/openid-configuration' => Http::response([
            'issuer' => SIGN_IN_ISSUER,
            'authorization_endpoint' => SIGN_IN_ISSUER.'/oauth/authorize',
            'token_endpoint' => SIGN_IN_ISSUER.'/oauth/token',
            'userinfo_endpoint' => SIGN_IN_ISSUER.'/oauth/userinfo',
            'jwks_uri' => SIGN_IN_ISSUER.'/.well-known/jwks.json',
        ]),
        SIGN_IN_ISSUER.'/oauth/token' => Http::response(['access_token' => 'at_1', 'token_type' => 'Bearer', 'expires_in' => 300]),
        SIGN_IN_ISSUER.'/oauth/userinfo' => Http::response(['sub' => 'usr_1', 'org' => 'org_1']),
    ]);

    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.verifier' => 'ver_1', 'cbox-id-client.nonce' => 'n_1', 'cbox-id-client.scopes' => ['profile']]);
    $user = app(IdentityClient::class)->authenticate(Request::create('https://app.test/login/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']));

    $token = Http::recorded(fn ($request): bool => $request->url() === SIGN_IN_ISSUER.'/oauth/token')->values();

    expect($user->id)->toBe('usr_1')
        ->and($token)->toHaveCount(1)
        ->and($token[0][0]['grant_type'])->toBe('authorization_code')
        ->and($token[0][0]['code_verifier'])->toBe('ver_1')
        ->and(session(SignInRoundTrips::KEY))->toHaveCount(1);
});
