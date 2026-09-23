<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\BackchannelLogout\SessionRegistry;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The browser half of back-channel logout. Added to the `web` group when
 * `backchannel_logout.enabled` is on; alias `cbox-id.session` to place it yourself.
 *
 * On the way in: if Cbox ID has ended the session this browser signed in with, log the
 * local user out and invalidate the session, then carry on as a guest — your `auth`
 * middleware decides what a guest sees. On the way out: index the session under its
 * `sid` and `sub`, AFTER the handler ran, because logging in regenerates the session id
 * and an index of the old one would point at nothing.
 */
class EnforceBackchannelLogout
{
    public const INDEXED_KEY = 'cbox-id-client.indexed-session';

    public function __construct(
        private readonly SessionIdentityStore $identities,
        private readonly SessionRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return ErrorResponse::from($next($request));
        }

        $identity = $this->identities->identity();

        if ($identity !== null && $this->registry->isRevoked($identity, $this->identities->rememberedAt())) {
            $this->identities->forget();

            $guard = app(AuthFactory::class)->guard();

            if ($guard instanceof StatefulGuard && $guard->check()) {
                // Also forgets this browser's remember-me cookie.
                $guard->logout();
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $response = ErrorResponse::from($next($request));

        $identity = $this->identities->identity();
        $session = $request->session();

        if ($identity !== null && $session->get(self::INDEXED_KEY) !== $session->getId()) {
            $this->registry->record($identity, $session->getId(), $this->identities->boundTo(), $this->identities->rememberedAt());
            $session->put(self::INDEXED_KEY, $session->getId());
        }

        return $response;
    }
}
