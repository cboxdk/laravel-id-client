<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog\Http;

use Cbox\Id\Client\AppCatalog\AppCatalog;
use Cbox\Id\Client\AppCatalog\AppCatalogContext;
use Cbox\Id\Client\AppCatalog\AppCatalogStore;
use Cbox\Id\Client\AppCatalog\CatalogUnavailable;
use Cbox\Id\Client\AppCatalog\SignInRequired;
use Cbox\Id\Client\Contracts\ResolvesAppCatalogContext;
use Cbox\Id\Client\Exceptions\CboxIdException;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\ResourceAuthorizationClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app switcher's same-origin endpoints. Thin: {@see AppCatalog} does the work.
 *
 *  - catalog   JSON `{apps, revision, refresh_after}`; 401 `{signin_url}` when only a
 *              sign-in can help (the page navigates there by itself); 503
 *              `{error: catalog_unavailable}` when Cbox ID cannot answer right now.
 *  - authorize / callback  the browser flow for a catalog grant, kept for pages that
 *              still start it.
 *  - signin    starts this app's own sign-in and comes back to `return_to`.
 */
final readonly class AppCatalogController
{
    /** When this session last started a sign-in for the catalog. Outlives the catalog's own keys. */
    public const SIGNIN_SENT_KEY = 'cbox-id-client.app-catalog-signin-sent';

    public function __construct(
        private ResolvesAppCatalogContext $contexts,
        private AppCatalog $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->contexts->resolve($request);

        if (! $context instanceof AppCatalogContext) {
            return self::json(['error' => 'forbidden'], 403);
        }

        $session = $request->session();

        try {
            return self::json($this->catalog->read($session, $context));
        } catch (SignInRequired $e) {
            // The page is sent to sign in at most once per five minutes. The mark is set
            // when a sign-in actually starts (`/apps/signin`, or a completed sign-in),
            // never by this answer: a 401 the page did not follow must not block the next
            // one. Still no grant that soon after a sign-in means the sign-in does not
            // produce one (a client not allowed token exchange, say), and sending the page
            // again would be a redirect loop.
            $last = $session->get(self::SIGNIN_SENT_KEY);

            if (is_int($last) && $last > time() - 300) {
                Log::warning('Cbox ID app catalog: still no grant right after a sign-in.', ['reason' => $e->getMessage()]);

                return self::json(['error' => 'catalog_unavailable'], 503);
            }

            return self::json(['signin_url' => self::signinUrl($request, $context)], 401);
        } catch (CatalogUnavailable) {
            return self::json(['error' => 'catalog_unavailable'], 503);
        }
    }

    public function authorize(Request $request, ResourceAuthorizationClient $authorization, AppCatalogStore $store): Response
    {
        $context = $this->contexts->resolve($request);

        abort_unless($context instanceof AppCatalogContext, 403);

        try {
            return $authorization->redirect(
                $store->resource(), $context->organizationId, $context->subject,
                route('cbox-id.apps.callback'), [AppCatalogStore::SCOPE],
                ['return_to' => self::returnPath($request->query('return_to'))], dpop: true,
            );
        } catch (CboxIdException) {
            return redirect(self::signinUrl($request, $context));
        }
    }

    public function callback(Request $request, ResourceAuthorizationClient $authorization, AppCatalogStore $store): RedirectResponse
    {
        try {
            $grant = $authorization->authenticate($request);
        } catch (CboxIdException) {
            // No grant is kept; the catalog renews or asks for a sign-in on its own.
            return redirect('/');
        }

        $context = $this->contexts->resolve($request);
        $returnTo = self::returnPath($grant->context['return_to'] ?? null);

        if ($context instanceof AppCatalogContext && $context->subject === $grant->subject
            && $grant->scopes === [AppCatalogStore::SCOPE] && $grant->resource === $store->resource()) {
            $store->rememberGrant($request->session(), $grant);
        }

        return redirect($returnTo);
    }

    public function signin(Request $request, IdentityClient $identity): RedirectResponse
    {
        $organization = $request->query('organization');
        $request->session()->put('url.intended', url(self::returnPath($request->query('return_to'))));
        $request->session()->put(self::SIGNIN_SENT_KEY, time());

        return $identity->redirect(organization: self::organization($organization));
    }

    /** Where to send the page for a new sign-in: the app's own launch, or the SDK's. */
    public static function signinUrl(Request $request, ?AppCatalogContext $context): string
    {
        $returnTo = $request->query('return_to');

        if (! is_string($returnTo) || self::returnPath($returnTo) !== $returnTo) {
            $returnTo = self::refererPath($request);
        }

        $parameters = array_filter([
            'return_to' => $returnTo,
            'organization' => $context?->organizationId,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        $route = config('cbox-id-client.app_catalog.signin_route');

        if (is_string($route) && $route !== '' && Route::has($route)) {
            return route($route, $parameters, false);
        }

        return route('cbox-id.apps.signin', $parameters, false);
    }

    /** A same-origin path, or `/`. Never a scheme, a host, `//` or a backslash. */
    public static function returnPath(mixed $path): string
    {
        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            && preg_match('/[\\\\\x00-\x20\x7F]/', $path) !== 1 && strlen($path) <= 2048 ? $path : '/';
    }

    private static function refererPath(Request $request): string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return '/';
        }

        $parts = parse_url($referer);

        $scheme = $parts['scheme'] ?? null;

        if (! is_array($parts) || ($parts['host'] ?? null) !== $request->getHost() || $scheme !== $request->getScheme()
            || ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)) !== (int) $request->getPort()) {
            return '/';
        }

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return self::returnPath($path);
    }

    private static function organization(mixed $organization): ?string
    {
        return is_string($organization) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $organization) === 1 ? $organization : null;
    }

    /** @param array<string, mixed> $body */
    private static function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status)->header('Cache-Control', 'private, no-store');
    }
}
