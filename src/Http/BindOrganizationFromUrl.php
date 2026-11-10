<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\Contracts\OrganizationDirectory;
use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Shell\MirroredOrganization;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * The URL is the organization (`/orgs/{org}/…`); the token follows it. Aliased
 * `cbox-id.org-url`, for the routes under `/orgs/{org}`:
 *
 *     Route::middleware(['web', 'auth', 'cbox-id.org-url'])->prefix('orgs/{org}')->group(…);
 *     Route::middleware('cbox-id.org-url:team')->…   // another route parameter name
 *
 * The slug is resolved in the app's local {@see OrganizationDirectory}: an organization the
 * person does not belong to is a 404. When the person's session is bound to another
 * organization, the middleware re-binds it through {@see IdentityClient::switchOrganization()}
 * and comes back to the same URL afterwards (the app's callback ends with
 * `redirect()->intended()`). With a live Cbox ID session that round trip shows no prompt.
 * There is no session-only organization state and no 404 on a mismatch.
 *
 * An Inertia visit gets `409` with `X-Inertia-Location`, so the browser makes the round
 * trip as a full page load; a JSON request gets `409 {error: "organization_rebind",
 * location}`. A support session is pinned to its organization and gets a 403. If Cbox ID
 * comes back still bound elsewhere within a minute, the answer is a 403 rather than a loop.
 */
class BindOrganizationFromUrl
{
    public const ATTEMPT_KEY = 'cbox-id-client.org-url.rebind';

    /** How long a re-bind attempt for one organization blocks another. */
    public const ATTEMPT_WINDOW = 60;

    public function __construct(
        private readonly OrganizationDirectory $directory,
        private readonly CurrentPrincipal $principals,
        private readonly IdentityClient $identity,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $parameter = 'org'): Response
    {
        $route = $request->route();
        $slug = $route instanceof Route ? $route->originalParameter($parameter) : null;
        $principal = $this->principals->resolve($request->user(), $request);

        if (! is_string($slug) || $slug === '' || ! $principal instanceof Principal) {
            return $next($request);
        }

        $organization = $this->directory->findBySlug($request, $slug);

        abort_unless($organization instanceof MirroredOrganization, 404);

        if ($principal->organization()?->id === $organization->id) {
            if ($request->hasSession() && $request->session()->has(self::ATTEMPT_KEY)) {
                $request->session()->forget(self::ATTEMPT_KEY);
            }

            return $next($request);
        }

        if ($principal->isSupportSession() || ! $request->hasSession() || $this->attemptedRecently($request, $organization->id)) {
            return $request->expectsJson()
                ? ErrorResponse::json('organization_mismatch', 'This session acts for another organization.', 403)
                : abort(403, 'This session acts for another organization.');
        }

        $request->session()->put(self::ATTEMPT_KEY, ['organization' => $organization->id, 'at' => Date::now()->getTimestamp()]);
        $request->session()->put('url.intended', $request->fullUrl());

        $location = $this->identity->switchOrganization($organization->id)->getTargetUrl();

        if ($request->hasHeader('X-Inertia')) {
            return response('', 409, ['X-Inertia-Location' => $location]);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'organization_rebind', 'location' => $location], 409);
        }

        return redirect()->away($location);
    }

    private function attemptedRecently(Request $request, string $organizationId): bool
    {
        $attempt = $request->session()->get(self::ATTEMPT_KEY);

        return is_array($attempt) && ($attempt['organization'] ?? null) === $organizationId
            && is_int($attempt['at'] ?? null) && $attempt['at'] > Date::now()->getTimestamp() - self::ATTEMPT_WINDOW;
    }
}
