<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\Exceptions\TokenRejected;
use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a verified Cbox ID access token on the route, and binds it for the
 * handler.
 *
 *     Route::middleware(['api', 'cbox-id.token:tax.quote'])->post(…)
 *
 * Scopes are the middleware's parameters, so a route declares what it needs where
 * anyone reading the route can see it, rather than in a check buried in a controller
 * that a new endpoint forgets to copy.
 *
 * **A rejection is a 401 with a reason, never a 500.** The distinction decides what
 * the caller does: a 401 saying the token expired is answered by fetching a new one,
 * where a 500 is answered by retrying the same dead credential until someone
 * notices. `WWW-Authenticate` carries the machine-readable half per RFC 6750 §3, so
 * a conformant client can act without parsing prose.
 */
class VerifyAccessToken
{
    public function __construct(private readonly AccessTokenVerifier $verifier) {}

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $required = array_values($scopes);
        $token = $this->bearer($request);

        if ($token === null) {
            // 'invalid_request' rather than 'invalid_token': nothing was presented, so
            // there is no token to call invalid. RFC 6750 §3.1 draws the same line, and
            // a client retrying a token it never sent is a confusing place to start.
            return $this->challenge('invalid_request', 'No bearer token was presented.');
        }

        try {
            $verified = $this->verifier->verify($token, $required);
        } catch (TokenRejected $e) {
            return $this->challenge(
                $required === [] ? 'invalid_token' : 'insufficient_scope',
                $e->getMessage(),
                $required,
            );
        }

        // Bound rather than stuffed onto the request, so a handler asks the container
        // for a VerifiedToken and gets one that provably passed through here. There is
        // no way to obtain the type otherwise — an unverified string cannot become one.
        app()->instance(VerifiedToken::class, $verified);
        $request->attributes->set('cbox_id_token', $verified);

        $response = $next($request);

        // `Closure` tells PHPStan nothing about what the pipeline hands back, and a
        // middleware whose return type is a promise it does not keep is worse than
        // one with no type at all.
        return $response instanceof Response
            ? $response
            : new JsonResponse(['error' => ['type' => 'engine_error', 'detail' => 'The handler returned no response.']], 500);
    }

    private function bearer(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! str_starts_with(strtolower($header), 'bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }

    /**
     * @param  list<string>  $scopes
     */
    private function challenge(string $error, string $description, array $scopes = []): JsonResponse
    {
        // The description is quoted and stripped of quotes rather than interpolated
        // raw: a verifier message can contain them, and an unbalanced quote turns a
        // valid challenge header into one a client cannot parse.
        $params = sprintf('error="%s", error_description="%s"', $error, str_replace('"', "'", $description));

        if ($scopes !== []) {
            $params .= sprintf(', scope="%s"', implode(' ', $scopes));
        }

        return response()->json([
            'error' => ['type' => $error, 'detail' => $description],
        ], $error === 'insufficient_scope' ? 403 : 401)
            ->header('WWW-Authenticate', 'Bearer '.$params);
    }
}
