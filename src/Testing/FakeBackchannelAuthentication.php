<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Contracts\OrganizationBoundBackchannelAuthentication;
use Cbox\Id\Client\Exceptions\GrantFailed;
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
class FakeBackchannelAuthentication implements OrganizationBoundBackchannelAuthentication
{
    use ScriptsGrantOutcomes;

    /** @var list<array{login_hint: string, scope: string, binding_message: string, resource: ?string, requested_expiry: ?int, nonce: ?string, client_notification_token: ?string, organization_id?: string}> */
    public array $started = [];

    public function __construct(private readonly int $now = 1_700_000_000) {}

    public function start(
        string $loginHint,
        string $scope,
        string $bindingMessage,
        ?string $resource = null,
        ?int $requestedExpiry = null,
        ?string $nonce = null,
        ?string $clientNotificationToken = null,
    ): PendingBackchannelAuthentication {
        return $this->initiate($loginHint, $scope, $bindingMessage, $resource, $requestedExpiry, $nonce, $clientNotificationToken);
    }

    private function initiate(
        string $loginHint,
        string $scope,
        string $bindingMessage,
        ?string $resource,
        ?int $requestedExpiry,
        ?string $nonce,
        ?string $clientNotificationToken,
        ?string $organizationId = null,
    ): PendingBackchannelAuthentication {
        $this->started[] = [
            'login_hint' => $loginHint,
            'scope' => $scope,
            'binding_message' => $bindingMessage,
            'resource' => $resource,
            'requested_expiry' => $requestedExpiry,
            'nonce' => $nonce,
            'client_notification_token' => $clientNotificationToken,
            ...($organizationId === null ? [] : ['organization_id' => $organizationId]),
        ];
        $this->throwIfStartFails();

        $expiresIn = $requestedExpiry ?? 120;

        return new PendingBackchannelAuthentication('areq_fake_'.count($this->started), $expiresIn, 5, $this->now + $expiresIn, $resource, $organizationId);
    }

    public function startForOrganization(
        string $organizationId,
        string $loginHint,
        string $scope,
        string $bindingMessage,
        ?string $resource = null,
        ?int $requestedExpiry = null,
        ?string $nonce = null,
        ?string $clientNotificationToken = null,
    ): PendingBackchannelAuthentication {
        if (preg_match('/\A(?:org_)?([0-7][0-9a-hjkmnp-tv-z]{25})\z/i', $organizationId, $match) !== 1) {
            throw new GrantFailed('Organization binding requires an organization ULID.', 'invalid_request');
        }

        return $this->initiate($loginHint, $scope, $bindingMessage, $resource, $requestedExpiry, $nonce, $clientNotificationToken, strtolower($match[1]));
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
