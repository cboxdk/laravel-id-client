<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\ValueObjects\Actor;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Cbox\Id\Client\ValueObjects\Organization;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;
use Cbox\Id\Client\ValueObjects\VerifiedToken;

/**
 * Whoever is acting in this request, as Cbox ID described them: a person who signed in
 * ({@see CboxUser}), a bearer token presented to your API
 * ({@see VerifiedToken}), a customer API key
 * ({@see VerifiedApiKey}), or the identity remembered in the
 * session between requests ({@see Identity}).
 *
 * One interface so authorization reads the same whichever door somebody came through:
 * the permission gate, the `cbox-id.org` and `cbox-id.permission` middleware, and your
 * own code ask these questions and never which kind of credential answered them.
 *
 * Every answer is deny-by-default: an absent or malformed claim is "no".
 */
interface Principal
{
    /** The stable Cbox ID subject (`sub`). Key your local records on this. */
    public function subjectId(): string;

    /** The organization this principal acts for, or null when it acts for none. */
    public function organization(): ?Organization;

    /**
     * This application's roles held in that organization (plus environment-wide ones).
     *
     * @return list<string>
     */
    public function roles(): array;

    /**
     * This application's `feature:action` permissions, as Cbox ID resolved them.
     *
     * @return list<string>
     */
    public function permissions(): array;

    public function hasPermission(string $permission): bool;

    public function hasRole(string $role): bool;

    /** The member of staff acting as this subject in a support session, if any. */
    public function actor(): ?Actor;

    public function isSupportSession(): bool;
}
