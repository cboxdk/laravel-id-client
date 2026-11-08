<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\BackchannelAuthentication;
use Cbox\Id\Client\Contracts\DeviceAuthorization;
use Cbox\Id\Client\Exceptions\AuthorizationDenied;
use Cbox\Id\Client\Exceptions\AuthorizationExpired;
use Cbox\Id\Client\Exceptions\GrantRateLimited;
use Cbox\Id\Client\Exceptions\GrantTimedOut;
use Cbox\Id\Client\Exceptions\InvalidTarget;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Testing\FakeBackchannelAuthentication;
use Cbox\Id\Client\Testing\FakeDeviceAuthorization;
use Cbox\Id\Client\ValueObjects\TokenResult;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;

const BRAIN_MCP = 'https://app.cboxbrain.com/mcp';

it('installs both fakes in the container through CboxId::fake()', function (): void {
    $cbox = CboxId::fake();

    expect(app(BackchannelAuthentication::class))->toBe($cbox->backchannelAuthentication())
        ->and(app(DeviceAuthorization::class))->toBe($cbox->deviceAuthorization());
});

it('scripts a CIBA approval after pending polls and records the request', function (): void {
    Http::fake();
    $ciba = CboxId::fake()->backchannelAuthentication();
    $ciba->pending(2)->approve(new TokenResult('at_brain', 'Bearer', 900, 'mcp:read'));

    $service = app(BackchannelAuthentication::class);
    $pending = $service->start('usr_01HZY', 'mcp:read mcp:write', 'Let the agent read your notes', BRAIN_MCP);

    expect($service->poll($pending)->granted())->toBeFalse()
        ->and($service->waitForToken($pending)->accessToken)->toBe('at_brain')
        ->and($pending->resource)->toBe(BRAIN_MCP);

    $ciba->assertStarted(loginHint: 'usr_01HZY', resource: BRAIN_MCP, bindingMessage: 'Let the agent read your notes', scope: 'mcp:read mcp:write');
    $ciba->assertPolled(3);
    Http::assertNothingSent();
});

it('fails assertStarted when no single request matches every argument', function (): void {
    $ciba = new FakeBackchannelAuthentication;
    $ciba->start('usr_1', 'mcp:read', 'One', BRAIN_MCP);

    expect(fn () => $ciba->assertStarted(loginHint: 'usr_1', bindingMessage: 'Two'))->toThrow(AssertionFailedError::class);
    expect(fn () => $ciba->assertNotStarted())->toThrow(AssertionFailedError::class);
});

it('scripts deny, expire and any other refusal', function (): void {
    $ciba = (new FakeBackchannelAuthentication)->deny()->expire()->failPoll(new InvalidTarget('No.', 'invalid_target'));
    $pending = $ciba->start('usr_1', 'mcp:read', 'x', BRAIN_MCP);

    expect(fn () => $ciba->poll($pending))->toThrow(AuthorizationDenied::class)
        ->and(fn () => $ciba->waitForToken($pending))->toThrow(AuthorizationExpired::class)
        ->and(fn () => $ciba->poll($pending))->toThrow(InvalidTarget::class);
});

it('never invents a grant when nothing is scripted', function (): void {
    $ciba = new FakeBackchannelAuthentication;
    $pending = $ciba->start('usr_1', 'mcp:read', 'x');

    expect($ciba->poll($pending)->granted())->toBeFalse()
        ->and(fn () => $ciba->waitForToken($pending, maxWait: 60))->toThrow(GrantTimedOut::class);
});

it('scripts a throttled start and still records it', function (): void {
    $ciba = (new FakeBackchannelAuthentication)->throttle(42);

    try {
        $ciba->start('usr_1', 'mcp:read', 'x');
        $this->fail('Expected GrantRateLimited');
    } catch (GrantRateLimited $e) {
        expect($e->retryAfter)->toBe(42);
    }

    $ciba->assertStarted(loginHint: 'usr_1');
});

it('scripts the device grant', function (): void {
    $device = CboxId::fake()->deviceAuthorization();
    $device->pending()->approve();

    $pending = app(DeviceAuthorization::class)->start('mcp:read mcp:write', BRAIN_MCP);

    expect($pending->userCode)->toBe(FakeDeviceAuthorization::USER_CODE)
        ->and($pending->verificationUri)->toBe('https://id.test/device')
        ->and(app(DeviceAuthorization::class)->waitForToken($pending)->accessToken)->toBe('fake-access-token');

    $device->assertStarted(resource: BRAIN_MCP, scope: 'mcp:read mcp:write');
    $device->assertPolled(2);
});

it('scripts a denied device sign-in', function (): void {
    $device = (new FakeDeviceAuthorization)->deny();

    $device->waitForToken($device->start());
})->throws(AuthorizationDenied::class);
