<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\Exceptions\GrantFailed;
use Cbox\Id\Client\ValueObjects\PendingDeviceAuthorization;
use Cbox\Id\Client\ValueObjects\PollResult;
use Cbox\Id\Client\ValueObjects\TokenResult;

/**
 * The RFC 8628 device authorization grant: show a code, let the person sign in on
 * another device, then collect the tokens.
 *
 * Every failure is a {@see GrantFailed}.
 */
interface DeviceAuthorization
{
    /** @param  string|null  $resource  the API the token is for (RFC 8707) */
    public function start(string $scope = '', ?string $resource = null): PendingDeviceAuthorization;

    /** Poll the token endpoint once; see {@see BackchannelAuthentication::poll()}. */
    public function poll(PendingDeviceAuthorization $authorization, ?int $interval = null): PollResult;

    /** Poll until the person answers, the code expires, or `$maxWait` seconds pass. */
    public function waitForToken(PendingDeviceAuthorization $authorization, ?int $maxWait = null): TokenResult;
}
