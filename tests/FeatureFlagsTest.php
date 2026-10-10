<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\HasFeatureFlags;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * The `feature_flags` claim: on the user, through `feature:` / `cbox-id.feature:` routes,
 * and in `@feature` Blade blocks.
 */
beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);

    Route::middleware(['web', 'feature:new-dashboard'])->get('/dashboard', fn () => ['ok' => true]);
    Route::middleware(['web', 'cbox-id.feature:billing.v2,acme-beta'])->get('/billing', fn () => ['ok' => true]);
});

function featureUser(array $claims): CboxUser
{
    return new CboxUser('user-1', null, null, null, ['sub' => 'user-1'] + $claims, 'at', null, null, 300);
}

function sessionWithFlags(?array $flags): void
{
    app(SessionIdentityStore::class)->remember(new Identity('user-1', $flags === null ? [] : ['feature_flags' => $flags]));
    Auth::login(LocalUser::withId(1));
}

it('reads the claim on the signed-in user and answers hasFeature', function (): void {
    $user = featureUser(['feature_flags' => ['acme-beta', 'new-dashboard']]);

    expect($user)->toBeInstanceOf(HasFeatureFlags::class)
        ->and($user->featureFlags())->toBe(['acme-beta', 'new-dashboard'])
        ->and($user->hasFeature('new-dashboard'))->toBeTrue()
        ->and($user->hasFeature('old-reports'))->toBeFalse()
        ->and($user->hasFeature(''))->toBeFalse();
});

it('tells "not requested" from "nothing on", and turns everything off for either', function (): void {
    expect(featureUser([])->featureFlags())->toBeNull()
        ->and(featureUser(['feature_flags' => []])->featureFlags())->toBe([])
        ->and(featureUser([])->hasFeature('x'))->toBeFalse();
});

it('treats a malformed claim as absent — never splits a string into flags', function (): void {
    $user = featureUser(['feature_flags' => 'new-dashboard acme-beta']);

    expect($user->featureFlags())->toBeNull()
        ->and($user->hasFeature('new-dashboard'))->toBeFalse()
        ->and(featureUser(['feature_flags' => ['billing.v2', 7, '']])->featureFlags())->toBe(['billing.v2']);
});

it('remembers the flags in the session identity, so they answer on the next request', function (): void {
    $identity = Identity::fromPrincipal(featureUser(['feature_flags' => ['acme-beta']]));

    expect($identity->hasFeature('acme-beta'))->toBeTrue()
        ->and(HasFeatureFlags::SCOPE)->toBe('feature_flags');
});

it('opens a feature: route only when the flag is on', function (): void {
    sessionWithFlags(['new-dashboard']);
    $this->getJson('/dashboard')->assertOk();

    sessionWithFlags(['other']);
    $this->getJson('/dashboard')->assertNotFound()->assertJsonPath('error.type', 'not_found');
    $this->get('/dashboard')->assertNotFound();
});

it('closes the route when the scope was never requested', function (): void {
    sessionWithFlags(null);

    $this->getJson('/dashboard')->assertNotFound();
});

it('requires every flag cbox-id.feature names', function (): void {
    sessionWithFlags(['billing.v2']);
    $this->getJson('/billing')->assertNotFound();

    sessionWithFlags(['billing.v2', 'acme-beta']);
    $this->getJson('/billing')->assertOk();
});

it('asks a guest to sign in first', function (): void {
    $this->getJson('/dashboard')->assertUnauthorized();
});

it('refuses a route that names no flag', function (): void {
    Route::middleware(['web', 'cbox-id.feature'])->get('/nothing', fn () => ['ok' => true]);
    sessionWithFlags(['new-dashboard']);

    $this->withoutExceptionHandling();
    $this->getJson('/nothing');
})->throws(ClientConfigurationException::class);

it('renders @feature blocks for the current principal', function (): void {
    CboxId::fake()->actingAs('user-1', featureFlags: ['new-dashboard']);

    $view = "@feature('new-dashboard')[on]@else[off]@endfeature @feature('old-reports')[on]@else[off]@endfeature";

    expect(preg_replace('/\s+/', '', Blade::render($view)))->toBe('[on][off]');
});

it('lets a test act as somebody with flags, and signs in with them', function (): void {
    $fake = CboxId::fake();

    expect($fake->actingAs(featureFlags: ['acme-beta']))->toBeInstanceOf(HasFeatureFlags::class);
    $this->getJson('/dashboard')->assertNotFound();

    $fake->actingAs(featureFlags: ['new-dashboard']);
    $this->getJson('/dashboard')->assertOk();

    expect($fake->signIn(featureFlags: ['x'])->hasFeature('x'))->toBeTrue();
});
