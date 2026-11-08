<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Contracts\BackchannelAuthentication;
use Cbox\Id\Client\ValueObjects\PendingBackchannelAuthentication;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;
use PHPUnit\Framework\Assert;

/**
 * CIBA for your test suite — no HTTP, no waiting.
 *
 *     $ciba = CboxId::fake()->backchannelAuthentication();
 *     $ciba->pending(2)->approve();      // or ->deny(), ->expire(), ->throttle(30)
 *
 *     // … run the code that asks for approval …
 *
 *     $ciba->assertStarted(loginHint: 'usr_1', resource: 'https://app.cboxbrain.com/mcp');
 */
class FakeBackchannelAuthentication implements BackchannelAuthentication
{
    use ScriptsGrantOutcomes;

    /** @var list<array{login_hint: string, scope: string, binding_message: string, resource: ?string, requested_expiry: ?int, nonce: ?string}> */
    public array $started = [];

    public function __construct(private readonly int $now = 1_700_000_000) {}

    public function start(
        string $loginHint,
        string $scope,
        string $bindingMessage,
        ?string $resource = null,
        ?int $requestedExpiry = null,
        ?string $nonce = null,
    ): PendingBackchannelAuthentication {
        $this->started[] = [
            'login_hint' => $loginHint,
            'scope' => $scope,
            'binding_message' => $bindingMessage,
            'resource' => $resource,
            'requested_expiry' => $requestedExpiry,
            'nonce' => $nonce,
        ];
        $this->throwIfStartFails();

        $expiresIn = $requestedExpiry ?? 120;

        return new PendingBackchannelAuthentication('areq_fake_'.count($this->started), $expiresIn, 5, $this->now + $expiresIn, $resource);
    }

    public function poll(PendingBackchannelAuthentication $request, ?int $interval = null): PollResult
    {
        return $this->nextPoll($interval ?? $request->interval);
    }

    public function waitForToken(PendingBackchannelAuthentication $request, ?int $maxWait = null): TokenResult
    {
        return $this->waitScripted($request->interval, $maxWait);
    }

    /** Assert a request was started; every argument given must match the same request. */
    public function assertStarted(?string $loginHint = null, ?string $resource = null, ?string $bindingMessage = null, ?string $scope = null): void
    {
        $matching = array_filter($this->started, static fn (array $request): bool => ($loginHint === null || $request['login_hint'] === $loginHint)
            && ($resource === null || $request['resource'] === $resource)
            && ($bindingMessage === null || $request['binding_message'] === $bindingMessage)
            && ($scope === null || $request['scope'] === $scope));

        Assert::assertNotEmpty($matching, 'Expected a backchannel request to be started as described; none was.');
    }

    public function assertNotStarted(): void
    {
        Assert::assertSame([], $this->started, 'A backchannel request was started unexpectedly.');
    }
}
