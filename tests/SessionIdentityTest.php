<?php

declare(strict_types=1);

use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Support\Facades\Auth;

/**
 * The identity remembered at sign-in belongs to exactly one local user.
 *
 * The failure this guards against is quiet: an application that logs a DIFFERENT person
 * into the same session — an impersonation feature, a second login form — would
 * otherwise inherit the first person's Cbox ID organization and permissions.
 */
function remembered(string $subject = 'user-1', array $claims = ['permissions' => ['invoices:create']]): SessionIdentityStore
{
    $store = app(SessionIdentityStore::class);
    $store->remember(new Identity($subject, $claims));

    return $store;
}

it('binds the remembered identity to the user the application logs in', function (): void {
    $store = remembered();
    $ada = LocalUser::withId(1);

    Auth::login($ada);

    expect($store->current($ada)?->subject)->toBe('user-1')
        // No longer a guest's to use.
        ->and($store->current(null))->toBeNull();
});

it('never answers for a different local user', function (): void {
    $store = remembered();
    Auth::login(LocalUser::withId(1));

    expect($store->current(LocalUser::withId(2)))->toBeNull();
});

it('forgets it outright when somebody else logs in to the same session', function (): void {
    $store = remembered();
    Auth::login(LocalUser::withId(1));

    Auth::login(LocalUser::withId(2));

    expect($store->identity())->toBeNull();
});

it('forgets it on logout', function (): void {
    $store = remembered();
    Auth::login(LocalUser::withId(1));

    Auth::logout();

    expect($store->identity())->toBeNull();
});

it('keeps the binding when the same person signs in again, as an organization switch does', function (): void {
    $store = remembered('user-1', ['org' => 'org_1']);
    $ada = LocalUser::withId(1);
    Auth::login($ada);

    // The callback of a switch: the same subject, a new organization, and an
    // application that does not bother logging the person in a second time.
    $store->remember(new Identity('user-1', ['org' => 'org_2']));

    expect($store->current($ada)?->organization()?->id)->toBe('org_2');
});

it('drops the binding when a different person signs in through Cbox ID', function (): void {
    $store = remembered('user-1');
    $ada = LocalUser::withId(1);
    Auth::login($ada);

    $store->remember(new Identity('user-2'));

    expect($store->current($ada))->toBeNull();
});

it('serves an application that uses Cbox ID as its only login, as a guest', function (): void {
    $store = remembered();

    expect($store->current(null)?->subject)->toBe('user-1')
        // But a local user who logged in some other way does not get it.
        ->and($store->current(LocalUser::withId(1)))->toBeNull();
});
