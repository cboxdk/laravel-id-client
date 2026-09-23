<?php

declare(strict_types=1);

namespace Cbox\Id\Client\BackchannelLogout;

/**
 * A logout token that passed every check in {@see LogoutTokenVerifier}.
 *
 * Only ever built by the verifier, so holding one is the proof. `sid` names one Cbox ID
 * session (the `sid` of the ID Token it signed you in with); with only `subject`, every
 * session of that person ends.
 */
readonly class LogoutToken
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public string $issuer,
        public ?string $subject,
        public ?string $sid,
        public string $jti,
        public int $issuedAt,
        public int $expiresAt,
        public array $claims = [],
    ) {}

    /** True when the token names one session rather than the whole person. */
    public function endsOneSession(): bool
    {
        return $this->sid !== null;
    }
}
