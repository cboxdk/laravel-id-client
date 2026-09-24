<?php

declare(strict_types=1);

namespace Cbox\Id\Client\BackchannelLogout;

use Cbox\Id\Client\Http\EnforceBackchannelLogout;

/**
 * What a logout token ended locally: the Laravel session ids destroyed in the session
 * store, and the local user ids those sessions were logged in as (for your own cleanup —
 * tokens you hold for them, websockets to close).
 *
 * Sessions the index did not know about are not listed, and still end: the next request
 * that presents one is signed out by {@see EnforceBackchannelLogout}.
 */
readonly class EndedSessions
{
    /**
     * @param  list<string>  $sessionIds
     * @param  list<string>  $localUserIds
     */
    public function __construct(
        public array $sessionIds = [],
        public array $localUserIds = [],
    ) {}
}
