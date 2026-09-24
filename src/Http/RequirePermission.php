<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires Cbox ID permissions on the route — all of them.
 *
 *     Route::middleware(['auth', 'cbox-id.permission:invoices:create'])->post(…);
 *     Route::middleware(['cbox-id.token', 'cbox-id.permission:invoices:read,invoices:export'])->get(…);
 *
 * Works for every kind of principal: a signed-in session, a verified bearer token, a
 * customer API key. The permission is the manifest key (`feature:action`), matched
 * exactly as Cbox ID resolved it for the caller's organization.
 *
 * Naming no permission is a configuration error, not a pass: a guard that requires
 * nothing is how a route ends up open while looking closed.
 */
class RequirePermission
{
    public function __construct(private readonly CurrentPrincipal $principals) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $required = array_values(array_filter($permissions, static fn (string $p): bool => $p !== ''));

        if ($required === []) {
            throw ClientConfigurationException::because('cbox-id.permission names no permission; write cbox-id.permission:feature:action.');
        }

        $principal = $this->principals->resolve($request->user(), $request);

        if ($principal === null) {
            if ($request->expectsJson()) {
                return ErrorResponse::json('unauthenticated', 'Sign in first.', 401);
            }

            throw new AuthenticationException;
        }

        $missing = array_values(array_filter($required, static fn (string $p): bool => ! $principal->hasPermission($p)));

        if ($missing !== []) {
            $detail = 'Missing permission(s): '.implode(', ', $missing);

            return $request->expectsJson()
                ? ErrorResponse::json('insufficient_permission', $detail, 403, ['required' => $required])
                : abort(403, $detail);
        }

        return ErrorResponse::from($next($request));
    }
}
