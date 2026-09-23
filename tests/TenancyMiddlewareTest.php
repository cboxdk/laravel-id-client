<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Cbox\Id\Client\ValueObjects\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * `cbox-id.org`, `cbox-id.permission` and `cbox-id.configured`, through real routes.
 */
beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    fakeCbox();

    Route::middleware(['web', 'cbox-id.org'])->get('/team', fn (Organization $org) => ['org' => $org->id]);
    Route::middleware(['web', 'cbox-id.org:admin'])->get('/team/settings', fn () => ['ok' => true]);
    Route::middleware(['web', 'cbox-id.permission:invoices:create'])->post('/invoices', fn () => ['ok' => true]);
    Route::middleware(['web', 'cbox-id.permission:invoices:read,invoices:export'])->get('/invoices/export', fn () => ['ok' => true]);
});

function sessionAs(array $claims): void
{
    app(SessionIdentityStore::class)->remember(new Identity('user-1', $claims));
    Auth::login(LocalUser::withId(1));
}

it('lets a member of an organization through and hands the handler the organization', function (): void {
    sessionAs(['org' => 'org_1', 'org_role' => 'member']);

    $this->getJson('/team')->assertOk()->assertJsonPath('org', 'org_1');
});

it('refuses an API caller with no organization with a 403 reason', function (): void {
    sessionAs([]);

    $this->getJson('/team')->assertForbidden()->assertJsonPath('error.type', 'organization_required');
});

it('sends a browser with no organization to the hosted picker and back', function (): void {
    sessionAs([]);

    $response = $this->get('/team');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://id.test/oauth/authorize?')
        ->and($response->headers->get('Location'))->toContain('prompt=select_organization')
        ->and(session('url.intended'))->toBe('http://localhost/team');
});

it('answers a plain 403 instead of the picker when the picker is off', function (): void {
    config(['cbox-id-client.organizations.picker' => false]);
    sessionAs([]);

    $this->get('/team')->assertForbidden();
});

it('requires the minimum organization tier', function (): void {
    sessionAs(['org' => 'org_1', 'org_role' => 'member']);
    $this->getJson('/team/settings')->assertForbidden()->assertJsonPath('error.type', 'insufficient_organization_role');

    sessionAs(['org' => 'org_1', 'org_role' => 'owner']);
    $this->getJson('/team/settings')->assertOk();
});

it('treats an unstated tier as not enough', function (): void {
    sessionAs(['org' => 'org_1']);

    $this->getJson('/team/settings')->assertForbidden();
});

it('refuses a misspelt tier loudly instead of letting everyone through', function (): void {
    Route::middleware(['web', 'cbox-id.org:admn'])->get('/typo', fn () => ['ok' => true]);
    sessionAs(['org' => 'org_1', 'org_role' => 'owner']);

    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/typo'))->toThrow(ClientConfigurationException::class, 'names no organization role');
});

it('asks who you are before it asks for an organization', function (): void {
    $this->getJson('/team')->assertUnauthorized()->assertJsonPath('error.type', 'unauthenticated');
});

it('requires every permission it names', function (): void {
    sessionAs(['org' => 'org_1', 'permissions' => ['invoices:create', 'invoices:read']]);

    $this->postJson('/invoices')->assertOk();
    $this->getJson('/invoices/export')
        ->assertForbidden()
        ->assertJsonPath('error.type', 'insufficient_permission')
        ->assertJsonPath('error.required', ['invoices:read', 'invoices:export']);
});

it('refuses a permission middleware that names nothing', function (): void {
    Route::middleware(['web', 'cbox-id.permission'])->get('/open', fn () => ['ok' => true]);
    sessionAs(['permissions' => ['anything:at-all']]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->getJson('/open'))->toThrow(ClientConfigurationException::class, 'names no permission');
});

it('refuses legibly when the deployment is not configured', function (): void {
    Route::middleware('cbox-id.configured:issuer,client_id')->get('/login', fn () => CboxId::redirect());

    config(['cbox-id-client.client_id' => '']);
    $this->getJson('/login')->assertStatus(503)->assertJsonPath('error.type', 'service_misconfigured');
    $this->get('/login')->assertStatus(503);

    config(['cbox-id-client.client_id' => 'client_1']);
    $this->get('/login')->assertRedirect();
});

it('never echoes a configured value in the refusal', function (): void {
    Route::middleware('cbox-id.configured:issuer,client_secret')->get('/login', fn () => 'ok');
    config(['cbox-id-client.client_secret' => '']);

    $body = $this->getJson('/login')->assertStatus(503)->getContent();

    expect($body)->not->toContain('https://id.test');
});
