<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\Contracts\HasFeatureFlags;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires Cbox ID feature flags to be on for the caller — all of them.
 *
 *     Route::middleware(['auth', 'feature:new-dashboard'])->get(…);
 *     Route::middleware(['cbox-id.token', 'cbox-id.feature:billing.v2,acme-beta'])->get(…);
 *
 * Read from the `feature_flags` claim of whoever is acting — the session's identity, a
 * verified bearer token — so the app must request the `feature_flags` scope. Without the
 * claim every feature is off: a forgotten scope closes a route, it never opens one. A
 * customer API key carries no flags.
 *
 * A flag that is off is a 404: the route is not there for this person yet, and saying
 * "forbidden" would tell them it exists. Naming no flag is a configuration error.
 */
class RequireFeature
{
    public function __construct(private readonly CurrentPrincipal $principals) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $required = array_values(array_filter($features, static fn (string $f): bool => $f !== ''));

        if ($required === []) {
            throw ClientConfigurationException::because('cbox-id.feature names no feature flag; write cbox-id.feature:flag-key.');
        }

        $principal = $this->principals->resolve($request->user(), $request);

        if ($principal === null) {
            if ($request->expectsJson()) {
                return ErrorResponse::json('unauthenticated', 'Sign in first.', 401);
            }

            throw new AuthenticationException;
        }

        foreach ($required as $feature) {
            if (! $principal instanceof HasFeatureFlags || ! $principal->hasFeature($feature)) {
                return $request->expectsJson()
                    ? ErrorResponse::json('not_found', 'Not found.', 404)
                    : abort(404);
            }
        }

        return ErrorResponse::from($next($request));
    }
}
