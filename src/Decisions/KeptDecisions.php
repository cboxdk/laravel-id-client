<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Decisions;

use Cbox\Id\Client\Authz\PermissionDecision;
use Cbox\Id\Client\Contracts\KeptPermissionDecisions;
use Cbox\Id\Client\Contracts\PermissionDecisions;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Background;
use Cbox\Id\Client\Webhooks\EventType;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Cbox ID's permission decisions, kept in the cache so pages render from local data and
 * keep rendering while Cbox ID is down.
 *
 * Every answer, yes or no, is kept with the time it was asked. It is fresh for
 * `fresh_for` seconds and kept for `max_age` seconds (thirty days):
 *
 * - A fresh answer is used by every caller.
 * - A page view (a safe method: GET, HEAD) gets a stale answer at once, however old it
 *   is within `max_age`, and a queued {@see RefreshKeptDecision} asks Cbox ID again, so
 *   a changed role shows one page view later. In an app with no queue worker (the
 *   `sync` driver) the same refresh runs after the response instead of inside it. If
 *   Cbox ID is down, the refresh fails quietly and the kept answer stays.
 * - A change (POST, PUT, PATCH, DELETE), a call with `$acting: true`, and a call outside
 *   an HTTP request (queue job, console) never act on a stale answer: they ask now.
 * - With no kept answer, or one older than `max_age`, the caller asks now. That is the
 *   one wait a person has, on their first visit.
 *
 * WHAT ENDS A KEPT ANSWER EARLY. Cbox ID saying that access changed: the webhooks in
 * {@see self::SUBJECT_EVENTS} and {@see self::ORGANIZATION_EVENTS}, and a back-channel
 * logout, mark the person (or the whole organization) as changed at that moment, and
 * every answer kept before the mark is ignored, so the next check asks now. The package
 * registers those handlers itself; they run when the app receives Cbox ID's webhooks.
 * Without them a page view can show a revoked person one stale page, once `fresh_for`
 * has passed, before the background refresh corrects it; a change never can.
 *
 * Asking uses a `decisions:read` machine token, which the identity client keeps until
 * shortly before it expires. A 401 drops that token and asks once more with a new one.
 * A failure with nothing usable kept throws, as {@see PermissionDecisions::check()} does.
 *
 * Only plain arrays and scalars go into the cache, and each entry is checked on the way
 * out; anything else is ignored and asked again. The cache key covers the client, the
 * subject, the organization and the permission set.
 */
class KeptDecisions implements KeptPermissionDecisions
{
    /** The scope the machine token needs to ask for decisions. */
    public const string SCOPE = 'decisions:read';

    private const string PREFIX = 'cbox-id-client.decision.';

    private const int VERSION = 1;

    /** `max_age` when nothing is configured: thirty days. */
    public const int DEFAULT_MAX_AGE = 2_592_000;

    /**
     * Webhooks that change what one person may do. Each carries `user_id`.
     *
     * @var list<EventType>
     */
    public const array SUBJECT_EVENTS = [
        EventType::UserDeactivated,
        EventType::UserReactivated,
        EventType::OrganizationMemberRemoved,
        EventType::OrganizationMemberRoleChanged,
        EventType::MembershipDeleted,
        EventType::MembershipUpdated,
        EventType::RoleAssigned,
        EventType::RoleUnassigned,
        EventType::RoleAssignedEverywhere,
        EventType::RoleUnassignedEverywhere,
        EventType::GovernanceAccessRevoked,
        EventType::DirectoryUserDeprovisioned,
        EventType::DirectoryUserDeactivated,
        EventType::DirectoryGroupMembershipChanged,
    ];

    /**
     * Webhooks that change what everybody in one organization may do.
     *
     * @var list<EventType>
     */
    public const array ORGANIZATION_EVENTS = [
        EventType::OrganizationSuspended,
        EventType::OrganizationReactivated,
        EventType::OrganizationArchived,
        EventType::OrganizationDeleted,
    ];

    private readonly int $freshFor;

    private readonly int $maxAge;

    public function __construct(
        private readonly PermissionDecisions $decisions,
        private readonly IdentityClient $identity,
        private readonly Repository $cache,
        private readonly Application $app,
        private readonly string $clientId,
        int $freshFor = 30,
        int $maxAge = self::DEFAULT_MAX_AGE,
        private readonly ?string $connection = null,
        private readonly ?string $queue = null,
    ) {
        $this->freshFor = max(0, $freshFor);
        $this->maxAge = max(1, $this->freshFor, $maxAge);
    }

    public function check(string $subject, ?string $organization, array $permissions, bool $acting = false): PermissionDecision
    {
        $request = $this->currentRequest();

        if ($request === null) {
            return $this->refresh($subject, $organization, $permissions);
        }

        $kept = $this->read($this->key($subject, $organization, $permissions));

        if ($kept !== null && $kept['at'] <= $this->changedAt($subject, $organization)) {
            // Cbox ID said this person's access changed after the answer was asked.
            $kept = null;
        }

        if ($kept !== null) {
            $age = Carbon::now()->getTimestamp() - $kept['at'];

            if ($age <= $this->freshFor) {
                return $kept['decision'];
            }

            if ($age <= $this->maxAge && ! $acting && $request->isMethodSafe()) {
                $this->queueRefresh($subject, $organization, $permissions);

                return $kept['decision'];
            }
        }

        return $this->refresh($subject, $organization, $permissions);
    }

    public function refresh(string $subject, ?string $organization, array $permissions): PermissionDecision
    {
        $permissions = array_values(array_unique($permissions));

        try {
            $decision = $this->decisions->check($this->identity->machineToken([self::SCOPE]), $subject, $organization, $permissions);
        } catch (AuthenticationFailed $error) {
            if ($error->status !== 401) {
                throw $error;
            }

            // The kept machine token was refused (revoked, or the client's secret rotated).
            $this->identity->forgetMachineToken([self::SCOPE]);
            $decision = $this->decisions->check($this->identity->machineToken([self::SCOPE]), $subject, $organization, $permissions);
        }

        $this->write($this->key($subject, $organization, $permissions), $decision);

        return $decision;
    }

    public function forget(string $subject, ?string $organization, array $permissions): void
    {
        try {
            $this->cache->forget($this->key($subject, $organization, $permissions));
        } catch (Throwable $error) {
            report($error);
        }
    }

    /**
     * Cbox ID said what this person may do has changed (deactivated, removed from an
     * organization, a role taken away). Every answer kept for them before now is
     * ignored: the next check asks. Never throws.
     */
    public function revokeSubject(string $subject): void
    {
        $this->mark($this->subjectMark($subject));
    }

    /** The same for everybody in one organization (it was suspended, archived, deleted). */
    public function revokeOrganization(string $organization): void
    {
        $this->mark($this->organizationMark($organization));
    }

    /**
     * Apply a verified webhook: one of {@see self::SUBJECT_EVENTS} marks its `user_id`,
     * one of {@see self::ORGANIZATION_EVENTS} its organization. Anything else is ignored.
     */
    public function revokedBy(WebhookEvent $event): void
    {
        $type = $event->eventType();

        if ($type === null) {
            return;
        }

        if (in_array($type, self::SUBJECT_EVENTS, true)) {
            $subject = $event->id('user_id');

            if ($subject !== null && $subject !== '') {
                $this->revokeSubject($subject);
            }

            return;
        }

        if (in_array($type, self::ORGANIZATION_EVENTS, true)) {
            $organization = $event->organizationId ?? $event->id('id');

            if ($organization !== null && $organization !== '') {
                $this->revokeOrganization($organization);
            }
        }
    }

    /**
     * The cache key for one question. Permissions are deduplicated and sorted, so the
     * order they are asked in does not matter.
     *
     * @param  list<string>  $permissions
     */
    public function key(string $subject, ?string $organization, array $permissions): string
    {
        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return self::PREFIX.hash('sha256', json_encode([$this->clientId, $subject, $organization, $permissions], JSON_THROW_ON_ERROR));
    }

    /**
     * The request being served, or null outside an HTTP request (a queue worker, a
     * console command). Under Octane, workers run from the console but serve requests.
     */
    protected function currentRequest(): ?Request
    {
        $servingHttp = ! $this->app->runningInConsole() || $this->app->runningUnitTests() || isset($_SERVER['LARAVEL_OCTANE']);

        if (! $servingHttp || ! $this->app->bound('request')) {
            return null;
        }

        return $this->app->make('request');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function queueRefresh(string $subject, ?string $organization, array $permissions): void
    {
        $permissions = array_values(array_unique($permissions));

        Background::dispatch(
            'cbox-id-client:decision:'.$this->key($subject, $organization, $permissions),
            static fn (): PendingDispatch => RefreshKeptDecision::dispatch($subject, $organization, $permissions),
            function () use ($subject, $organization, $permissions): void {
                $this->refresh($subject, $organization, $permissions);
            },
            $this->connection,
            $this->queue,
        );
    }

    /**
     * When Cbox ID last said this person's access, or this organization's, changed.
     * Zero when it never did. A cache that cannot be read counts as "never": the
     * answer's own age still bounds it.
     */
    private function changedAt(string $subject, ?string $organization): int
    {
        try {
            $subjectAt = $this->cache->get($this->subjectMark($subject));
            $organizationAt = $organization !== null ? $this->cache->get($this->organizationMark($organization)) : null;
        } catch (Throwable $error) {
            report($error);

            return 0;
        }

        return max(is_int($subjectAt) ? $subjectAt : 0, is_int($organizationAt) ? $organizationAt : 0);
    }

    private function mark(string $key): void
    {
        try {
            $this->cache->put($key, Carbon::now()->getTimestamp(), $this->maxAge);
        } catch (Throwable $error) {
            report($error);
        }
    }

    private function subjectMark(string $subject): string
    {
        return self::PREFIX.'changed.subject.'.hash('sha256', json_encode([$this->clientId, $subject], JSON_THROW_ON_ERROR));
    }

    private function organizationMark(string $organization): string
    {
        return self::PREFIX.'changed.organization.'.hash('sha256', json_encode([$this->clientId, $organization], JSON_THROW_ON_ERROR));
    }

    /** @return array{at: int, decision: PermissionDecision}|null */
    private function read(string $key): ?array
    {
        try {
            $stored = $this->cache->get($key);
        } catch (Throwable $error) {
            report($error);

            return null;
        }

        if (! is_array($stored) || ($stored['v'] ?? null) !== self::VERSION || ! is_int($stored['at'] ?? null)
            || ! is_string($stored['subject'] ?? null) || ! array_key_exists('organization', $stored)
            || ! ($stored['organization'] === null || is_string($stored['organization']))
            || ! is_string($stored['client_id'] ?? null) || ! is_bool($stored['active'] ?? null)
            || ! array_key_exists('role', $stored) || ! is_array($stored['permissions'] ?? null)) {
            return null;
        }

        $role = $stored['role'];

        if ($role !== null && (! is_string($role) || OrganizationRole::tryFrom($role) === null)) {
            return null;
        }

        $permissions = [];

        foreach ($stored['permissions'] as $permission => $allowed) {
            if (! is_string($permission) || ! is_bool($allowed)) {
                return null;
            }

            $permissions[$permission] = $allowed;
        }

        return [
            'at' => $stored['at'],
            'decision' => new PermissionDecision(
                $stored['subject'],
                $stored['organization'],
                $stored['client_id'],
                is_string($role) ? OrganizationRole::from($role) : null,
                $stored['active'],
                $permissions,
            ),
        ];
    }

    private function write(string $key, PermissionDecision $decision): void
    {
        try {
            $this->cache->put($key, [
                'v' => self::VERSION,
                'at' => Carbon::now()->getTimestamp(),
                'subject' => $decision->subject,
                'organization' => $decision->organization,
                'client_id' => $decision->clientId,
                'role' => $decision->organizationRole?->value,
                'active' => $decision->organizationActive,
                'permissions' => $decision->permissions,
            ], $this->maxAge);
        } catch (Throwable $error) {
            // The answer is still good for this request; the next one asks again.
            report($error);
        }
    }
}
