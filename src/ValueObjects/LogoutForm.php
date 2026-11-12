<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\IdentityClient;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * RP-initiated logout by POST: where to post and what, so the id_token goes in the request
 * body instead of a URL.
 *
 * A URL is written to access logs, kept in browser history and sent on as `Referer`. An
 * id_token that names the person (`email`, `name`, organizations, roles) must not travel
 * that way, and OIDC RP-Initiated Logout 1.0 §2 gives the alternative: the end-session
 * endpoint takes the same parameters by POST.
 *
 * Return it from the logout route and it renders a small page that posts itself:
 *
 *     return CboxId::logoutForm(route('login'), $idToken) ?? redirect()->route('login');
 *
 * or read `action` and `fields` and put the form in a page of your own. Under a
 * Content-Security-Policy that refuses inline scripts, pass the page's nonce with
 * {@see withNonce()}; without one the page shows a "Sign out" button to press.
 *
 * FROM ANOTHER SITE THE PROVIDER'S COOKIE DOES NOT COME ALONG. A browser does not send a
 * `SameSite=Lax` session cookie with a cross-site POST, so the provider learns who is
 * signing out from the hint alone and ends what it ends for a verified hint. When the app
 * and the issuer are on different sites and the provider needs its cookie to end the
 * browser's session, {@see IdentityClient::logoutUrl()} is the request that
 * carries it (and it leaves a hint that names the person out by itself).
 *
 * The app's Content-Security-Policy must allow the issuer in `form-action`.
 *
 * `new LogoutForm('https://id.example/oauth/logout')` is a valid form with no fields, so
 * a test can build one.
 */
class LogoutForm implements Responsable
{
    /** @param  array<string, string>  $fields */
    public function __construct(
        public readonly string $action,
        public readonly array $fields = [],
        public readonly ?string $nonce = null,
    ) {}

    public function withNonce(?string $nonce): self
    {
        return new self($this->action, $this->fields, $nonce);
    }

    /** @param  Request  $request */
    public function toResponse($request): Response
    {
        $inputs = '';

        foreach ($this->fields as $name => $value) {
            $inputs .= '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
        }

        $nonce = $this->nonce !== null && $this->nonce !== '' ? ' nonce="'.e($this->nonce).'"' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="referrer" content="no-referrer"><title>Signing out</title></head><body>'
            .'<form method="post" action="'.e($this->action).'" id="cbox-id-logout">'.$inputs
            .'<p>Signing you out.</p><button type="submit">Sign out</button></form>'
            .'<script'.$nonce.'>document.getElementById("cbox-id-logout").submit();</script>'
            .'</body></html>';

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
