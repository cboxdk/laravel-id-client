<?php

declare(strict_types=1);

namespace Cbox\Id\Client\UserProperties;

use Closure;

/** A user or machine token granted access to the explicit namespace by ID. */
readonly class PropertyToken
{
    /** @param (Closure(string, string, string): string)|null $proof Optional DPoP signer: method, URL, access token. */
    public function __construct(public string $accessToken, public ?Closure $proof = null) {}
}
