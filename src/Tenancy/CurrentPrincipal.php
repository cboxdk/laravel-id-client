<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Tenancy;

use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;
use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Answers "who is acting in this request, as far as Cbox ID is concerned".
 *
 * In order:
 *
 * 1. A principal a test installed with `CboxId::fake()->actingAs(…)`.
 * 2. A credential THIS request presented and a middleware verified — a bearer token
 *    (`cbox-id.token`) or a customer API key (`cbox-id.api-key`). Read off the request's
 *    attributes rather than the container, because the proof belongs to one request and
 *    must not outlive it.
 * 3. The identity the session remembers from sign-in, if it belongs to `$user`.
 *
 * A verified credential answers only for the user the request is authenticated as (or
 * for a guest check): `Gate::forUser($someoneElse)` must not be answered with the
 * caller's own permissions.
 */
class CurrentPrincipal
{
    public const TOKEN_ATTRIBUTE = 'cbox_id_token';

    public const API_KEY_ATTRIBUTE = 'cbox_id_api_key';

    private ?Principal $fake = null;

    public function __construct(private readonly SessionIdentityStore $sessions) {}

    public function resolve(?Authenticatable $user, ?Request $request): ?Principal
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        if ($request !== null) {
            $presented = $this->presented($request);

            if ($presented !== null) {
                return $user === null || self::same($user, $request->user()) ? $presented : null;
            }
        }

        return $this->sessions->current($user);
    }

    /** Install (or, with null, remove) a principal for tests. */
    public function fake(?Principal $principal): void
    {
        $this->fake = $principal;
    }

    private function presented(Request $request): ?Principal
    {
        $token = $request->attributes->get(self::TOKEN_ATTRIBUTE);

        if ($token instanceof VerifiedToken) {
            return $token;
        }

        $key = $request->attributes->get(self::API_KEY_ATTRIBUTE);

        return $key instanceof VerifiedApiKey ? $key : null;
    }

    private static function same(Authenticatable $user, mixed $other): bool
    {
        return $other instanceof Authenticatable
            && $user->getAuthIdentifier() === $other->getAuthIdentifier();
    }
}
