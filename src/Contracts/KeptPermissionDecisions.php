<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\Authz\PermissionDecision;
use Cbox\Id\Client\Decisions\KeptDecisions;

/**
 * Cbox ID's permission decisions, kept so a page never waits on Cbox ID and keeps
 * working while Cbox ID is away. The default implementation is
 * {@see KeptDecisions}.
 */
interface KeptPermissionDecisions
{
    /**
     * The decision for this person, organization and permission set.
     *
     * A page view (GET, HEAD) gets the kept answer at once, even a stale one, and a
     * stale one is asked again in the background. A change (POST, PUT, PATCH, DELETE),
     * a call with `$acting`, or a call outside an HTTP request (queue job, console)
     * never acts on a stale answer: it asks Cbox ID now.
     *
     * @param  list<string>  $permissions
     */
    public function check(string $subject, ?string $organization, array $permissions, bool $acting = false): PermissionDecision;

    /**
     * Ask Cbox ID now and keep the answer, yes or no.
     *
     * @param  list<string>  $permissions
     */
    public function refresh(string $subject, ?string $organization, array $permissions): PermissionDecision;

    /**
     * Drop the kept answer, so the next check asks Cbox ID. Call it when you learn a
     * person's access changed, for example from a role webhook.
     *
     * @param  list<string>  $permissions
     */
    public function forget(string $subject, ?string $organization, array $permissions): void;
}
