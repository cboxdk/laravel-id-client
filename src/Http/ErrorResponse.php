<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The SDK's one error envelope, `{"error": {"type": …, "detail": …}}` — what
 * `cbox-id.token` has always answered, now shared by every middleware here so a client
 * parses one shape whichever of them refused it.
 *
 * @internal
 */
final class ErrorResponse
{
    /**
     * @param  array<string, mixed>  $extra  merged into the `error` object
     * @param  array<string, string>  $headers
     */
    public static function json(string $type, string $detail, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => ['type' => $type, 'detail' => $detail] + $extra], $status, $headers);
    }

    /**
     * The pipeline's answer, typed. `Closure` tells PHPStan nothing about what it hands
     * back, and a middleware whose return type is a promise it does not keep is worse
     * than one with no type at all.
     */
    public static function from(mixed $response): Response
    {
        return $response instanceof Response
            ? $response
            : self::json('engine_error', 'The handler returned no response.', 500);
    }
}
