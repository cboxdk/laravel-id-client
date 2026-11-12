<?php

declare(strict_types=1);

use Cbox\Id\Client\Authz\PermissionDecision;
use Cbox\Id\Client\Contracts\KeptPermissionDecisions;
use Cbox\Id\Client\Contracts\PermissionDecisions;
use Cbox\Id\Client\Decisions\KeptDecisions;
use Cbox\Id\Client\Decisions\RefreshKeptDecision;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\IdentityClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Illuminate\Support\defer;

/*
 * Pages render from kept answers and keep rendering while Cbox ID is down; changes
 * never act on a stale answer.
 */

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
        'cbox-id-client.decisions.fresh_for' => 30,
        'cbox-id-client.decisions.max_age' => 3600,
    ]);
    Cache::flush();
    Http::preventStrayRequests();
    $this->decisionState = 'allow';
    $this->decisionOptions = [];

    $state = &$this->decisionState;
    $options = &$this->decisionOptions;
    Http::fake(['*/oauth/decisions' => static function (ClientRequest $request, array $sent) use (&$state, &$options) {
        $options = $sent;

        return match ($state) {
            'down' => throw new ConnectionException('Connection refused'),
            'unauthorized' => Http::response(['error' => 'invalid_token'], 401),
            default => Http::response(keptDecisionBody($state === 'allow')),
        };
    }]);
    fakeCbox();
});

/** @return array<string, mixed> */
function keptDecisionBody(bool $allowed): array
{
    return [
        'mode' => 'rbac', 'subject' => ['type' => 'user', 'id' => 'user-1'], 'organization' => 'org-1', 'client_id' => 'client_1',
        'org_role' => 'member', 'organization_active' => true, 'allowed' => $allowed,
        'results' => [['permission' => 'invoices:approve', 'allowed' => $allowed]],
    ];
}

function decisionRequests(): int
{
    return Http::recorded(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/oauth/decisions'))->count();
}

function machineTokenRequests(): int
{
    return Http::recorded(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/oauth/token'))->count();
}

function servingRequest(string $method): void
{
    app()->instance('request', Request::create('https://app.test/invoices', $method));
}

function keptCheck(bool $acting = false): PermissionDecision
{
    return app(KeptPermissionDecisions::class)->check('user-1', 'org-1', ['invoices:approve'], $acting);
}

it('asks once on the first visit and keeps the answer while it is fresh', function (): void {
    expect(keptCheck()->allowed())->toBeTrue()
        ->and(keptCheck()->allowed())->toBeTrue();

    $this->travel(30)->seconds();
    expect(keptCheck()->allowed())->toBeTrue()
        ->and(decisionRequests())->toBe(1);
});

it('keeps a no as well as a yes', function (): void {
    $this->decisionState = 'deny';

    expect(keptCheck()->allowed())->toBeFalse()
        ->and(keptCheck()->allowed())->toBeFalse()
        ->and(decisionRequests())->toBe(1);
});

it('serves a stale answer to a page view at once and queues a refresh', function (): void {
    Queue::fake();
    keptCheck();
    $this->decisionState = 'deny';
    $this->travel(31)->seconds();

    expect(keptCheck()->allowed())->toBeTrue()
        ->and(decisionRequests())->toBe(1);
    Queue::assertPushed(RefreshKeptDecision::class, static fn (RefreshKeptDecision $job): bool => $job->subject === 'user-1'
        && $job->organization === 'org-1' && $job->permissions === ['invoices:approve']);
});

it('shows a changed answer one page view later', function (): void {
    keptCheck();
    $this->decisionState = 'deny';
    $this->travel(31)->seconds();

    // No queue worker (the sync driver): the refresh runs after this page's response,
    // never inside the request.
    expect(keptCheck()->allowed())->toBeTrue()
        ->and(decisionRequests())->toBe(1);

    defer()->invoke();

    expect(keptCheck()->allowed())->toBeFalse()
        ->and(decisionRequests())->toBe(2);
});

it('treats HEAD as a page view', function (): void {
    Queue::fake();
    keptCheck();
    $this->travel(31)->seconds();
    servingRequest('HEAD');

    keptCheck();
    expect(decisionRequests())->toBe(1);
    Queue::assertPushed(RefreshKeptDecision::class);
});

it('asks now for a change when the kept answer is stale', function (string $method): void {
    Queue::fake();
    keptCheck();
    $this->decisionState = 'deny';
    $this->travel(31)->seconds();
    servingRequest($method);

    expect(keptCheck()->allowed())->toBeFalse()
        ->and(decisionRequests())->toBe(2);
    Queue::assertNothingPushed();

    // The new answer is kept for the next page view.
    servingRequest('GET');
    expect(keptCheck()->allowed())->toBeFalse()
        ->and(decisionRequests())->toBe(2);
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('lets a change use a fresh answer', function (): void {
    keptCheck();
    servingRequest('POST');

    expect(keptCheck()->allowed())->toBeTrue()
        ->and(decisionRequests())->toBe(1);
});

it('asks now when the caller is acting on a stale answer', function (): void {
    Queue::fake();
    keptCheck();
    $this->decisionState = 'deny';
    $this->travel(31)->seconds();

    expect(keptCheck(acting: true)->allowed())->toBeFalse()
        ->and(decisionRequests())->toBe(2);
    Queue::assertNothingPushed();
});

it('asks now when the kept answer is older than max_age', function (): void {
    Queue::fake();
    keptCheck();
    $this->decisionState = 'deny';
    $this->travel(3601)->seconds();

    expect(keptCheck()->allowed())->toBeFalse()
        ->and(decisionRequests())->toBe(2);
});

it('always asks outside an HTTP request', function (): void {
    $kept = new class(app(PermissionDecisions::class), app(IdentityClient::class), Cache::store(), app(), 'client_1') extends KeptDecisions
    {
        protected function currentRequest(): ?Request
        {
            return null;
        }
    };

    $kept->check('user-1', 'org-1', ['invoices:approve']);
    $kept->check('user-1', 'org-1', ['invoices:approve']);

    expect(decisionRequests())->toBe(2);
});

it('keeps serving the kept answer to page views while Cbox ID is down', function (): void {
    Exceptions::fake();
    keptCheck();
    $this->decisionState = 'down';
    $this->travel(31)->seconds();

    expect(keptCheck()->allowed())->toBeTrue();
    defer()->invoke();
    expect(keptCheck()->allowed())->toBeTrue();
    Exceptions::assertReported(AuthenticationFailed::class);
});

it('throws when Cbox ID is down and nothing is kept', function (): void {
    $this->decisionState = 'down';

    keptCheck();
})->throws(AuthenticationFailed::class);

it('throws for a change when Cbox ID is down and the kept answer is stale', function (): void {
    keptCheck();
    $this->decisionState = 'down';
    $this->travel(31)->seconds();
    servingRequest('POST');

    keptCheck();
})->throws(AuthenticationFailed::class);

it('serves the kept answer when the refresh cannot be queued', function (): void {
    Exceptions::fake();
    config(['cbox-id-client.decisions.queue_connection' => 'missing']);
    keptCheck();
    $this->travel(31)->seconds();

    expect(keptCheck()->allowed())->toBeTrue();
    Exceptions::assertReported(InvalidArgumentException::class);
});

it('reuses one machine token for every question', function (): void {
    keptCheck();
    app(KeptPermissionDecisions::class)->forget('user-1', 'org-1', ['invoices:approve']);
    keptCheck();
    servingRequest('POST');
    $this->travel(31)->seconds();
    keptCheck();

    expect(machineTokenRequests())->toBe(1)
        ->and(decisionRequests())->toBe(3);
    Http::assertSent(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/oauth/token') && $request['scope'] === 'decisions:read');
});

it('drops a refused machine token and asks once more with a new one', function (): void {
    keptCheck();
    $this->decisionState = 'unauthorized';
    $this->travel(31)->seconds();
    servingRequest('POST');

    expect(fn () => keptCheck())->toThrow(AuthenticationFailed::class)
        ->and(machineTokenRequests())->toBe(2)
        ->and(decisionRequests())->toBe(3);
});

it('ignores a kept entry it cannot read', function (mixed $entry): void {
    $key = app(KeptDecisions::class)->key('user-1', 'org-1', ['invoices:approve']);
    Cache::put($key, $entry, 3600);

    expect(keptCheck()->allowed())->toBeTrue()
        ->and(decisionRequests())->toBe(1);
})->with([
    'a string' => ['yes'],
    'no time' => [['v' => 1, 'subject' => 'user-1', 'organization' => 'org-1', 'client_id' => 'client_1', 'role' => null, 'active' => true, 'permissions' => ['invoices:approve' => true]]],
    'an unknown role' => [['v' => 1, 'at' => time(), 'subject' => 'user-1', 'organization' => 'org-1', 'client_id' => 'client_1', 'role' => 'owner-ish', 'active' => true, 'permissions' => ['invoices:approve' => true]]],
    'a truthy permission' => [['v' => 1, 'at' => time(), 'subject' => 'user-1', 'organization' => 'org-1', 'client_id' => 'client_1', 'role' => null, 'active' => true, 'permissions' => ['invoices:approve' => 1]]],
    'another version' => [['v' => 2, 'at' => time(), 'subject' => 'user-1', 'organization' => 'org-1', 'client_id' => 'client_1', 'role' => null, 'active' => true, 'permissions' => ['invoices:approve' => true]]],
]);

it('keeps only plain values in the cache', function (): void {
    keptCheck();
    $entry = Cache::get(app(KeptDecisions::class)->key('user-1', 'org-1', ['invoices:approve']));

    expect($entry)->toBeArray()
        ->and($entry['role'])->toBe('member')
        ->and($entry['permissions'])->toBe(['invoices:approve' => true])
        ->and(json_encode($entry))->not->toContain('at_1');
});

it('keys on client, subject, organization and the permission set in any order', function (): void {
    $kept = app(KeptDecisions::class);

    expect($kept->key('user-1', 'org-1', ['a', 'b']))->toBe($kept->key('user-1', 'org-1', ['b', 'a', 'b']))
        ->and($kept->key('user-1', 'org-1', ['a']))->not->toBe($kept->key('user-1', null, ['a']))
        ->and($kept->key('user-1', 'org-1', ['a']))->not->toBe($kept->key('user-2', 'org-1', ['a']))
        ->and($kept->key('user-1', 'org-1', ['a']))->not->toBe($kept->key('user-1', 'org-1', ['a', 'b']));

    config(['cbox-id-client.client_id' => 'client_2']);
    expect(app(KeptDecisions::class)->key('user-1', 'org-1', ['a']))->not->toBe($kept->key('user-1', 'org-1', ['a']));
});

it('asks again after forget', function (): void {
    keptCheck();
    app(KeptPermissionDecisions::class)->forget('user-1', 'org-1', ['invoices:approve']);
    keptCheck();

    expect(decisionRequests())->toBe(2);
});

it('runs the refresh job quietly when Cbox ID is down', function (): void {
    Exceptions::fake();
    $this->decisionState = 'down';

    (new RefreshKeptDecision('user-1', 'org-1', ['invoices:approve']))->handle(app(KeptPermissionDecisions::class));

    Exceptions::assertReported(AuthenticationFailed::class);
    expect((new RefreshKeptDecision('user-1', 'org-1', ['b', 'a']))->uniqueId())
        ->toBe((new RefreshKeptDecision('user-1', 'org-1', ['a', 'b']))->uniqueId());
});

it('works on top of the decisions fake', function (): void {
    $fake = CboxId::fake();
    $fake->permissionDecisions()->queue(new PermissionDecision('user-1', 'org-1', 'client_1', null, true, ['invoices:approve' => true]));

    expect(keptCheck()->allowed())->toBeTrue()
        ->and(keptCheck()->allowed())->toBeTrue()
        ->and($fake->permissionDecisions()->calls)->toHaveCount(1);
});

it('gives every call to Cbox ID a short connect timeout', function (): void {
    keptCheck();

    expect($this->decisionOptions['connect_timeout'] ?? null)->toBe(3);

    config(['cbox-id-client.http_connect_timeout' => 2]);
    app(KeptPermissionDecisions::class)->forget('user-1', 'org-1', ['invoices:approve']);
    keptCheck();

    expect($this->decisionOptions['connect_timeout'] ?? null)->toBe(2);
});
