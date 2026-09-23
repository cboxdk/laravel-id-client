<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Exceptions\ApiKeyRejected;
use Cbox\Id\Client\Exceptions\ApiKeyVerificationUnavailable;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects an API with your customers' API keys.
 *
 *     Route::middleware('cbox-id.api-key')->get('/v1/reports', …);
 *     Route::middleware('cbox-id.api-key:reports:read')->get('/v1/reports', …);
 *
 * The key arrives as `Authorization: Bearer <key>`. Parameters are permissions the key
 * must carry, all of them. On success the {@see VerifiedApiKey} is on the request
 * (`cbox_id_api_key`) and in the container, and it is the request's principal — so
 * `cbox-id.org`, `cbox-id.permission` and the permission gate work after it unchanged.
 *
 * 401 for a key that is not live, 403 for a live key without the permission, and 503
 * (with `Retry-After`) when Cbox ID could not be asked — never a 401 for an outage, which
 * would tell a customer to rotate a key that works.
 */
class VerifyApiKey
{
    public function __construct(private readonly VerifiesApiKeys $verifier) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $key = $request->bearerToken();

        if ($key === null || $key === '') {
            return ErrorResponse::json('invalid_request', 'No API key was presented.', 401, [], ['WWW-Authenticate' => 'Bearer error="invalid_request"']);
        }

        try {
            $verified = $this->verifier->verify($key, array_values($permissions));
        } catch (ApiKeyRejected $e) {
            return $e->isInsufficientPermission()
                ? ErrorResponse::json('insufficient_permission', $e->getMessage(), 403, ['required' => array_values($permissions)])
                : ErrorResponse::json('invalid_token', $e->getMessage(), 401, [], ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        } catch (ApiKeyVerificationUnavailable) {
            return ErrorResponse::json('temporarily_unavailable', 'The API key could not be verified right now. Try again shortly.', 503, [], ['Retry-After' => '5']);
        }

        $request->attributes->set(CurrentPrincipal::API_KEY_ATTRIBUTE, $verified);
        app()->instance(VerifiedApiKey::class, $verified);

        return ErrorResponse::from($next($request));
    }
}
