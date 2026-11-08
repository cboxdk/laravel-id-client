<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * A started device authorization (RFC 8628 §3.2).
 *
 * Show the person `userCode` and `verificationUri` (or `verificationUriComplete`, which
 * already carries the code — good for a QR code), then poll with the `deviceCode`. The
 * device code is a credential for this flow: keep it on the device, never display it.
 */
readonly class PendingDeviceAuthorization
{
    public function __construct(
        #[\SensitiveParameter] public string $deviceCode = '',
        public string $userCode = '',
        public string $verificationUri = '',
        public ?string $verificationUriComplete = null,
        public int $expiresIn = 0,
        public int $interval = 5,
        public int $expiresAt = 0,
        public ?string $resource = null,
    ) {}
}
