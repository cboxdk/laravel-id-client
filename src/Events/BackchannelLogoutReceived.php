<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Events;

use Cbox\Id\Client\BackchannelLogout\EndedSessions;
use Cbox\Id\Client\BackchannelLogout\LogoutToken;

/**
 * Cbox ID told this application that a person signed out — dispatched after the token
 * was validated and the matching local sessions were ended.
 *
 *     Event::listen(BackchannelLogoutReceived::class, function ($event) {
 *         ApiTokens::revokeForSubject($event->token->subject);
 *     });
 *
 * Listen for anything else a sign-out should end: API tokens you minted, websocket
 * connections, cached per-user data.
 */
class BackchannelLogoutReceived
{
    public function __construct(
        public readonly LogoutToken $token,
        public readonly EndedSessions $ended,
    ) {}
}
