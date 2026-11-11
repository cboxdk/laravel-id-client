<?php

declare(strict_types=1);

namespace Cbox\Id\Client\SignIn\Http;

use Cbox\Id\Client\AppCatalog\Http\AppCatalogController;
use Cbox\Id\Client\Enums\Prompt;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\SignIn\SignInRoundTrips;
use Cbox\Id\Client\SignIn\SignInRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Redirect mode's entry points ({@see SignInRoutes}). Nothing here draws a sign-in form:
 * each one answers with a redirect to Cbox ID's authorize endpoint (authorization code
 * + PKCE, `state` and `nonce`, through {@see IdentityClient::redirect()}), and the
 * person comes back to the app's own callback.
 *
 * What a link may carry, on every entry point:
 *
 *  - `return_to`     a path in this app to land on afterwards (kept as the intended URL)
 *  - `organization`  bind the sign-in to that organization (`organization=` on authorize)
 *  - `login_hint`    an email address to prefill (`email` is read too)
 */
final readonly class SignInController
{
    /** `prompt` values a link to `/login` may ask for. Never `none`: that cannot show a page. */
    private const PROMPTS = [Prompt::Login, Prompt::SelectAccount, Prompt::SelectOrganization];

    /**
     * `/login`: straight to Cbox ID. Somebody who is already signed in goes home, unless
     * the link asks for another account or organization (`prompt`, `organization`).
     */
    public function login(Request $request, IdentityClient $identity): Response
    {
        $prompt = $this->prompt($request);
        $organization = $this->organization($request);

        if ($prompt === null && $organization === null && $this->signedIn()) {
            return new RedirectResponse(url($this->returnTo($request) ?? $this->home()));
        }

        return $this->start($request, $identity, static fn (IdentityClient $identity, ?string $hint): RedirectResponse => $identity->redirect(
            prompt: $prompt,
            loginHint: $hint,
            organization: $organization,
            idpHint: self::word($request->query('idp_hint'), 32),
        ));
    }

    /**
     * `/signup`: Cbox ID's hosted "create an account" page (`prompt=create`), or its
     * sign-in page when the instance does not offer sign-up. Never bound to an
     * organization: Cbox ID refuses the two together.
     */
    public function signup(Request $request, IdentityClient $identity): Response
    {
        if ($this->signedIn()) {
            return new RedirectResponse(url($this->returnTo($request) ?? $this->home()));
        }

        return $this->start($request, $identity, static fn (IdentityClient $identity, ?string $hint): RedirectResponse => $identity->signUp($hint));
    }

    /**
     * `/login/launch?organization=`: open this app for one organization, from the app
     * switcher or a link in another Cbox app. Starts a sign-in even when somebody is
     * signed in here, because the organization is in the token.
     */
    public function launch(Request $request, IdentityClient $identity): Response
    {
        $organization = $this->organization($request);

        return $this->start($request, $identity, static fn (IdentityClient $identity, ?string $hint): RedirectResponse => $identity->redirect(
            loginHint: $hint,
            organization: $organization,
        ));
    }

    /**
     * `/login/invitation?organization=`: where an invitation brings somebody back to.
     * The invitation itself is accepted on Cbox ID (the mail links there, and a person
     * without an account makes one there); this signs them in bound to the organization
     * they just joined, so that is the one they arrive in.
     */
    public function invitation(Request $request, IdentityClient $identity): Response
    {
        $organization = $this->organization($request);

        if ($organization === null) {
            return $this->login($request, $identity);
        }

        return $this->start($request, $identity, static fn (IdentityClient $identity, ?string $hint): RedirectResponse => $identity->redirect(
            loginHint: $hint,
            organization: $organization,
        ));
    }

    /** What the person sees when a sign-in did not complete. Never a redirect back to Cbox ID. */
    public function error(Request $request): Response
    {
        $code = $request->session()->get(SignInRoutes::ERROR_KEY);

        return $this->notice($request, 'error', is_string($code) ? $code : 'sign_in_failed');
    }

    /**
     * "You are signed out", for an app whose logout does not end at Cbox ID. Landing on
     * `/login` instead would sign the person straight back in while their Cbox ID session
     * is alive.
     */
    public function signedOut(Request $request): Response
    {
        return $this->notice($request, 'signed_out', null);
    }

    /**
     * @param  callable(IdentityClient, ?string): RedirectResponse  $redirect
     */
    private function start(Request $request, IdentityClient $identity, callable $redirect): Response
    {
        $session = $request->session();

        if (SignInRoundTrips::tooMany($session)) {
            Log::warning('Cbox ID sign-in: stopped after repeated round trips that signed nobody in.', ['path' => $request->path()]);

            return $this->notice($request, 'error', 'sign_in_loop');
        }

        // An explicit `return_to` wins; otherwise whatever the `auth` middleware kept
        // when it sent the guest here stays as it is.
        $returnTo = $this->returnTo($request);

        if ($returnTo !== null) {
            $session->put('url.intended', url($returnTo));
        }

        try {
            $response = $redirect($identity, $this->loginHint($request));
        } catch (Throwable $e) {
            // No issuer, no client, or a discovery document that could not be read.
            Log::warning('Cbox ID sign-in could not start.', ['reason' => $e::class, 'message' => $e->getMessage()]);

            return $this->notice($request, 'error', 'sign_in_unavailable', 503);
        }

        // An Inertia visit is an XHR and cannot follow a redirect to another origin.
        if ($request->headers->has('X-Inertia')) {
            return response('', 409, ['X-Inertia-Location' => $response->getTargetUrl()]);
        }

        return $response;
    }

    private function notice(Request $request, string $kind, ?string $code, int $status = 200): Response
    {
        $request->session()->forget(SignInRoundTrips::KEY);

        $login = SignInRoutes::path('login_path');
        $view = config('cbox-id-client.sign_in.view');
        $name = config('app.name');

        [$title, $message] = match (true) {
            $kind === 'signed_out' => [__('You are signed out'), __('Sign in again to continue.')],
            $code === 'access_denied' => [__('No access with this account'), __('This account cannot open this application or organization. Try another account, or ask an administrator of your organization.')],
            $code === 'sign_in_unavailable' => [__('Sign-in is unavailable'), __('Sign-in could not be started right now. Try again in a moment.')],
            $code === 'sign_in_loop' => [__('Sign-in did not complete'), __('Signing in kept returning here without completing. Try again, or use another account.')],
            default => [__('Sign-in did not complete'), __('Something went wrong while signing you in. Try again.')],
        };

        return response()
            ->view(is_string($view) && $view !== '' ? $view : 'cbox-id-client::sign-in.notice', [
                'kind' => $kind,
                'code' => $code,
                'title' => $title,
                'message' => $message,
                'app' => is_string($name) ? $name : '',
                'signInUrl' => $login !== null ? url($login) : null,
                'otherAccountUrl' => $login !== null && $kind === 'error' ? url($login).'?prompt='.Prompt::SelectAccount->value : null,
                'nonce' => Vite::cspNonce(),
            ], $status)
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function signedIn(): bool
    {
        $guard = config('cbox-id-client.sign_in.guard');

        return Auth::guard(is_string($guard) && $guard !== '' ? $guard : null)->check();
    }

    private function home(): string
    {
        $home = config('cbox-id-client.sign_in.home');

        return is_string($home) && $home !== '' ? $home : '/';
    }

    /** A path in this app, or null. Never another origin. */
    private function returnTo(Request $request): ?string
    {
        $returnTo = $request->query('return_to');

        return is_string($returnTo) && $returnTo !== '' && AppCatalogController::returnPath($returnTo) === $returnTo ? $returnTo : null;
    }

    private function organization(Request $request): ?string
    {
        return self::word($request->query('organization'), 64);
    }

    private function prompt(Request $request): ?Prompt
    {
        $prompt = $request->query('prompt');
        $prompt = is_string($prompt) ? Prompt::tryFrom($prompt) : null;

        return in_array($prompt, self::PROMPTS, true) ? $prompt : null;
    }

    private function loginHint(Request $request): ?string
    {
        $hint = $request->query('login_hint') ?? $request->query('email');

        return is_string($hint) && strlen($hint) <= 254 && filter_var($hint, FILTER_VALIDATE_EMAIL) !== false ? $hint : null;
    }

    private static function word(mixed $value, int $max): ?string
    {
        return is_string($value) && preg_match('/\A[A-Za-z0-9_-]{1,'.$max.'}\z/', $value) === 1 ? $value : null;
    }
}
