<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A setting this call needs is empty — the deployment is not finished being set up.
 *
 * Before this existed, an empty `CBOX_ID_ISSUER` was not noticed by anything in this
 * package: discovery fetched `/.well-known/openid-configuration` from an empty base URL
 * and Guzzle answered "URI must include a scheme", as a 500, on every request. Nothing in
 * that says *an environment variable is missing*, so the first person to see it went
 * looking for a bug in the data. Two applications wrote the same middleware to translate
 * it; this is that translation, once, where the fact is known.
 *
 * **503, not 500.** The service is not broken, it is not set up, and a caller retrying
 * later is doing the right thing — which is not true of a 500. Rendered that way on its
 * own (it is an HTTP exception to Laravel), so an application that never catches it still
 * answers legibly. The detail names the environment variable but never a value.
 *
 * Extends {@see ClientConfigurationException}, so code that caught that still does.
 */
class NotConfigured extends ClientConfigurationException implements HttpExceptionInterface
{
    /** The config key under `cbox-id-client.` that is empty, e.g. `issuer`. */
    public string $key = '';

    public static function key(string $key, ?string $purpose = null): self
    {
        $env = self::envFor($key);

        $exception = new self(
            "Cbox ID is not configured: `cbox-id-client.{$key}` is empty".
            ($env !== null ? " (set {$env})" : '').
            ($purpose !== null ? " — it is needed to {$purpose}." : '.'),
        );
        $exception->key = $key;

        return $exception;
    }

    public function getStatusCode(): int
    {
        return 503;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * JSON callers get the SDK's error envelope; everyone else falls through to the
     * application's own 503 page (`false` tells Laravel to render it the default way).
     */
    public function render(Request $request): JsonResponse|false
    {
        if (! $request->expectsJson()) {
            return false;
        }

        return new JsonResponse(['error' => [
            'type' => 'service_misconfigured',
            'detail' => 'This deployment has no identity provider configured yet.',
        ]], 503);
    }

    private static function envFor(string $key): ?string
    {
        return match ($key) {
            'issuer' => 'CBOX_ID_ISSUER',
            'client_id' => 'CBOX_ID_CLIENT_ID',
            'client_secret' => 'CBOX_ID_CLIENT_SECRET',
            'redirect' => 'CBOX_ID_REDIRECT',
            'management.key' => 'CBOX_ID_MANAGEMENT_KEY',
            default => null,
        };
    }
}
