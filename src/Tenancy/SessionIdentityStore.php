<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Tenancy;

use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Claims;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Remembers who signed in through Cbox ID, in the Laravel session, between requests.
 *
 * **BOUND TO ONE LOCAL USER.** Your application logs somebody in with `Auth::login()`
 * after {@see IdentityClient::authenticate()}; the `Login` event that
 * fires binds the remembered identity to THAT user's id. From then on it answers only
 * while the same user is logged in, so an application that logs a different person into
 * the same session — an impersonation feature, a second login form — can never inherit
 * the first person's Cbox ID permissions. A different user logging in forgets it
 * outright, and `Logout` forgets it too.
 *
 * Before it is bound it answers only to a guest: that is an application that uses Cbox ID
 * as its whole login and never calls `Auth::login()`.
 *
 * The organization the person chose is part of what is remembered, which is what makes
 * "the current organization" survive from one request to the next.
 */
class SessionIdentityStore
{
    public const KEY = 'cbox-id-client.identity';

    /**
     * Remember an identity. The binding to a local user is kept when it is the SAME
     * subject signing in again — an organization switch is a fresh authorization for the
     * person already logged in, and their application may reasonably not log them in a
     * second time — and dropped otherwise.
     */
    public function remember(Identity $identity): void
    {
        $previous = $this->stored();
        $boundTo = $previous !== null && hash_equals($previous['identity']->subject, $identity->subject)
            ? $previous['bound_to']
            : null;

        session()->put(self::KEY, ['claims' => $identity->toArray(), 'bound_to' => $boundTo]);
    }

    /**
     * The remembered identity, without asking who is logged in. Use {@see current()}
     * for any decision.
     */
    public function identity(): ?Identity
    {
        return $this->stored()['identity'] ?? null;
    }

    /**
     * The remembered identity, if it belongs to `$user` (null = a guest).
     */
    public function current(?Authenticatable $user): ?Identity
    {
        $stored = $this->stored();

        if ($stored === null) {
            return null;
        }

        if ($stored['bound_to'] === null) {
            return $user === null ? $stored['identity'] : null;
        }

        $id = $user !== null ? self::idOf($user) : null;

        return $id !== null && hash_equals($stored['bound_to'], $id) ? $stored['identity'] : null;
    }

    /** Called on Laravel's `Login` event. */
    public function bindTo(Authenticatable $user): void
    {
        $stored = $this->stored();
        $id = self::idOf($user);

        if ($stored === null) {
            return;
        }

        if ($id === null || ($stored['bound_to'] !== null && ! hash_equals($stored['bound_to'], $id))) {
            $this->forget();

            return;
        }

        session()->put(self::KEY, ['claims' => $stored['identity']->toArray(), 'bound_to' => $id]);
    }

    public function forget(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * @return array{identity: Identity, bound_to: string|null}|null
     */
    private function stored(): ?array
    {
        $raw = session()->get(self::KEY);

        if (! is_array($raw)) {
            return null;
        }

        $identity = Identity::fromClaims(Claims::object($raw, 'claims'));

        if ($identity === null) {
            return null;
        }

        return ['identity' => $identity, 'bound_to' => Claims::string($raw, 'bound_to')];
    }

    private static function idOf(Authenticatable $user): ?string
    {
        $id = $user->getAuthIdentifier();

        return is_int($id) || (is_string($id) && $id !== '') ? (string) $id : null;
    }
}
