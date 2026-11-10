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
 * The slug is resolved in the app's local {@see OrganizationDirectory}. When the person's
 * session is bound to another organization, the middleware re-binds it through
 * {@see IdentityClient::switchOrganization()} and comes back to the same URL afterwards
 * (the app's callback ends with `redirect()->intended()`). With a live Cbox ID session that
 * round trip shows no prompt. There is no session-only organization state and no 404 on a
 * mismatch.
 *
 * A slug the directory does not know may just be news to it (the person joined that
 * organization after signing in). A page load then refreshes the sign-in once, still bound
 * to the current organization, and comes back; only if the slug is still unknown is the
 * answer a 404.
 *
 * Only a page load (GET or HEAD) makes a round trip. An Inertia visit gets `409` with
 * `X-Inertia-Location`, so the browser makes it as a full page load. Any other request (a
 * JSON call, or a POST from a stale tab) gets `409 {error: "organization_rebind",
 * location}`, where `location` is the page to load in full (this URL for a GET, else the
 * page the request came from), and the session is left alone: nothing is replayed as a GET.
 *
 * A support session is pinned to its organization and gets a 403. If Cbox ID comes back
 * still bound elsewhere within a minute, the answer is a 403 rather than a loop.
 */
class BindOrganizationFromUrl
{
    public const ATTEMPT_KEY = 'cbox-id-client.org-url.rebind';

    /** The last refresh for an unknown slug: `{slug, at}`. */
    public const REFRESH_KEY = 'cbox-id-client.org-url.refresh';

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

        if (! $organization instanceof MirroredOrganization) {
            return $this->unknown($request, $principal, $slug);
        }

        if ($principal->organization()?->id === $organization->id) {
            if ($request->hasSession()) {
                $request->session()->forget([self::ATTEMPT_KEY, self::REFRESH_KEY]);
            }

            return $next($request);
        }

        if ($principal->isSupportSession() || ! $request->hasSession() || $this->attempted($request, self::ATTEMPT_KEY, 'organization', $organization->id)) {
            return $request->expectsJson()
                ? ErrorResponse::json('organization_mismatch', 'This session acts for another organization.', 403)
                : abort(403, 'This session acts for another organization.');
        }

        return $this->roundTrip($request, self::ATTEMPT_KEY, ['organization' => $organization->id],
            fn (): string => $this->identity->switchOrganization($organization->id)->getTargetUrl());
    }

    /** A slug the directory does not know: refresh the sign-in once, else 404. */
    private function unknown(Request $request, Principal $principal, string $slug): Response
    {
        if ($principal->isSupportSession() || ! $request->hasSession() || ! self::isPageLoad($request)
            || $this->attempted($request, self::REFRESH_KEY, 'slug', $slug)) {
            abort(404);
        }

        $bound = $principal->organization()?->id;

        return $this->roundTrip($request, self::REFRESH_KEY, ['slug' => $slug],
            fn (): string => $this->identity->redirect(organization: $bound)->getTargetUrl());
    }

    /**
     * Send the person to Cbox ID and back to this URL. Only a page load writes the session;
     * any other request is told where to go and nothing more.
     *
     * @param  array<string, string>  $mark
     * @param  Closure(): string  $location
     */
    private function roundTrip(Request $request, string $key, array $mark, Closure $location): Response
    {
        if (! self::isPageLoad($request)) {
            // Where a full page load makes the round trip: this URL for a GET, else the page
            // the request came from (a POST URL loaded as a GET would be a 405).
            $page = $request->isMethod('GET') || $request->isMethod('HEAD') ? $request->fullUrl() : url()->previous();

            return response()->json(['error' => 'organization_rebind', 'location' => $page], 409);
        }

        $request->session()->put($key, [...$mark, 'at' => Date::now()->getTimestamp()]);
        $request->session()->put('url.intended', $request->fullUrl());

        $target = $location();

        return $request->hasHeader('X-Inertia')
            ? response('', 409, ['X-Inertia-Location' => $target])
            : redirect()->away($target);
    }

    /** A GET or HEAD the browser navigates (an Inertia visit counts; a JSON call does not). */
    private static function isPageLoad(Request $request): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && ($request->hasHeader('X-Inertia') || ! $request->expectsJson());
    }

    private function attempted(Request $request, string $key, string $field, string $value): bool
    {
        $attempt = $request->session()->get($key);

        return is_array($attempt) && ($attempt[$field] ?? null) === $value
            && is_int($attempt['at'] ?? null) && $attempt['at'] > Date::now()->getTimestamp() - self::ATTEMPT_WINDOW;
    }
}
