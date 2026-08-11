<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Migration;

use Cbox\Id\Client\IdentityClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * The handler on YOUR side of a migration off an old login.
 *
 * Cbox ID offers this endpoint an email and a password it has never seen; your closure
 * answers with the person or with nothing. On a yes they are created in Cbox ID as a
 * result of the login that just succeeded, and from their next sign-in the old system is
 * never asked again.
 *
 * WHY A FACTORY RATHER THAN A DOCUMENTED FORMAT. The wire contract is a signed POST with
 * JSON in and JSON out — small enough to describe in a paragraph, and describing it would
 * mean every adopter writes the signature check themselves. That check is the part people
 * get wrong: `===` instead of a constant-time compare, no freshness window, no thought
 * about a captured request being replayed an hour later. This owns all of it, and reuses
 * {@see IdentityClient::verifyWebhook()} to do so — the same construction, so there is one
 * implementation of it in this package rather than two that can drift.
 *
 * ```php
 * // routes/web.php
 * Route::post('/cbox-legacy', LegacyLogin::using(function (string $email, string $password): ?LegacyUser {
 *     $row = DB::connection('legacy')->table('users')->where('email', $email)->first();
 *
 *     return $row && Hash::check($password, $row->password)
 *         ? new LegacyUser($row->email, $row->name, $row->confirmed_at !== null, $row->password)
 *         : null;
 * }));
 * ```
 */
final class LegacyLogin
{
    /**
     * @param  Closure(string, string): ?LegacyUser  $verify
     */
    private function __construct(
        private readonly Closure $verify,
        private readonly string $secret,
        private readonly int $toleranceSeconds,
    ) {}

    /**
     * Build the route handler.
     *
     * The secret defaults to `cbox-id-client.migration.secret`, and its absence throws
     * HERE — at boot, when the route is declared — rather than at request time. An
     * endpoint that exists and cannot verify anything is one that answers 500 to Cbox ID
     * and looks like an outage; refusing to build it is a stack trace in your deploy,
     * which is where somebody is looking.
     *
     * @param  Closure(string, string): ?LegacyUser  $verify
     */
    public static function using(Closure $verify, ?string $secret = null, int $toleranceSeconds = 300): self
    {
        $secret ??= config('cbox-id-client.migration.secret');

        if (! is_string($secret) || strlen($secret) < 32) {
            throw new InvalidArgumentException(
                'A legacy-login secret of at least 32 characters is required — it is the only thing proving a request came from Cbox ID. Set cbox-id-client.migration.secret, or pass one.',
            );
        }

        return new self($verify, $secret, $toleranceSeconds);
    }

    public function __invoke(Request $request, IdentityClient $client): JsonResponse
    {
        // The RAW body. The signature covers the exact bytes sent, so re-encoding a parsed
        // payload produces different ones and would refuse every valid request — a failure
        // that looks like a wrong secret and costs an afternoon.
        $raw = $request->getContent();

        if (! $client->verifyWebhook($raw, $request->header('X-Cbox-Signature'), $this->secret, $this->toleranceSeconds)) {
            // No detail. A caller who cannot sign is not owed help telling a bad signature
            // from a stale timestamp — both mean the same thing to anyone legitimate, and
            // the difference is a hint to anyone else.
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;

        if (! is_string($email) || $email === '') {
            return new JsonResponse(['error' => 'invalid_request'], 400);
        }

        // No password means Cbox ID is asking "do you know this address" — the tooling
        // path, for a dry run before cutover. It never arrives from a sign-in.
        if (! is_string($password)) {
            return new JsonResponse(['user' => null]);
        }

        try {
            $user = ($this->verify)($email, $password);
        } catch (Throwable) {
            // 503, not 401. Your store could not DECIDE, which is not a wrong password —
            // Cbox ID refuses the sign-in either way, but it needs to tell the two apart
            // in its own logs, and a 401 would hide an outage as a bad credential.
            return new JsonResponse(['error' => 'unavailable'], 503);
        }

        return new JsonResponse($user instanceof LegacyUser ? $user->toArray() : ['user' => null]);
    }
}
