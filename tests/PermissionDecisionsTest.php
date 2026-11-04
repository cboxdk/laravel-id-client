<?php

declare(strict_types=1);

use Cbox\Id\Client\Authz\PermissionDecision;
use Cbox\Id\Client\Contracts\PermissionDecisions;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Facades\CboxId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_id' => 'client_1']);
    Http::preventStrayRequests();
});

function permissionDecisionBody(): array
{
    return ['mode' => 'rbac', 'subject' => ['type' => 'user', 'id' => 'user-1'], 'organization' => 'org-1', 'client_id' => 'client_1', 'org_role' => 'member', 'organization_active' => true, 'allowed' => true, 'results' => [['permission' => 'stache:exec', 'allowed' => true]]];
}

it('asks Cbox ID live on every call with exact subject organization and app binding', function (): void {
    $allowed = permissionDecisionBody();
    $denied = array_replace($allowed, ['allowed' => false, 'org_role' => null, 'results' => [['permission' => 'stache:exec', 'allowed' => false]]]);
    Http::fake(['https://id.test/oauth/decisions' => Http::sequence()->push($allowed)->push($denied)]);
    $client = app(PermissionDecisions::class);
    $first = $client->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
    $second = $client->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
    expect($first->allowed())->toBeTrue()->and($first->organizationRole)->toBe(OrganizationRole::Member)
        ->and($second->allowed())->toBeFalse()->and($second->organizationRole)->toBeNull()
        ->and($first->allows('unrequested:permission'))->toBeFalse();
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer issuer-token') && $request->data() === ['permission' => ['stache:exec'], 'subject' => 'user-1', 'org' => 'org-1']);
});

it('refuses malformed or differently scoped responses', function (string $path, mixed $value): void {
    $body = permissionDecisionBody();
    data_set($body, $path, $value);
    Http::fake(['https://id.test/oauth/decisions' => Http::response($body)]);
    app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
})->with([
    'another subject' => ['subject.id', 'user-2'],
    'another organization' => ['organization', 'org-2'],
    'another app' => ['client_id', 'client_2'],
    'non-user subject' => ['subject.type', 'service'],
    'wrong mode' => ['mode', 'rebac'],
    'truthy active' => ['organization_active', 'true'],
    'truthy allowed' => ['allowed', 1],
    'unknown membership' => ['org_role', 'superadmin'],
    'no checks' => ['results', []],
    'extra check' => ['results', [['permission' => 'stache:exec', 'allowed' => true], ['permission' => 'other', 'allowed' => true]]],
    'unrequested check' => ['results.0.permission', 'other'],
    'truthy check' => ['results.0.allowed', 'yes'],
    'inconsistent summary' => ['allowed', false],
    'inactive organization with allow' => ['organization_active', false],
])->throws(AuthenticationFailed::class);

it('refuses duplicate results even if the count matches', function (): void {
    $body = permissionDecisionBody();
    $body['results'][] = $body['results'][0];
    Http::fake(['https://id.test/oauth/decisions' => Http::response($body)]);
    app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec', 'stache:read']);
})->throws(AuthenticationFailed::class);

it('does not follow redirects or return error bodies as decisions', function (int $status): void {
    Http::fake(function (Request $request, array $options) use ($status) {
        expect($options['allow_redirects'])->toBeFalse();

        return Http::response(['error_description' => 'issuer-token should never appear in an error'], $status, ['Location' => 'https://other.test']);
    });
    try {
        app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
        test()->fail('Expected HTTP refusal');
    } catch (AuthenticationFailed $error) {
        expect($error->status)->toBe($status)->and($error->getMessage())->not->toContain('issuer-token')->and($error->getPrevious())->toBeNull();
    }
    Http::assertSentCount(1);
})->with([302, 401, 403, 429, 500]);

it('refuses oversized responses and invalid JSON', function (string $body): void {
    Http::fake(['https://id.test/oauth/decisions' => Http::response($body)]);
    app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
})->with(['oversized' => str_repeat(' ', 65537), 'invalid' => '{invalid', 'scalar' => 'true'])->throws(AuthenticationFailed::class);

it('rejects empty and oversized batches before a network call', function (array $permissions): void {
    try {
        app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', $permissions);
        test()->fail('Expected invalid request');
    } catch (AuthenticationFailed) {
        Http::assertNothingSent();
    }
})->with(['empty' => [[]], 'blank' => [[' ']], 'oversized key' => [[str_repeat('x', 256)]], 'oversized batch' => [array_fill(0, 51, 'stache:exec')]]);

it('ships a default-denying fake without outgoing requests or plaintext token recordings', function (): void {
    $fake = CboxId::fake()->permissionDecisions();
    $fake->queue(new PermissionDecision('user-1', 'org-1', 'client_1', OrganizationRole::Member, true, ['stache:exec' => true]));
    $client = app(PermissionDecisions::class);
    expect($client->check('issuer-token', 'user-1', 'org-1', ['stache:exec'])->allowed())->toBeTrue()
        ->and($client->check('issuer-token', 'user-1', 'org-1', ['stache:exec'])->allowed())->toBeFalse()
        ->and($fake->calls[0]['token_hash'])->toBe(hash('sha256', 'issuer-token'))
        ->and(json_encode($fake->calls))->not->toContain('issuer-token')
        ->and((new PermissionDecision)->allowed())->toBeFalse();
    Http::assertNothingSent();
});

it('refuses unsafe issuer URLs without sending a token', function (string $issuer): void {
    config(['cbox-id-client.issuer' => $issuer]);
    try {
        app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
        test()->fail('Expected invalid issuer');
    } catch (AuthenticationFailed) {
        Http::assertNothingSent();
    }
})->with(['http://id.test', 'https://user:password@id.test', 'https://id.test?x=1', 'https://id.test#fragment', '']);

it('redacts transport exceptions without preserving a credential-bearing previous exception', function (): void {
    Http::fake(fn () => throw new ConnectionException('issuer-token private transport detail'));
    try {
        app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
        test()->fail('Expected unavailable issuer');
    } catch (AuthenticationFailed $error) {
        expect($error->getMessage())->not->toContain('issuer-token')->and($error->getPrevious())->toBeNull();
    }
});

it('lets the consumer fake exercise an unavailable identity service', function (): void {
    CboxId::fake()->permissionDecisions()->queue(AuthenticationFailed::because('Identity unavailable'));
    app(PermissionDecisions::class)->check('issuer-token', 'user-1', 'org-1', ['stache:exec']);
})->throws(AuthenticationFailed::class, 'Identity unavailable');
