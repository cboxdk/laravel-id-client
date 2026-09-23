<?php

declare(strict_types=1);

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\ResourceNotFound;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Facades\CboxIdManagement;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Management\Data\NewInvitation;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\Tenancy\PermissionGate;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * `CboxId::fake()` — what an application's own test suite uses instead of standing up a
 * fake issuer. Every scenario here is one the two first consuming apps wrote by hand.
 */
it('needs no issuer, no HTTP and no configuration', function (): void {
    config(['cbox-id-client.issuer' => null, 'cbox-id-client.client_id' => null]);
    Http::preventStrayRequests();

    $cbox = CboxId::fake();
    $url = CboxId::redirect(organization: 'org_1')->getTargetUrl();

    expect($url)->toStartWith('https://id.test/oauth/authorize?')->and($url)->toContain('organization=org_1');
    expect($cbox->client())->toBe(app(IdentityClient::class));
});

it('acts as a user in an organization with permissions, for the gate and the middleware', function (): void {
    PermissionGate::register(app(GateContract::class), app(CurrentPrincipal::class));
    Route::middleware(['cbox-id.org:admin', 'cbox-id.permission:invoices:create'])->post('/invoices', fn () => ['org' => CboxId::currentOrganization()?->id]);

    CboxId::fake()->actingAs('user_1', organization: 'org_1', role: OrganizationRole::Admin, permissions: ['invoices:create']);
    $this->actingAs(LocalUser::withId(1));

    $this->postJson('/invoices')->assertOk()->assertJsonPath('org', 'org_1');
    expect(LocalUser::withId(1)->can('invoices:create'))->toBeTrue()
        ->and(CboxId::principal()?->subjectId())->toBe('user_1');
});

it('acts as a member without the permission, and the route refuses', function (): void {
    Route::middleware(['cbox-id.permission:invoices:create'])->post('/invoices', fn () => ['ok' => true]);

    CboxId::fake()->actingAs('user_1', permissions: ['invoices:read']);

    $this->postJson('/invoices')->assertForbidden();
});

it('acts as a support session', function (): void {
    CboxId::fake()->actingAs('user_1', actor: 'staff_9');

    expect(CboxId::principal()?->isSupportSession())->toBeTrue()
        ->and(CboxId::principal()?->actor()?->subject)->toBe('staff_9');
});

it('mints real tokens that the production verifier accepts', function (): void {
    Route::middleware(['cbox-id.token:api.read', 'cbox-id.permission:reports:read'])->get('/api/reports', fn (VerifiedToken $t) => [
        'sub' => $t->subject,
        'org' => $t->organization()?->id,
        'role' => $t->organization()?->role?->value,
    ]);

    $cbox = CboxId::fake();
    $token = $cbox->token()->for('user_7')->organization('org_7', 'Acme', OrganizationRole::Owner)->permissions(['reports:read'])->scopes(['api.read']);

    $this->withToken((string) $token)->getJson('/api/reports')
        ->assertOk()->assertExactJson(['sub' => 'user_7', 'org' => 'org_7', 'role' => 'owner']);

    // Verified for real: a forged, an expired and an under-scoped token are all refused.
    $this->withToken($token->mint().'x')->getJson('/api/reports')->assertUnauthorized();
    $this->withToken($token->expired()->mint())->getJson('/api/reports')->assertUnauthorized();
    $this->withToken($token->scopes([])->mint())->getJson('/api/reports')->assertForbidden();
    $this->withToken($token->audience('https://elsewhere.test')->mint())->getJson('/api/reports')->assertUnauthorized();
});

it('runs the real callback route with a queued sign-in, and remembers it', function (): void {
    Route::middleware('web')->get('/auth/callback', function (Request $request) {
        $cbox = CboxId::authenticate($request);
        Auth::login(LocalUser::withId(1));

        return ['sub' => $cbox->id, 'org' => $cbox->organization()?->id];
    });
    Route::middleware(['web', 'cbox-id.org'])->get('/team', fn () => ['org' => CboxId::currentOrganization()?->id]);

    CboxId::fake()->signIn('user_1', organization: 'org_1', role: OrganizationRole::Owner, permissions: ['team:manage']);

    $this->get('/auth/callback?code=x&state=y')->assertOk()->assertJson(['sub' => 'user_1', 'org' => 'org_1']);
    $this->getJson('/team')->assertOk()->assertJsonPath('org', 'org_1');
});

it('fails a sign-in the way Cbox ID would', function (): void {
    CboxId::fake()->failSignIn('access_denied', 'Not a member.');

    try {
        CboxId::authenticate(Request::create('/cb'));
        $this->fail('Expected a failure.');
    } catch (AuthenticationFailed $e) {
        expect($e->isAccessDenied())->toBeTrue()->and($e->errorDescription)->toBe('Not a member.');
    }
});

it('records provisioning for assertions, and keeps enough state to read back', function (): void {
    $cbox = CboxId::fake();

    $org = CboxIdManagement::createOrganization(new NewOrganization('Acme', ownerUserId: 'user_1'));
    CboxIdManagement::invite($org->id, new NewInvitation('ada@example.test', roles: ['editor']));
    CboxIdManagement::assignRole($org->id, 'user_1', 'role_editor');

    $cbox->management()->assertOrganizationCreated(fn (NewOrganization $o): bool => $o->name === 'Acme' && $o->ownerUserId === 'user_1');
    $cbox->management()->assertInvited('ADA@example.test', $org->id);
    $cbox->management()->assertRoleAssigned($org->id, 'user_1', 'role_editor');
    $cbox->management()->assertNotCalled('removeMember');

    expect(CboxIdManagement::members($org->id)->items[0]->role)->toBe(OrganizationRole::Owner)
        ->and(fn () => CboxIdManagement::organization('org_nope'))->toThrow(ResourceNotFound::class);
});

it('fails an assertion that did not happen', function (): void {
    $cbox = CboxId::fake();

    expect(fn () => $cbox->management()->assertInvited('nobody@example.test'))->toThrow(ExpectationFailedException::class);
});

it('lets a test make the management API fail', function (): void {
    $cbox = CboxId::fake();
    $cbox->management()->failNext('invite', new ResourceNotFound('Organization not found.'));

    expect(fn () => CboxIdManagement::invite('org_1', new NewInvitation('a@b.test')))->toThrow(ResourceNotFound::class);
    $cbox->management()->assertCalled('invite', times: 1);
});

it('serves customer API keys from memory', function (): void {
    Route::middleware('cbox-id.api-key:reports:read')->get('/v1/reports', fn () => ['ok' => true]);

    $cbox = CboxId::fake();
    $cbox->apiKeys()->add('ctx_live_abc', 'user_1', 'org_1', permissions: ['reports:read']);

    $this->withToken('ctx_live_abc')->getJson('/v1/reports')->assertOk();
    $this->withToken('ctx_live_zzz')->getJson('/v1/reports')->assertUnauthorized();
    $cbox->apiKeys()->assertVerified('ctx_live_abc');

    $cbox->apiKeys()->unavailable();
    $this->withToken('ctx_live_abc')->getJson('/v1/reports')->assertStatus(503);
});

it('mints machine tokens and records revocations', function (): void {
    $cbox = CboxId::fake();

    $token = CboxId::machineToken(['api.read']);
    CboxId::revoke('rt_1', 'refresh_token');

    expect(explode('.', $token))->toHaveCount(3);
    $cbox->client()->assertRevoked('rt_1');
});
