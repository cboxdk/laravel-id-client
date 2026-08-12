<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Frontend;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\FrontendApiUnavailable;
use Cbox\Id\Client\ValueObjects\FrontendConfig;
use Cbox\Id\Client\ValueObjects\FrontendSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The browser-facing half of Cbox ID, read from PHP.
 *
 * Everything else in this package assumes a CONFIDENTIAL client: it holds a secret, it
 * exchanges codes, it verifies webhooks. A publishable key is the opposite — public on
 * purpose, safe in a bundle, and useful only from the origins its owner registered.
 *
 * WHY A PHP CLIENT FOR A BROWSER-FACING API AT ALL. A Blade page still has to know what to
 * draw. Reading the environment's sign-in configuration here — the endpoints, the social
 * buttons, the customer's theme — lets a server-rendered sign-in box carry the customer's
 * branding without shipping a JavaScript SDK to fetch it, and without a flash of unstyled
 * form while it arrives. It is also the only way to draw that box on a page that has to
 * work with JavaScript disabled.
 *
 * THE PUBLISHABLE KEY GRANTS NOTHING ON ITS OWN. {@see session()} is authorized by an
 * access token the caller already holds; the key only names an environment and says a
 * browser is asking. That is the property that makes it safe to publish, and it is why
 * this class refuses a `cbox_sk_`-shaped value outright rather than discovering the
 * mistake later as an opaque 401 in somebody's log.
 *
 * CACHED, BECAUSE A PAGE RENDER IS NOT A GOOD PLACE FOR A NETWORK CALL. The configuration
 * document changes when somebody edits it in the console, so a short server-side cache is
 * the right trade for something that decides layout. The session is never cached — it
 * describes one person holding one token.
 */
class FrontendClient
{
    public function __construct(
        private readonly string $issuer,
        private readonly string $publishableKey,
        private readonly int $cacheTtl = 60,
        private readonly int $timeout = 5,
    ) {
        if (trim($this->issuer) === '') {
            throw ClientConfigurationException::because('A Cbox ID issuer is required to read the Frontend API.');
        }

        if (! str_starts_with($this->publishableKey, 'pk_')) {
            // Caught here rather than at the first request: putting a client secret where
            // a public key belongs is the exact mistake this channel exists to make
            // unnecessary, and as an opaque 401 in a log the cause is invisible.
            throw ClientConfigurationException::because(
                'A Cbox ID publishable key must start with pk_test_ or pk_live_. '.
                'Client secrets and API keys must never be used here — this value is meant to be public.',
            );
        }
    }

    /** Whether this key drives real sign-ins, rather than test ones. */
    public function isLive(): bool
    {
        return str_starts_with($this->publishableKey, 'pk_live_');
    }

    /**
     * Everything needed to draw a sign-in box, and nothing that identifies anybody.
     *
     * @throws FrontendApiUnavailable
     */
    public function config(): FrontendConfig
    {
        /** @var array<string, mixed> $document */
        $document = Cache::remember(
            // Keyed on the KEY as well as the issuer: one application may legitimately
            // read two environments, and a cache keyed on the issuer alone would serve
            // one customer's branding on the other's page.
            'cbox-id-client.frontend.'.hash('sha256', $this->issuer.'|'.$this->publishableKey),
            $this->cacheTtl,
            fn (): array => $this->get('/frontend/v1/config'),
        );

        return FrontendConfig::fromArray($document);
    }

    /**
     * Who the holder of this access token is, or nobody.
     *
     * Signed out is a STATE, not an error: a page that renders an avatar on every request
     * should not have to treat "nobody is signed in" as a failure, and an empty token is
     * answered without a network call at all.
     *
     * @throws FrontendApiUnavailable
     */
    public function session(?string $accessToken): FrontendSession
    {
        if ($accessToken === null || trim($accessToken) === '') {
            return new FrontendSession(null);
        }

        return FrontendSession::fromArray($this->get('/frontend/v1/session', [
            'Authorization' => 'Bearer '.$accessToken,
        ]));
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws FrontendApiUnavailable
     */
    private function get(string $path, array $headers = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    // A HEADER, NEVER A QUERY STRING. A query string puts the key in
                    // server logs, in `Referer` on every outbound link, and in browser
                    // history — and while the key is public, spraying it across logs
                    // makes revocation harder to reason about and makes the key look like
                    // a secret that leaked.
                    'X-Cbox-Publishable-Key' => $this->publishableKey,
                    'Accept' => 'application/json',
                    ...$headers,
                ])
                ->get(rtrim($this->issuer, '/').$path);
        } catch (Throwable $e) {
            throw FrontendApiUnavailable::unreachable($this->issuer, $e);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            // THE SERVER'S OWN REASON, when it sent one. It answers precisely — "No
            // publishable key was presented" is a wiring mistake and "That publishable key
            // cannot be used from this origin" is an allow-list — and substituting one
            // guess for both would make this client less useful than the API it wraps.
            throw FrontendApiUnavailable::refused(
                $this->description($response->json()) ?? sprintf(
                    'Cbox ID refused the request (%d). Check that this key is not revoked, and that '.
                    'the origin it is used from is on its allow-list.',
                    $response->status(),
                ),
                $response->status(),
            );
        }

        if ($response->failed()) {
            throw FrontendApiUnavailable::refused(
                $this->description($response->json()) ?? sprintf('Cbox ID returned %d.', $response->status()),
                $response->status(),
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw FrontendApiUnavailable::refused(
                'Cbox ID returned a body that is not the expected document. Something between this '.
                'server and the issuer is rewriting responses.',
                $response->status(),
            );
        }

        $document = [];

        foreach ($body as $key => $value) {
            if (is_string($key)) {
                $document[$key] = $value;
            }
        }

        return $document;
    }

    private function description(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $description = $body['error_description'] ?? null;

        return is_string($description) && $description !== '' ? $description : null;
    }
}
