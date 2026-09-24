<?php

declare(strict_types=1);

use Cbox\Id\Client\BackchannelLogout\LogoutTokenVerifier;
use Cbox\Id\Client\BackchannelLogout\SessionRegistry;
use Cbox\Id\Client\ClientServiceProvider;
use Cbox\Id\Client\Events\BackchannelLogoutReceived;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/**
 * OIDC Back-Channel Logout 1.0: Cbox ID POSTs a signed logout token, the SDK validates it
 * strictly (§2.6) and ends the matching local sessions.
 *
 * Tokens are minted the way laravel-id's JwtLogoutTokenIssuer mints them, and signed with
 * the same test key the instance's JWKS serves.
 */
const LOGOUT_EVENT = 'http://schemas.openid.net/event/backchannel-logout';

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
        'cbox-id-client.backchannel_logout.enabled' => true,
    ]);
    Cache::flush();
    // One stub set per test (Http::fake keeps the FIRST match), with a slot for the
    // id_token a test wants the token endpoint to return.
    fakeCbox(fn () => test()->idTokenForLogin ?? null);

    // The route and the web-group middleware are registered at boot, when enabled.
    (new ClientServiceProvider($this->app))->boot();
});

/**
 * @param  array<string, mixed>  $overrides  null removes a claim
 * @param  array<string, mixed>  $header
 */
function logoutToken(array $overrides = [], array $header = ['typ' => 'logout+jwt'], string $kid = 'test-1'): string
{
    $claims = array_replace([
        'iss' => 'https://id.test',
        'aud' => 'client_1',
        'iat' => time(),
        'exp' => time() + 120,
        'jti' => bin2hex(random_bytes(8)),
        'events' => [LOGOUT_EVENT => (object) []],
        'sub' => 'user-1',
        'sid' => 'sid-A',
    ], $overrides);

    return JWT::encode(array_filter($claims, static fn ($v) => $v !== null), rsaKeypair($kid)['private'], 'RS256', 'test-1', $header);
}

function postLogout(?string $token): TestResponse
{
    return test()->post('/cbox-id/backchannel-logout', $token === null ? [] : ['logout_token' => $token]);
}

dataset('refusals', [
    'no token' => [fn () => null, 'No logout_token was sent.'],
    'not a JWT' => [fn () => 'not-a-jwt', 'is not a JWT'],
    'signed by another key' => [fn () => logoutToken(kid: 'intruder'), 'could not be verified'],
    'HMAC-signed with the public modulus' => [fn () => JWT::encode(['iss' => 'https://id.test'], str_repeat('s', 64), 'HS256', 'test-1', ['typ' => 'logout+jwt']), 'could not be verified'],
    'expired' => [fn () => logoutToken(['iat' => time() - 200, 'exp' => time() - 10]), 'could not be verified'],
    'issued in the future' => [fn () => logoutToken(['iat' => time() + 600, 'exp' => time() + 700]), 'could not be verified'],
    'typed as something else' => [fn () => logoutToken([], ['typ' => 'JWT']), 'not typed logout+jwt'],
    'another issuer' => [fn () => logoutToken(['iss' => 'https://evil.test']), 'issuer did not match'],
    'another audience' => [fn () => logoutToken(['aud' => 'client_2']), 'audience is not this client'],
    'no iat' => [fn () => logoutToken(['iat' => null]), 'carries no iat'],
    'too old' => [fn () => logoutToken(['iat' => time() - 1000, 'exp' => time() + 60]), 'issued too long ago'],
    'no exp' => [fn () => logoutToken(['exp' => null]), 'carries no exp'],
    'no jti' => [fn () => logoutToken(['jti' => null]), 'carries no jti'],
    'no events' => [fn () => logoutToken(['events' => null]), 'no back-channel logout event'],
    'another event' => [fn () => logoutToken(['events' => ['https://example.test/other' => (object) []]]), 'no back-channel logout event'],
    'event not an object' => [fn () => logoutToken(['events' => [LOGOUT_EVENT => true]]), 'is not a JSON object'],
    'a nonce (an ID Token)' => [fn () => logoutToken(['nonce' => 'n-1']), 'carries a nonce'],
    'neither sub nor sid' => [fn () => logoutToken(['sub' => null, 'sid' => null]), 'neither a sub nor a sid'],
    'sid not a string' => [fn () => logoutToken(['sid' => ['sid-A']]), 'sub or sid is not a string'],
]);

it('refuses a token that fails a check, and says which', function (Closure $token, string $reason): void {
    postLogout($token())
        ->assertStatus(400)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('error', 'invalid_request')
        ->assertJson(fn ($json) => $json->where('error_description', fn (string $d): bool => str_contains($d, $reason))->etc());
})->with('refusals');

it('accepts a valid token with 200 and no-store', function (): void {
    postLogout(logoutToken())->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

it('mounts the receiver outside the web group, so no session and no CSRF check apply', function (): void {
    // Laravel skips CSRF verification while running tests, so a request cannot prove
    // this; the route's middleware can. Cbox ID's server has no session and no token.
    app('router')->getRoutes()->refreshNameLookups();
    $route = app('router')->getRoutes()->getByName('cbox-id.backchannel-logout');

    expect($route?->methods())->toBe(['POST'])
        ->and($route?->gatherMiddleware())->not->toContain('web');
});

it('accepts an audience list that includes this client, and a token with only sub or only sid', function (): void {
    postLogout(logoutToken(['aud' => ['client_0', 'client_1']]))->assertOk();
    postLogout(logoutToken(['sid' => null]))->assertOk();
    postLogout(logoutToken(['sub' => null]))->assertOk();
});

it('refuses a replayed token', function (): void {
    $token = logoutToken();

    postLogout($token)->assertOk();
    postLogout($token)->assertStatus(400)->assertJsonPath('error_description', 'The token has already been used.');
});

it('does not use up a jti on a token it refused for another reason', function (): void {
    postLogout(logoutToken(['jti' => 'jti-1', 'nonce' => 'x']))->assertStatus(400);

    postLogout(logoutToken(['jti' => 'jti-1']))->assertOk();
});

it('answers 503 so Cbox ID retries when the signing keys cannot be read', function (): void {
    $this->app->instance(LogoutTokenVerifier::class, new LogoutTokenVerifier(
        new class('https://id.test', 0, 1) extends Discovery
        {
            public function jwks(): array
            {
                throw ClientConfigurationException::because('Could not load the Cbox ID signing keys (JWKS).');
            }
        },
        'https://id.test',
        'client_1',
        Cache::store(),
    ));

    postLogout(logoutToken())->assertStatus(503);
});

it('is not mounted unless enabled', function (): void {
    $this->refreshApplication();

    $this->post('/cbox-id/backchannel-logout', ['logout_token' => 'x'])->assertNotFound();
});

it('remembers the sid of the ID Token it signed somebody in with', function (): void {
    $this->idTokenForLogin = idToken([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1', 'nonce' => 'nonce_1',
        'sid' => 'sid-A', 'iat' => time(), 'exp' => time() + 900,
    ]);

    app(IdentityClient::class)->authenticate(loginCallback());

    expect(app(SessionIdentityStore::class)->identity()?->claims['sid'] ?? null)->toBe('sid-A');
});

describe('ending local sessions', function (): void {
    beforeEach(function (): void {
        config([
            'session.driver' => 'database',
            'auth.providers.users.model' => LocalUser::class,
        ]);

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('remember_token', 100)->nullable();
        });
        DB::table('users')->insert([['id' => 1, 'remember_token' => 'rt-1'], ['id' => 2, 'remember_token' => 'rt-2']]);

        // A sign-in the way an app does it: the SDK remembers, the app logs in and
        // regenerates the session id — which is why the index is written on the way OUT.
        Route::middleware('web')->get('/signin/{user}/{sid}', function (int $user, string $sid) {
            CboxId::rememberIdentity(new Identity('user-'.$user, ['sid' => $sid]));
            Auth::login(LocalUser::withId($user));
            session()->regenerate();

            return 'ok';
        });
        Route::middleware('web')->get('/me', fn () => ['user' => Auth::id(), 'principal' => CboxId::principal()?->subjectId()]);
    });

    /**
     * A new request, as a new PHP process would see it. In a test the session store and
     * the auth guard are the same objects from one request to the next, and both keep
     * what they loaded — which would let a destroyed session live on in memory.
     */
    function freshProcess(): void
    {
        app('auth')->forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }

    /** Sign in from a fresh "browser" and return its session cookie value. */
    function browserSignsIn(int $user, string $sid): string
    {
        freshProcess();
        // A new browser: no session cookie of its own.
        $cookie = test()->withCookie(config('session.cookie'), '')->get("/signin/{$user}/{$sid}")->assertOk()->getCookie(config('session.cookie'))?->getValue();
        expect($cookie)->toBeString();

        return (string) $cookie;
    }

    function asBrowser(string $cookie): TestResponse
    {
        freshProcess();

        return test()->withCookie(config('session.cookie'), $cookie)->withCredentials()->getJson('/me');
    }

    it('destroys the one session a sid names, in the session store, and leaves the rest', function (): void {
        $a = browserSignsIn(1, 'sid-A');
        $b = browserSignsIn(1, 'sid-B');

        asBrowser($a)->assertJson(['user' => 1, 'principal' => 'user-1']);
        expect(DB::table('sessions')->count())->toBe(2);

        postLogout(logoutToken(['sid' => 'sid-A']))->assertOk();

        expect(DB::table('sessions')->where('id', $a)->exists())->toBeFalse()
            ->and(DB::table('sessions')->where('id', $b)->exists())->toBeTrue();
        asBrowser($a)->assertJson(['user' => null, 'principal' => null]);
        asBrowser($b)->assertJson(['user' => 1, 'principal' => 'user-1']);
    });

    it('ends every session of a subject that signed in before the token, and none after', function (): void {
        $a = browserSignsIn(1, 'sid-A');
        $b = browserSignsIn(1, 'sid-B');
        $other = browserSignsIn(2, 'sid-C');
        $issuedAt = time();

        // Signs straight back in, before the (late) notice arrives.
        $this->travel(30)->seconds();
        $after = browserSignsIn(1, 'sid-D');
        foreach ([$a, $b, $other, $after] as $cookie) {
            asBrowser($cookie)->assertJsonMissing(['user' => null]);
        }

        postLogout(logoutToken(['sid' => null, 'iat' => $issuedAt]))->assertOk();

        asBrowser($a)->assertJson(['user' => null]);
        asBrowser($b)->assertJson(['user' => null]);
        asBrowser($other)->assertJson(['user' => 2]);
        asBrowser($after)->assertJson(['user' => 1, 'principal' => 'user-1']);

        // …and the new session stays indexed, so the NEXT sign-out-everywhere reaches it.
        $indexed = array_column(Cache::get('cbox-id-client:bcl:sub:'.hash('sha256', 'user-1')) ?? [], 'session');
        expect($indexed)->toBe([$after]);
    });

    it('signs a session out on its next request even when it could not be destroyed', function (): void {
        config(['cbox-id-client.backchannel_logout.destroy_sessions' => false]);
        $this->app->forgetInstance(SessionRegistry::class);
        $a = browserSignsIn(1, 'sid-A');
        asBrowser($a)->assertJson(['user' => 1, 'principal' => 'user-1']);

        postLogout(logoutToken(['sid' => 'sid-A']))->assertOk();

        expect(DB::table('sessions')->where('id', $a)->exists())->toBeTrue();
        asBrowser($a)->assertJson(['user' => null, 'principal' => null]);
    });

    it('cycles remember-me tokens when a person is signed out everywhere, not for one session', function (): void {
        browserSignsIn(1, 'sid-A');
        postLogout(logoutToken(['sid' => 'sid-A']))->assertOk();
        expect(DB::table('users')->where('id', 1)->value('remember_token'))->toBe('rt-1');

        browserSignsIn(1, 'sid-B');
        postLogout(logoutToken(['sid' => null]))->assertOk();
        expect(DB::table('users')->where('id', 1)->value('remember_token'))->not->toBe('rt-1')
            ->and(DB::table('users')->where('id', 2)->value('remember_token'))->toBe('rt-2');
    });

    it('tells the application what it ended', function (): void {
        Event::fake([BackchannelLogoutReceived::class]);
        $a = browserSignsIn(1, 'sid-A');

        postLogout(logoutToken(['sid' => 'sid-A', 'sub' => 'user-1']))->assertOk();

        Event::assertDispatched(BackchannelLogoutReceived::class, fn (BackchannelLogoutReceived $e): bool => $e->token->sid === 'sid-A'
            && $e->token->subject === 'user-1'
            && $e->ended->sessionIds === [$a]
            && $e->ended->localUserIds === ['1']);
    });
});
