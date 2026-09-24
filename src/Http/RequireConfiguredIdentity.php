<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\Exceptions\NotConfigured;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse legibly when this deployment has no identity provider configured.
 *
 *     Route::middleware('cbox-id.configured')->group(…);                       // issuer
 *     Route::middleware('cbox-id.configured:issuer,client_id,redirect')->…     // login routes
 *
 * Without it an unconfigured deployment surfaced as a 500 on the first call that needed
 * the issuer, and nothing about that said *a deployment is missing an environment
 * variable*. Two applications wrote this middleware for themselves; this is it once.
 *
 * **503, not 500**: the service is not broken, it is not finished being set up, and a
 * caller retrying later is doing the right thing. JSON callers get the SDK's error
 * envelope; everyone else gets your application's own 503 page. The response never
 * echoes a value — only that one is missing.
 *
 * It opens no door. An unconfigured deployment still refuses every request behind it; this
 * only changes what the refusal says.
 */
class RequireConfiguredIdentity
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        foreach ($keys === [] ? ['issuer'] : $keys as $key) {
            $value = config('cbox-id-client.'.$key);

            if (! is_string($value) || trim($value) === '') {
                // Thrown rather than answered here, so the application's exception
                // handler sees (and reports) it the same way it would anywhere else.
                throw NotConfigured::key($key);
            }
        }

        return ErrorResponse::from($next($request));
    }
}
