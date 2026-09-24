<?php

declare(strict_types=1);

use Cbox\Id\Client\Enums\Prompt;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Choosing an organization happens at Cbox ID, in the authorization request: the
 * organization is IN the token, and only the issuer can re-issue it.
 */
beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    Cache::flush();
});

/** @return array<string, string> */
function authorizeQuery(RedirectResponse $response): array
{
    parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

    return array_filter($query, 'is_string');
}

/** Sign in with an id_token whose `org` is the given one. */
function signInBoundTo(?string $org): CboxUser
{
    fakeCbox(idToken(array_filter([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1', 'nonce' => 'nonce_1',
        'org' => $org, 'org_name' => $org !== null ? 'Org '.$org : null, 'org_role' => $org !== null ? 'admin' : null,
        'iat' => time(), 'exp' => time() + 900,
    ], static fn ($v) => $v !== null)));

    return app(IdentityClient::class)->authenticate(loginCallback());
}

it('sends organization, organization_hint and the organization prompts to authorize', function (): void {
    fakeCbox();
    $client = app(IdentityClient::class);

    expect(authorizeQuery($client->redirect(organization: 'org_2')))->toMatchArray(['organization' => 'org_2'])
        ->and(authorizeQuery($client->redirect(organizationHint: 'org_3')))->toMatchArray(['organization_hint' => 'org_3'])
        ->and(authorizeQuery($client->redirect(prompt: Prompt::SelectOrganization)))->toMatchArray(['prompt' => 'select_organization'])
        ->and(authorizeQuery($client->createOrganization()))->toMatchArray(['prompt' => 'create_organization'])
        ->and(authorizeQuery($client->selectOrganization(organizationHint: 'org_4')))->toMatchArray(['prompt' => 'select_organization', 'organization_hint' => 'org_4'])
        ->and(authorizeQuery($client->switchOrganization('org_5')))->toMatchArray(['organization' => 'org_5'])
        // Plain logins stay plain.
        ->and(authorizeQuery($client->redirect()))->not->toHaveKeys(['organization', 'organization_hint', 'prompt']);
});

it('accepts the string prompts it always did', function (): void {
    fakeCbox();

    expect(authorizeQuery(app(IdentityClient::class)->redirect(prompt: 'login')))->toMatchArray(['prompt' => 'login']);
});

it('completes a switch that Cbox ID bound to the requested organization', function (): void {
    // What switchOrganization() stashes is what the callback judges the answer against.
    session(['cbox-id-client.organization' => 'org_2']);

    expect(signInBoundTo('org_2')->organization()?->id)->toBe('org_2')
        ->and(session()->has('cbox-id-client.organization'))->toBeFalse();
});

it('stashes the organization a switch asked for', function (): void {
    fakeCbox();

    app(IdentityClient::class)->switchOrganization('org_2');

    expect(session('cbox-id-client.organization'))->toBe('org_2');
});

it('refuses a switch that came back bound to a different organization', function (): void {
    session(['cbox-id-client.organization' => 'org_2']);

    expect(fn () => signInBoundTo('org_1'))
        ->toThrow(AuthenticationFailed::class, 'different organization than the one requested');
});

it('refuses a switch that came back bound to no organization at all', function (): void {
    session(['cbox-id-client.organization' => 'org_2']);

    // UserInfo in the fake answers org_1; neither may stand in for the one requested.
    expect(fn () => signInBoundTo(null))
        ->toThrow(AuthenticationFailed::class, 'different organization than the one requested');
});

it('does not hold a plain login to an abandoned switch', function (): void {
    fakeCbox();
    $client = app(IdentityClient::class);

    $client->switchOrganization('org_2');     // abandoned — never came back
    $client->redirect();                       // a fresh, unbound login

    expect(session()->has('cbox-id-client.organization'))->toBeFalse();
});

it('says access_denied when Cbox ID refuses the organization', function (): void {
    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.organization' => 'org_9']);
    $request = Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'error' => 'access_denied', 'error_description' => 'Not a member of that organization.']);

    try {
        app(IdentityClient::class)->authenticate($request);
        $this->fail('Expected a refusal.');
    } catch (AuthenticationFailed $e) {
        expect($e->isAccessDenied())->toBeTrue()
            ->and($e->errorDescription)->toBe('Not a member of that organization.');
    }
});

it('remembers who signed in, and in which organization, for the rest of the session', function (): void {
    signInBoundTo('org_2');

    $identity = app(SessionIdentityStore::class)->identity();

    expect($identity?->subject)->toBe('user-1')
        ->and($identity?->organization()?->id)->toBe('org_2')
        ->and($identity?->organization()?->name)->toBe('Org org_2')
        // Never the tokens.
        ->and(json_encode(session()->all()))->not->toContain('at_1')
        ->and(json_encode(session()->all()))->not->toContain('rt_1');
});

it('remembers nothing when the application keeps identity itself', function (): void {
    config(['cbox-id-client.session.remember' => false]);
    $this->app->forgetInstance(IdentityClient::class);

    signInBoundTo('org_2');

    expect(app(SessionIdentityStore::class)->identity())->toBeNull();
});
