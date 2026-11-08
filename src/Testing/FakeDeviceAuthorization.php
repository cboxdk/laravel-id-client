<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Contracts\DeviceAuthorization;
use Cbox\Id\Client\ValueObjects\PendingDeviceAuthorization;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;
use PHPUnit\Framework\Assert;

/**
 * The device grant for your test suite — no HTTP, no waiting.
 *
 *     $device = CboxId::fake()->deviceAuthorization();
 *     $device->pending()->approve(new TokenResult('at', 'Bearer', 900, refreshToken: 'rt'));
 *
 *     $this->artisan('mycli:login')->expectsOutputToContain('FAKE-CODE');
 *
 *     $device->assertStarted(resource: 'https://app.cboxbrain.com/mcp');
 */
class FakeDeviceAuthorization implements DeviceAuthorization
{
    use ScriptsGrantOutcomes;

    public const string USER_CODE = 'FAKE-CODE';

    /** @var list<array{scope: string, resource: ?string}> */
    public array $started = [];

    public function __construct(
        private readonly string $issuer = CboxIdFake::ISSUER,
        private readonly int $now = 1_700_000_000,
    ) {}

    public function start(string $scope = '', ?string $resource = null): PendingDeviceAuthorization
    {
        $this->started[] = ['scope' => $scope, 'resource' => $resource];
        $this->throwIfStartFails();

        $uri = rtrim($this->issuer, '/').'/device';

        return new PendingDeviceAuthorization(
            'dc_fake_'.count($this->started),
            self::USER_CODE,
            $uri,
            $uri.'?user_code='.self::USER_CODE,
            600,
            5,
            $this->now + 600,
            $resource,
        );
    }

    public function poll(PendingDeviceAuthorization $authorization, ?int $interval = null): PollResult
    {
        return $this->nextPoll($interval ?? $authorization->interval);
    }

    public function waitForToken(PendingDeviceAuthorization $authorization, ?int $maxWait = null): TokenResult
    {
        return $this->waitScripted($authorization->interval, $maxWait);
    }

    /** Assert a device authorization was started; every argument given must match the same one. */
    public function assertStarted(?string $resource = null, ?string $scope = null): void
    {
        $matching = array_filter($this->started, static fn (array $request): bool => ($resource === null || $request['resource'] === $resource)
            && ($scope === null || $request['scope'] === $scope));

        Assert::assertNotEmpty($matching, 'Expected a device authorization to be started as described; none was.');
    }

    public function assertNotStarted(): void
    {
        Assert::assertSame([], $this->started, 'A device authorization was started unexpectedly.');
    }
}
