<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Enums;

/** Whether a customer API key still verifies. */
enum ApiKeyStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
