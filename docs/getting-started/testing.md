---
title: Testing
description: CboxId::fake() — act as a user in an organization with permissions, sign in through your real callback, call your API with real signed tokens, and assert provisioning, with no fake issuer.
weight: 3
---

# Testing

```php
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Enums\OrganizationRole;

$cbox = CboxId::fake();
```

That one call replaces Cbox ID for the rest of the test: no issuer, no HTTP, no
configuration needed (empty settings are filled with test values; nothing you set is
overwritten). This package's own suite uses the same fakes.

## Act as somebody

```php
$cbox->actingAs('user_1', organization: 'org_1', role: OrganizationRole::Admin, permissions: ['invoices:create']);

$this->actingAs($localUser)->post('/invoices')->assertCreated();
```

The permission gate, `cbox-id.org`, `cbox-id.permission` and `CboxId::principal()` all
see this principal. Pass `actor: 'staff_1'` for a support session, `organization: null`
for somebody who has not chosen a team. `actingAsGuest()` undoes it.

## Sign in through your real callback

```php
$cbox->signIn('user_1', organization: 'org_1', role: OrganizationRole::Owner, email: 'ada@example.test');

$this->get('/auth/callback?code=x&state=y')->assertRedirect('/dashboard');
```

`authenticate()` returns that person and remembers them exactly as it does in
production. `failSignIn('access_denied', 'Not a member.')` makes it fail instead.

## Call your API with a real token

```php
$token = $cbox->token()
    ->for('user_1')
    ->organization('org_1', 'Acme', OrganizationRole::Member)
    ->permissions(['reports:read'])
    ->scopes(['api.read']);

$this->withToken((string) $token)->getJson('/api/reports')->assertOk();
$this->withToken($token->expired()->mint())->getJson('/api/reports')->assertUnauthorized();
```

These are real RS256 tokens, verified by the same code that verifies production ones —
signature, issuer, audience, expiry — so a test that passes here is not passing
because a mock waved it through.

## Assert provisioning

```php
$this->post('/teams', ['name' => 'Acme']);

$cbox->management()->assertOrganizationCreated(fn ($org) => $org->name === 'Acme');
$cbox->management()->assertInvited('ada@example.com', 'org_fake_1');
$cbox->management()->assertRoleAssigned('org_fake_1', 'user_1', 'role_editor');
$cbox->management()->assertCalled('removeMember', times: 0);
```

The fake keeps enough state to read back (create an organization, then list its
members), refuses unknown ids with `ResourceNotFound`, and `failNext('invite', $e)`
makes the next call throw — for testing your error handling.

## Customer API keys

```php
$cbox->apiKeys()->add('ctx_live_abc', 'user_1', 'org_1', permissions: ['reports:read']);

$this->withToken('ctx_live_abc')->getJson('/v1/reports')->assertOk();

$cbox->apiKeys()->unavailable();   // Cbox ID "down": the middleware answers 503
```

## Back-channel calls

`machineToken()` and `refresh()` mint real tokens; `revoke()` is recorded
(`$cbox->client()->assertRevoked($token)`).
