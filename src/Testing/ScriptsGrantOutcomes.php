<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Exceptions\AuthorizationDenied;
use Cbox\Id\Client\Exceptions\AuthorizationExpired;
use Cbox\Id\Client\Exceptions\GrantFailed;
use Cbox\Id\Client\Exceptions\GrantRateLimited;
use Cbox\Id\Client\Exceptions\GrantTimedOut;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;
use PHPUnit\Framework\Assert;

/**
 * The scripted answers shared by the CIBA and device-grant fakes.
 *
 * Each poll takes the next scripted outcome: still pending, approved with tokens, or a
 * refusal. With nothing scripted a poll stays pending and `waitForToken()` times out — an
 * unconfigured fake never invents a grant.
 */
trait ScriptsGrantOutcomes
{
    /** @var list<TokenResult|GrantFailed|null> null is "still pending" */
    private array $outcomes = [];

    private ?GrantFailed $startFailure = null;

    private int $polls = 0;

    /** The person has not answered yet, for the next `$polls` polls. */
    public function pending(int $polls = 1): static
    {
        for ($i = 0; $i < $polls; $i++) {
            $this->outcomes[] = null;
        }

        return $this;
    }

    /** The person approves; the next poll returns these tokens. */
    public function approve(?TokenResult $tokens = null): static
    {
        $this->outcomes[] = $tokens ?? new TokenResult('fake-access-token', 'Bearer', 900);

        return $this;
    }

    /** The person declines: the next poll throws `AuthorizationDenied`. */
    public function deny(): static
    {
        $this->outcomes[] = new AuthorizationDenied('The person declined the request.', 'access_denied', null, 400);

        return $this;
    }

    /** The request runs out: the next poll throws `AuthorizationExpired`. */
    public function expire(): static
    {
        $this->outcomes[] = new AuthorizationExpired('The request expired before the person answered.', 'expired_token', null, 400);

        return $this;
    }

    /** The next poll throws this refusal (`InvalidTarget`, `InvalidGrant`, …). */
    public function failPoll(GrantFailed $failure): static
    {
        $this->outcomes[] = $failure;

        return $this;
    }

    /** Every `start()` throws this refusal until cleared with null. */
    public function failStart(?GrantFailed $failure): static
    {
        $this->startFailure = $failure;

        return $this;
    }

    /** Every `start()` is rate limited with this `Retry-After`. */
    public function throttle(int $retryAfter = 30): static
    {
        return $this->failStart(new GrantRateLimited('Cbox ID is rate limiting new requests for this person or client.', $retryAfter));
    }

    public function assertPolled(?int $times = null): void
    {
        if ($times === null) {
            Assert::assertGreaterThan(0, $this->polls, 'Expected the grant to be polled; it was not.');

            return;
        }

        Assert::assertSame($times, $this->polls, "Expected the grant to be polled {$times} time(s); it was polled {$this->polls}.");
    }

    private function throwIfStartFails(): void
    {
        if ($this->startFailure !== null) {
            throw $this->startFailure;
        }
    }

    private function nextPoll(int $interval): PollResult
    {
        $this->polls++;
        $outcome = array_shift($this->outcomes);

        if ($outcome instanceof GrantFailed) {
            throw $outcome;
        }

        return new PollResult($outcome, $interval);
    }

    private function waitScripted(int $interval, ?int $maxWait): TokenResult
    {
        while ($this->outcomes !== []) {
            $result = $this->nextPoll($interval);

            if ($result->tokens !== null) {
                return $result->tokens;
            }
        }

        throw new GrantTimedOut('Nobody answered within '.($maxWait ?? 0).' seconds (nothing more was scripted on the fake).', 'authorization_pending');
    }
}
