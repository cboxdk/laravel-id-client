<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\ValueObjects\ServiceConnection;

/** First-party machine provisioning; never substitutes for a person's connect authorization. */
interface ProvisionsServiceConnections
{
    public function provision(string $organization, string $service, string $audience): ServiceConnection;
}
