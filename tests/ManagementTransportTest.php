<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\CboxIdApiException;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\ManagementNetworkException;
use Cbox\Id\Client\Exceptions\UnexpectedResponse;
use Cbox\Id\Client\Management\AccountClient;
use Cbox\Id\Client\Management\Environment\Schemas\App;
use Cbox\Id\Client\Management\Environment\Schemas\AppSecret;
use Cbox\Id\Client\Management\EnvironmentClient;
use Cbox\Id\Client\Management\PlatformClient;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\WorkspaceClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
 * The hand-written transport every generated client runs on — the PHP twin of id-js's
 * `ManagementTransport`, held to the same behaviour: one Idempotency-Key per write, reused
 * on every retry; retries only where they are safe; and errors typed the same way.
 */

const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

it('sends the key, JSON headers and a fresh Idempotency-Key on a write', function (): void {
    $seen = fakeManagement(envelope(appData(), 201, ['X-Request-Id' => 'req_1']));

    $response = envClient()->apps->create(['name' => 'Billing', 'type' => 'web', 'redirect_uris' => ['https://billing.acme.test/callback']]);

    $request = $seen[0];
    expect($request->method())->toBe('POST')
        ->and($request->url())->toBe('https://acme.test/api/v1/apps')
        ->and($request->header('Authorization'))->toBe(['Bearer cbid_env_test'])
        ->and($request->header('Accept'))->toBe(['application/json'])
        ->and($request->header('Content-Type'))->toBe(['application/json'])
        ->and($request->header('Idempotency-Key')[0])->toMatch(UUID_V4)
        ->and($request->data())->toBe(['name' => 'Billing', 'type' => 'web', 'redirect_uris' => ['https://billing.acme.test/callback']]);

    expect($response->data)->toBeInstanceOf(App::class)
        ->and($response->data->clientId)->toBe('cid_1')
        ->and($response->data->clientSecret)->toBe('csec_shown_once')
        ->and($response->status)->toBe(201)
        ->and($response->replayed)->toBeFalse()
        ->and($response->idempotencyKey)->toBe($request->header('Idempotency-Key')[0])
        ->and($response->requestId)->toBe('req_1');
});

it('sends no Idempotency-Key and no body on a read', function (): void {
    $seen = fakeManagement(envelope(appData()));

    envClient()->apps->get('app 1/2');

    expect($seen[0]->method())->toBe('GET')
        ->and($seen[0]->url())->toBe('https://acme.test/api/v1/apps/app%201%2F2')
        ->and($seen[0]->hasHeader('Idempotency-Key'))->toBeFalse()
        ->and($seen[0]->body())->toBe('');
});

it('retries a 5xx with the SAME Idempotency-Key, and reports a replayed answer', function (): void {
    $seen = fakeManagement(
        Http::response(['error' => 'server_error', 'message' => 'Boom'], 503),
        Http::response(['error' => 'idempotency_in_progress', 'message' => 'Still running'], 409),
        envelope(appData(['client_secret' => null]), 201, ['Idempotent-Replayed' => 'true']),
    );

    $response = envClient()->apps->create(['name' => 'Billing']);

    expect($seen)->toHaveCount(3);
    $keys = array_map(fn ($r) => $r->header('Idempotency-Key')[0], $seen->getArrayCopy());
    expect(array_unique($keys))->toHaveCount(1)
        ->and($response->replayed)->toBeTrue()
        ->and($response->idempotencyKey)->toBe($keys[0])
        ->and($response->data->clientSecret)->toBeNull();
    Sleep::assertSleptTimes(2);
});

it('uses the caller\'s own Idempotency-Key', function (): void {
    $seen = fakeManagement(envelope(appData(), 201));

    $response = envClient()->apps->create(['name' => 'Billing'], new CallOptions(idempotencyKey: 'my-key-1'));

    expect($seen[0]->header('Idempotency-Key'))->toBe(['my-key-1'])
        ->and($response->idempotencyKey)->toBe('my-key-1');
});

it('retries a dropped connection with the same key, then gives up with that key', function (): void {
    $seen = fakeManagement(Http::failedConnection(), envelope(appData(), 201));

    envClient()->apps->create(['name' => 'Billing']);
    expect($seen)->toHaveCount(2)
        ->and($seen[0]->header('Idempotency-Key'))->toBe($seen[1]->header('Idempotency-Key'));

    $seen = fakeManagement(Http::failedConnection(), Http::failedConnection());

    try {
        envClient(maxRetries: 1)->apps->create(['name' => 'Billing'], new CallOptions(idempotencyKey: 'k-net'));
        $this->fail('Expected a network exception.');
    } catch (ManagementNetworkException $e) {
        expect($e->idempotencyKey)->toBe('k-net')
            ->and($e->getMessage())->toStartWith('POST /api/v1/apps failed:')
            ->and($seen)->toHaveCount(2);
    }
});

it('honours Retry-After, and throws one longer than it will wait', function (): void {
    fakeManagement(Http::response(['error' => 'rate_limited', 'message' => 'Slow down'], 429, ['Retry-After' => '2']), envelope(appData()));

    envClient()->apps->get('app_1');
    Sleep::assertSequence([Sleep::usleep(2_000_000)]);

    fakeManagement(Http::response(['error' => 'rate_limited', 'message' => 'Slow down'], 429, ['Retry-After' => '120']));

    expect(fn () => envClient()->apps->get('app_1'))->toThrow(function (CboxIdApiException $e): void {
        expect($e->status)->toBe(429)
            ->and($e->isRateLimited())->toBeTrue()
            ->and($e->retryAfter)->toBe(120);
    });
});

it('does not retry a 4xx that is not about a running duplicate', function (): void {
    $seen = fakeManagement(Http::response(['error' => 'last_owner', 'message' => 'Keep an owner'], 409));

    expect(fn () => envClient()->apps->delete('app_1'))->toThrow(CboxIdApiException::class, 'Keep an owner');
    expect($seen)->toHaveCount(1);
});

it('types a validation failure, with field errors and the request id', function (): void {
    fakeManagement(Http::response([
        'error' => 'validation_failed',
        'message' => 'The name field is required.',
        'errors' => ['name' => ['The name field is required.'], 'type' => 'Pick a type.'],
        'request_id' => 'req_body',
    ], 422, ['X-Request-Id' => 'req_header']));

    try {
        envClient()->apps->create([]);
        $this->fail('Expected a validation failure.');
    } catch (CboxIdApiException $e) {
        expect($e->status)->toBe(422)
            ->and($e->error)->toBe('validation_failed')
            ->and($e->isValidationError())->toBeTrue()
            ->and($e->getMessage())->toBe('The name field is required.')
            ->and($e->errors)->toBe(['name' => ['The name field is required.'], 'type' => ['Pick a type.']])
            ->and($e->requestId)->toBe('req_body');
    }

    fakeManagement(Http::response(['error' => 'invalid_token', 'error_description' => 'The token expired.'], 401, ['X-Request-Id' => 'req_h']));

    expect(fn () => envClient()->apps->get('app_1'))->toThrow(function (CboxIdApiException $e): void {
        expect($e->isUnauthorized())->toBeTrue()
            ->and($e->getMessage())->toBe('The token expired.')
            ->and($e->requestId)->toBe('req_h');
    });

    fakeManagement(Http::response('', 404));

    expect(fn () => envClient()->apps->get('app_1'))->toThrow(function (CboxIdApiException $e): void {
        expect($e->error)->toBe('http_404')->and($e->isNotFound())->toBeTrue();
    });
});

it('names the environment for a root access token, and asks the token provider every time', function (): void {
    $seen = fakeManagement(envelope(appData()), envelope(appData()));
    $calls = 0;

    $env = new EnvironmentClient(
        baseUrl: 'https://api.cboxid.test',
        accessToken: function () use (&$calls): string {
            return 'root_token_'.(++$calls);
        },
        environment: 'acme-staging',
    );

    $env->apps->get('app_1');
    $env->apps->get('app_1');

    expect($seen[0]->url())->toBe('https://api.cboxid.test/api/v1/apps/app_1')
        ->and($seen[0]->header('Cbox-Environment'))->toBe(['acme-staging'])
        ->and($seen[0]->header('Authorization'))->toBe(['Bearer root_token_1'])
        ->and($seen[1]->header('Authorization'))->toBe(['Bearer root_token_2']);
});

it('refuses credentials that cannot work before sending anything', function (Closure $build, string $message): void {
    expect($build)->toThrow(ClientConfigurationException::class, $message);
})->with([
    'a workspace key on the environment plane' => [fn () => new EnvironmentClient(baseUrl: 'https://acme.test', apiKey: 'cbid_ws_x'), 'cannot call the environment plane'],
    'an environment key on the workspace plane' => [fn () => new WorkspaceClient(apiKey: 'cbid_env_x'), 'cannot call the workspace plane'],
    'a key on the platform plane' => [fn () => new PlatformClient(apiKey: 'cbid_env_x'), 'accepts no management key'],
    'a key on the account plane' => [fn () => new AccountClient(baseUrl: 'https://acme.test', apiKey: 'cbid_env_x'), 'accepts no management key'],
    'both a key and a token' => [fn () => new EnvironmentClient(baseUrl: 'https://acme.test', apiKey: 'cbid_env_x', accessToken: 't'), 'exactly one'],
    'neither' => [fn () => new EnvironmentClient(baseUrl: 'https://acme.test'), 'exactly one'],
    'an environment with a key' => [fn () => new EnvironmentClient(baseUrl: 'https://acme.test', apiKey: 'cbid_env_x', environment: 'staging'), 'bound to its own environment'],
    'an environment on another plane' => [fn () => new WorkspaceClient(accessToken: 't', environment: 'staging'), 'applies to the environment plane'],
    'plain http' => [fn () => new EnvironmentClient(baseUrl: 'http://acme.test', apiKey: 'cbid_env_x'), 'must be https'],
    'no host for an environment' => [fn () => new EnvironmentClient(apiKey: 'cbid_env_x'), 'needs a `baseUrl`'],
]);

it('appends /api/v1 once, allows loopback http, and defaults the root planes', function (): void {
    expect((new EnvironmentClient(baseUrl: 'https://acme.test/', apiKey: 'cbid_env_x'))->transport->baseUrl)->toBe('https://acme.test/api/v1')
        ->and((new EnvironmentClient(baseUrl: 'https://acme.test/api/v1', apiKey: 'cbid_env_x'))->transport->baseUrl)->toBe('https://acme.test/api/v1')
        ->and((new EnvironmentClient(baseUrl: 'http://localhost:8000', apiKey: 'cbid_env_x'))->transport->baseUrl)->toBe('http://localhost:8000/api/v1')
        ->and((new WorkspaceClient(apiKey: 'cbid_ws_x'))->transport->baseUrl)->toBe('https://api.cboxid.com/api/v1')
        ->and((new PlatformClient(accessToken: 't'))->transport->baseUrl)->toBe('https://api.cboxid.com/api/v1');
});

it('encodes a query the way the server reads it', function (): void {
    $seen = fakeManagement(envelope([], meta: ['has_more' => false]));

    envClient()->auditLogs->events->list(['organization_id' => 'org_1', 'actions' => ['a.b', 'c.d'], 'limit' => 10]);

    expect($seen[0]->url())->toBe('https://acme.test/api/v1/audit-logs/events?organization_id=org_1&actions%5B%5D=a.b&actions%5B%5D=c.d&limit=10');
});

it('keeps Authorization its own whatever headers the caller adds', function (): void {
    $seen = fakeManagement(envelope(appData()));

    envClient(headers: ['User-Agent' => 'acme/1.0', 'authorization' => 'Bearer stolen'])
        ->apps->get('app_1', new CallOptions(headers: ['X-Trace' => 't1']));

    expect($seen[0]->header('Authorization'))->toBe(['Bearer cbid_env_test'])
        ->and($seen[0]->header('User-Agent'))->toBe(['acme/1.0'])
        ->and($seen[0]->header('X-Trace'))->toBe(['t1']);
});

it('says where a response broke its contract, and keeps the body and key for a retry', function (): void {
    fakeManagement(envelope(['id' => 'app_1', 'name' => 'Billing'], 201));

    try {
        envClient()->apps->create(['name' => 'Billing'], new CallOptions(idempotencyKey: 'k-shape'));
        $this->fail('Expected an unexpected response.');
    } catch (UnexpectedResponse $e) {
        expect($e->getMessage())->toBe('App.client_id: expected a value, got null.')
            ->and($e->idempotencyKey)->toBe('k-shape')
            ->and($e->body)->toBe(['data' => ['id' => 'app_1', 'name' => 'Billing']]);
    }
});

it('turns schema objects back into the wire form', function (): void {
    fakeManagement(envelope(['id' => 'sec_1', 'hint' => 'x9', 'client_secret' => 'csec_new'], 201));

    $secret = envClient()->apps->secrets->rotate('app_1', ['grace_seconds' => 0])->data;

    expect($secret)->toBeInstanceOf(AppSecret::class)
        ->and($secret->toArray())->toBe([
            'id' => 'sec_1',
            'hint' => 'x9',
            'created_at' => null,
            'expires_at' => null,
            'last_used_at' => null,
            'client_secret' => 'csec_new',
            'previous_expire_at' => null,
        ])
        ->and(json_encode($secret))->toBe(json_encode($secret->toArray()));
});

it('answers null for a 204', function (): void {
    fakeManagement(Http::response(null, 204));

    $response = envClient()->apps->secrets->revoke('app_1', 'sec_1');

    expect($response->status)->toBe(204)->and($response->data)->toBeNull();
});

it('reaches a route the generated surface does not cover', function (): void {
    $seen = fakeManagement(Http::response(['data' => ['ok' => true]], 201));

    $response = envClient()->request('POST', '/experimental/thing', ['dry_run' => true], ['name' => 'x']);

    expect($seen[0]->url())->toBe('https://acme.test/api/v1/experimental/thing?dry_run=1')
        ->and($seen[0]->header('Idempotency-Key')[0])->toMatch(UUID_V4)
        ->and($response->data)->toBe(['ok' => true]);
});
