<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\ProvisionsServiceConnections;
use Cbox\Id\Client\Contracts\ServiceConnections;
use Cbox\Id\Client\Exceptions\ServiceConnectionFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Testing\FakeServiceConnections;
use Cbox\Id\Client\ValueObjects\ServiceConnection;
use Cbox\Id\Client\ValueObjects\ServiceConnectionLease;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['cbox-id-client.issuer' => 'https://id.example.test', 'cbox-id-client.client_id' => 'signals']);
    $this->grant = ['id' => 'grant-1', 'organization_id' => 'org-1', 'service' => 'id_events', 'consumer_client_id' => 'signals',
        'audience' => 'https://id.example.test/feed', 'scopes' => ['events:read'], 'revoked_at' => null];
    $this->connection = new ServiceConnection('grant-1', 'org-1', 'id_events', 'signals', 'https://id.example.test/feed', ['events:read']);
    $identity = Mockery::mock(IdentityClient::class);
    $identity->shouldReceive('machineToken')->with(['service_connections.lease'])->andReturn('machine-token');
    app()->instance(IdentityClient::class, $identity);
    Http::preventStrayRequests();
});

it('creates an organization-bound grant using the human authorization', function (): void {
    Http::fake(['https://id.example.test/api/v1/service-connections/id_events' => Http::response($this->grant)]);
    $grant = app(ServiceConnections::class)->connect('human-token', 'org-1', 'id_events', 'https://id.example.test/feed');
    expect($grant->id)->toBe('grant-1')->and($grant->scopes)->toBe(['events:read']);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer human-token'));
});

it('provisions through the separate machine capability with an explicit organization', function (): void {
    config(['cbox-id-client.api_url' => 'https://api.id.example.test']);
    app(IdentityClient::class)->shouldReceive('machineToken')->with(['id:service-connection-provisions:write'])->andReturn('provision-token');
    Http::fake(['https://api.id.example.test/v1/service-connections/id_events/provision' => Http::response($this->grant)]);
    $grant = app(ProvisionsServiceConnections::class)->provision('org-1', 'id_events', $this->connection->audience);
    expect($grant)->toEqual($this->connection);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer provision-token') && $request->data() === ['organization_id' => 'org-1']);
    $fake = new FakeServiceConnections([$grant, new ServiceConnectionFailed('Revoked', 409)]);
    expect($fake->provision('org-1', 'id_events', $grant->audience))->toBe($grant);
    expect(fn () => $fake->provision('org-1', 'id_events', $grant->audience))->toThrow(ServiceConnectionFailed::class);
});

it('preserves machine provisioning refusals without falling back or leaking upstream data', function (int $status): void {
    config(['cbox-id-client.api_url' => 'https://api.id.example.test']);
    app(IdentityClient::class)->shouldReceive('machineToken')->with(['id:service-connection-provisions:write'])->andReturn('provision-token');
    Http::fake(['*' => Http::response(['secret' => 'must-not-leak'], $status)]);
    try {
        app(ProvisionsServiceConnections::class)->provision('org-1', 'id_events', $this->connection->audience);
        $this->fail('A failed provisioning request must not return a grant.');
    } catch (ServiceConnectionFailed $error) {
        expect($error->getCode())->toBe($status);
        expect($error->getMessage())->not->toContain('must-not-leak');
    }
    Http::assertSentCount(1);
})->with([403, 409, 503]);

it('refuses machine provisioning on a legacy-only configuration', function (): void {
    expect(fn () => app(ProvisionsServiceConnections::class)->provision('org-1', 'id_events', $this->connection->audience))
        ->toThrow(ServiceConnectionFailed::class, 'CBOX_ID_API_URL');
    Http::assertNothingSent();
});

it('sanitizes machine credential failures across connection operations', function (string $failure, int $status, string $operation): void {
    app()->forgetInstance(IdentityClient::class);
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_secret' => 'test-secret',
        'cbox-id-client.api_url' => 'https://api.id.example.test']);
    Http::fake([
        '*/.well-known/openid-configuration' => $failure === 'discovery'
            ? Http::response(['secret' => 'must-not-leak'], 503)
            : Http::response(['issuer' => 'https://id.test', 'token_endpoint' => 'https://id.test/oauth/token']),
        '*/oauth/token' => function () use ($failure, $status) {
            if ($failure === 'transport') {
                throw new ConnectionException('must-not-leak');
            }

            return Http::response(['error' => 'must-not-leak', 'error_description' => 'test-secret'], $failure === 'malformed' ? 200 : $status);
        },
    ]);
    try {
        match ($operation) {
            'provision' => app(ProvisionsServiceConnections::class)->provision('org-1', 'id_events', $this->connection->audience),
            'lease' => app(ServiceConnections::class)->lease($this->connection),
            'status' => app(ServiceConnections::class)->status($this->connection),
        };
        $this->fail('Credential failure must not become a grant or a successful status.');
    } catch (ServiceConnectionFailed $error) {
        expect($error->getCode())->toBe($status)->and($error->getPrevious())->toBeNull()
            ->and($error->getMessage())->not->toContain('must-not-leak', 'test-secret');
    }
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/service-connections/'));
})->with([['token', 503], ['token', 401], ['discovery', 503], ['transport', 503], ['malformed', 502]])
    ->with(['provision', 'lease', 'status']);

it('renews only the refused provisioning credential on the next explicit attempt', function (): void {
    app()->forgetInstance(IdentityClient::class);
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_secret' => 'test-secret',
        'cbox-id-client.api_url' => 'https://api.id.example.test']);
    fakeTokenEndpoint(fn (int $n) => ['access_token' => 'machine-'.$n, 'expires_in' => 3600]);
    Http::fake(['https://api.id.example.test/v1/service-connections/id_events/provision' => Http::sequence()
        ->push(['error' => 'invalid_token'], 401)->push($this->grant)]);
    $identity = app(IdentityClient::class);
    $leaseToken = $identity->machineToken(['service_connections.lease']);
    $client = app(ProvisionsServiceConnections::class);
    expect(fn () => $client->provision('org-1', 'id_events', $this->connection->audience))->toThrow(ServiceConnectionFailed::class);
    expect($identity->machineToken(['service_connections.lease']))->toBe($leaseToken);
    expect($client->provision('org-1', 'id_events', $this->connection->audience))->toEqual($this->connection);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/provision') && $request->hasHeader('Authorization', 'Bearer machine-3'));
    expect(tokenRequests())->toBe(3);
});

it('rejects a grant with mismatched ownership or scope', function (string $field, mixed $value, bool $machine): void {
    Http::fake(['*' => Http::response([...$this->grant, $field => $value])]);
    if ($machine) {
        config(['cbox-id-client.api_url' => 'https://api.id.example.test']);
        app(IdentityClient::class)->shouldReceive('machineToken')->with(['id:service-connection-provisions:write'])->andReturn('provision-token');
        expect(fn () => app(ProvisionsServiceConnections::class)->provision('org-1', 'id_events', $this->connection->audience))->toThrow(ServiceConnectionFailed::class);

        return;
    }
    expect(fn () => app(ServiceConnections::class)->connect('human-token', 'org-1', 'id_events', 'https://id.example.test/feed'))->toThrow(ServiceConnectionFailed::class);
})->with([
    ['organization_id', 'other'], ['service', 'billing'], ['audience', 'https://other.test'], ['consumer_client_id', 'other'],
    ['revoked_at', '2026-11-05'], ['id', ''], ['scopes', []], ['scopes', ['events:read', 'events:read']], ['scopes', ['bad scope']],
])->with([false, true]);

it('leases through the machine identity and validates the provider audience and scopes', function (): void {
    Http::fake(['*' => Http::response(['access_token' => 'provider-token', 'token_type' => 'Bearer', 'expires_in' => 300, 'scope' => 'events:read', 'audience' => $this->connection->audience])]);
    $lease = app(ServiceConnections::class)->lease($this->connection);
    expect($lease->accessToken)->toBe('provider-token')->and($lease->expiresAt)->toBeGreaterThan(time());
    Http::assertSent(fn ($request) => $request->url() === 'https://id.example.test/api/v1/service-connections/grant-1/token' && $request->hasHeader('Authorization', 'Bearer machine-token'));
});

it('rejects malformed or broader leases', function (string $field, mixed $value): void {
    Http::fake(['*' => Http::response(['access_token' => 'secret', 'token_type' => 'Bearer', 'expires_in' => 300, 'scope' => 'events:read', 'audience' => $this->connection->audience, $field => $value])]);
    expect(fn () => app(ServiceConnections::class)->lease($this->connection))->toThrow(ServiceConnectionFailed::class);
})->with([['audience', 'https://other.test'], ['scope', 'events:read events:write'], ['expires_in', 0], ['expires_in', '300'], ['token_type', 'DPoP'], ['access_token', '']]);

it('reads live status and reports central revocation', function (): void {
    Http::fake(['*' => Http::sequence()->push(['active' => true, ...$this->grant])->push(['active' => false])]);
    expect(app(ServiceConnections::class)->status($this->connection))->toBeTrue();
    expect(app(ServiceConnections::class)->status($this->connection))->toBeFalse();
});

it('does not treat malformed status as a revoked or active grant', function (): void {
    Http::fake(['*' => Http::response(['active' => true, ...$this->grant, 'organization_id' => 'other'])]);
    expect(fn () => app(ServiceConnections::class)->status($this->connection))->toThrow(ServiceConnectionFailed::class);
});

it('requires explicit revocation confirmation', function (): void {
    Http::fake(['*' => Http::sequence()->push(['revoked' => true])->push([])]);
    app(ServiceConnections::class)->revoke('human-token', $this->connection);
    expect(fn () => app(ServiceConnections::class)->revoke('human-token', $this->connection))->toThrow(ServiceConnectionFailed::class);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->hasHeader('Authorization', 'Bearer human-token'));
});

it('does not follow redirects or disclose upstream error bodies', function (): void {
    Http::fake(['*' => Http::response(['secret' => 'must-not-leak'], 302, ['Location' => 'https://other.test'])]);
    expect(fn () => app(ServiceConnections::class)->lease($this->connection))->toThrow(ServiceConnectionFailed::class, 'ID refused');
    Http::assertSentCount(1);
});

it('rejects insecure issuer configuration before sending credentials', function (): void {
    config(['cbox-id-client.issuer' => 'http://id.example.test']);
    expect(fn () => app(ServiceConnections::class)->connect('human-token', 'org-1', 'id_events', 'https://id.example.test/feed'))->toThrow(ServiceConnectionFailed::class);
    Http::assertNothingSent();
});

it('refuses a different consumer before requesting a lease', function (): void {
    $connection = new ServiceConnection('grant-2', 'org-1', 'id_events', 'other', 'https://id.example.test/feed', ['events:read']);
    expect(fn () => app(ServiceConnections::class)->lease($connection))->toThrow(ServiceConnectionFailed::class);
    Http::assertNothingSent();
});

it('ships a replaceable fake with explicit lifecycle results', function (): void {
    $lease = new ServiceConnectionLease('test-only', time() + 60, $this->connection);
    $fake = new FakeServiceConnections([$this->connection, true, $lease, true, false]);
    app()->instance(ServiceConnections::class, $fake);
    $client = app(ServiceConnections::class);
    expect($client->connect('human', 'org-1', 'id_events', $this->connection->audience))->toBe($this->connection);
    expect($client->status($this->connection))->toBeTrue();
    expect($client->lease($this->connection))->toBe($lease);
    $client->revoke('human', $this->connection);
    expect($client->status($this->connection))->toBeFalse();
    expect(fn () => $client->status($this->connection))->toThrow(LogicException::class);
});
