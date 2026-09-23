<?php

declare(strict_types=1);

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\ClientServiceProvider;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\Tenancy\PermissionGate;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Laravel's own authorization answering Cbox ID permissions: `@can`, `$user->can()`,
 * `Gate::authorize()` — with no policy per permission, and without ever denying what the
 * application's own gates would allow.
 */
function registerGate(): void
{
    PermissionGate::register(app(GateContract::class), app(CurrentPrincipal::class));
}

function signedInWith(array $permissions, int $localId = 1): LocalUser
{
    app(SessionIdentityStore::class)->remember(new Identity('user-1', ['org' => 'org_1', 'permissions' => $permissions]));
    $user = LocalUser::withId($localId);
    Auth::login($user);

    return $user;
}

it('grants a held permission through $user->can() and @can', function (): void {
    registerGate();
    $user = signedInWith(['invoices:create']);

    expect($user->can('invoices:create'))->toBeTrue()
        ->and($user->can('invoices:delete'))->toBeFalse()
        ->and(trim(Blade::render("@can('invoices:create') yes @else no @endcan")))->toBe('yes')
        ->and(trim(Blade::render("@can('invoices:delete') yes @else no @endcan")))->toBe('no');
});

it('only ever grants: an app gate still decides what the permission does not cover', function (): void {
    registerGate();
    Gate::define('invoices:export', fn (): bool => true);
    Gate::define('update', fn (): bool => false);
    $user = signedInWith(['update']);

    expect($user->can('invoices:export'))->toBeTrue()   // not held, the app's gate says yes
        ->and($user->can('update'))->toBeFalse();        // not permission-shaped: never touched
});

it('is off until the application asks for it', function (): void {
    $user = signedInWith(['invoices:create']);

    expect($user->can('invoices:create'))->toBeFalse();

    config(['cbox-id-client.authorization.gate' => true]);
    (new ClientServiceProvider($this->app))->boot();

    expect($user->can('invoices:create'))->toBeTrue();
});

it('does not answer for another local user with the session holder permissions', function (): void {
    registerGate();
    signedInWith(['invoices:create']);

    expect(Gate::forUser(LocalUser::withId(2))->allows('invoices:create'))->toBeFalse();
});

it('grants from a verified bearer token on an API route', function (): void {
    registerGate();
    fakeCbox();
    $this->app->instance(AccessTokenVerifier::class, new AccessTokenVerifier(new Discovery('https://id.test', 3600, 10), 'https://id.test', 'https://api.cboxtax.com'));

    Route::middleware('cbox-id.token')->get('/api/invoices', function () {
        Gate::authorize('invoices:read');

        return ['ok' => true];
    });

    $this->getJson('/api/invoices', ['Authorization' => 'Bearer '.accessToken(['permissions' => ['invoices:read']])])->assertOk();
    $this->getJson('/api/invoices', ['Authorization' => 'Bearer '.accessToken(['permissions' => []])])->assertForbidden();
});

it('does not lend a bearer token\'s permissions to a check about another user', function (): void {
    registerGate();
    fakeCbox();
    $this->app->instance(AccessTokenVerifier::class, new AccessTokenVerifier(new Discovery('https://id.test', 3600, 10), 'https://id.test', 'https://api.cboxtax.com'));

    Route::middleware('cbox-id.token')->get('/api/check', fn () => [
        'caller' => Gate::allows('invoices:read'),
        'someone_else' => Gate::forUser(LocalUser::withId(2))->allows('invoices:read'),
    ]);

    $this->getJson('/api/check', ['Authorization' => 'Bearer '.accessToken(['permissions' => ['invoices:read']])])
        ->assertExactJson(['caller' => true, 'someone_else' => false]);
});

it('recognises the manifest permission shape and nothing else', function (): void {
    expect(PermissionGate::isPermission('invoices:create'))->toBeTrue()
        ->and(PermissionGate::isPermission('tax.quote:read'))->toBeTrue()
        ->and(PermissionGate::isPermission('update'))->toBeFalse()
        ->and(PermissionGate::isPermission('a:b:c'))->toBeFalse()
        ->and(PermissionGate::isPermission(':create'))->toBeFalse();
});
