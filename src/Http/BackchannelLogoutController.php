<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\BackchannelLogout\LogoutTokenVerifier;
use Cbox\Id\Client\BackchannelLogout\SessionRegistry;
use Cbox\Id\Client\Events\BackchannelLogoutReceived;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\LogoutTokenRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The back-channel logout endpoint (OIDC Back-Channel Logout 1.0 §2.5).
 *
 * A server-to-server POST, form-encoded, with one field: `logout_token`. Mounted outside
 * the `web` group, so it carries no session and no CSRF check — the token's signature is
 * its authentication. Answers, all with `Cache-Control: no-store`:
 *
 * - 200 — the token was valid and the matching sessions are ended.
 * - 400 `invalid_request` — the token was refused; Cbox ID treats it as final.
 * - 503 — the issuer's keys could not be read; Cbox ID retries.
 */
class BackchannelLogoutController
{
    public function __construct(
        private readonly LogoutTokenVerifier $verifier,
        private readonly SessionRegistry $sessions,
    ) {}

    public function __invoke(Request $request): Response|JsonResponse
    {
        // The form body only, as §2.5 specifies — never a query string, where a token
        // would land in access logs.
        $token = $request->request->get('logout_token');

        try {
            $verified = $this->verifier->verify(is_string($token) ? $token : '');
        } catch (LogoutTokenRejected $e) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => $e->getMessage()], 400, ['Cache-Control' => 'no-store']);
        } catch (ClientConfigurationException) {
            return new JsonResponse(['error' => 'temporarily_unavailable'], 503, ['Cache-Control' => 'no-store', 'Retry-After' => '10']);
        }

        event(new BackchannelLogoutReceived($verified, $this->sessions->end($verified)));

        return new Response('', 200, ['Cache-Control' => 'no-store']);
    }
}
