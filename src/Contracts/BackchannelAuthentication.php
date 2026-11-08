<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\Exceptions\GrantFailed;
use Cbox\Id\Client\ValueObjects\PendingBackchannelAuthentication;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;

/**
 * OpenID CIBA in poll mode: ask a known person to approve a request on their own
 * device, then collect the tokens.
 *
 * Every failure is a {@see GrantFailed}; see the docs for which subclass maps to which
 * OAuth error.
 */
interface BackchannelAuthentication
{
    /**
     * Start a backchannel request.
     *
     * @param  string  $loginHint  the person: a Cbox ID user id (`usr_…`) or a confirmed email
     * @param  string  $bindingMessage  shown to the person beside Approve (at most 255 characters)
     * @param  string|null  $resource  the API the token is for (RFC 8707); required for self-registered clients
     * @param  int|null  $requestedExpiry  seconds the request may stay open
     */
    public function start(
        string $loginHint,
        string $scope,
        string $bindingMessage,
        ?string $resource = null,
        ?int $requestedExpiry = null,
        ?string $nonce = null,
    ): PendingBackchannelAuthentication;

    /**
     * Poll the token endpoint once. `$interval` is the interval in use, so a `slow_down`
     * answer can raise it; it defaults to the request's own.
     */
    public function poll(PendingBackchannelAuthentication $request, ?int $interval = null): PollResult;

    /**
     * Poll until the person answers, the request expires, or `$maxWait` seconds pass.
     * Blocks the calling process: use it in a CLI or a queued job, not a web request.
     */
    public function waitForToken(PendingBackchannelAuthentication $request, ?int $maxWait = null): TokenResult;
}
