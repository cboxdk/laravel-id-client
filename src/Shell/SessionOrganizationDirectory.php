<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Shell;

use Cbox\Id\Client\Contracts\OrganizationDirectory;
use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Support\Claims;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Http\Request;

/**
 * The default {@see OrganizationDirectory}: the organizations the person signed in with.
 * It reads the session's remembered identity (the `organizations` claim, and the bound
 * `org`), plus any organization created in this session. Local, no call to Cbox ID.
 *
 * It knows only what the last sign-in said. An organization the person joined since then
 * shows up after their next sign-in or organization switch. An app that mirrors
 * memberships by webhook should bind its own directory over that table.
 *
 * A slug the claim does not carry falls back to the id, so the URL still resolves.
 */
class SessionOrganizationDirectory implements OrganizationDirectory
{
    public const SESSION_KEY = 'cbox-id-client.shell.created-organizations';

    public function __construct(private readonly CurrentPrincipal $principals) {}

    public function organizations(Request $request, ?string $search = null, ?string $cursor = null, int $limit = 25): MirroredOrganizationPage
    {
        $all = $this->all($request);

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $all = array_values(array_filter(
                $all,
                static fn (MirroredOrganization $o): bool => str_contains(mb_strtolower($o->name), $needle)
                    || str_contains(mb_strtolower($o->slug), $needle),
            ));
        }

        $offset = $cursor !== null && ctype_digit($cursor) ? (int) $cursor : 0;
        $limit = max(1, $limit);

        return new MirroredOrganizationPage(
            array_slice($all, $offset, $limit),
            $offset + $limit < count($all) ? (string) ($offset + $limit) : null,
        );
    }

    public function findById(Request $request, string $id): ?MirroredOrganization
    {
        foreach ($this->all($request) as $organization) {
            if ($organization->id === $id) {
                return $organization;
            }
        }

        return null;
    }

    public function findBySlug(Request $request, string $slug): ?MirroredOrganization
    {
        foreach ($this->all($request) as $organization) {
            if ($organization->slug === $slug) {
                return $organization;
            }
        }

        return null;
    }

    public function remember(Request $request, MirroredOrganization $organization): void
    {
        $principal = $this->principals->resolve($request->user(), $request);

        if (! $principal instanceof Principal || ! $request->hasSession()) {
            return;
        }

        $kept = $request->session()->get(self::SESSION_KEY);
        $kept = is_array($kept) ? $kept : [];
        $kept[$organization->id] = [
            'subject' => $principal->subjectId(), 'id' => $organization->id, 'slug' => $organization->slug,
            'name' => $organization->name, 'role' => $organization->role?->value,
        ];

        $request->session()->put(self::SESSION_KEY, $kept);
    }

    /**
     * The person's organizations, the claim's order, then any created in this session.
     *
     * @return list<MirroredOrganization>
     */
    private function all(Request $request): array
    {
        $principal = $this->principals->resolve($request->user(), $request);

        if (! $principal instanceof Principal) {
            return [];
        }

        $out = [];
        $claims = $principal instanceof Identity ? $principal->claims : [];

        foreach (Claims::objects($claims, 'organizations') as $entry) {
            $organization = self::fromEntry($entry);

            if ($organization instanceof MirroredOrganization) {
                $out[$organization->id] = $organization;
            }
        }

        $current = $principal->organization();

        if ($current !== null && ! isset($out[$current->id])) {
            $out[$current->id] = new MirroredOrganization(
                $current->id, Claims::string($claims, 'org_slug') ?? $current->id, $current->label(), $current->role,
            );
        }

        foreach ($this->created($request, $principal->subjectId()) as $organization) {
            $out[$organization->id] ??= $organization;
        }

        return array_values($out);
    }

    /** @return list<MirroredOrganization> */
    private function created(Request $request, string $subject): array
    {
        $kept = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        $out = [];

        foreach (is_array($kept) ? $kept : [] as $entry) {
            if (is_array($entry) && ($entry['subject'] ?? null) === $subject) {
                $organization = self::fromEntry($entry);

                if ($organization instanceof MirroredOrganization) {
                    $out[] = $organization;
                }
            }
        }

        return $out;
    }

    /** @param array<array-key, mixed> $entry */
    private static function fromEntry(array $entry): ?MirroredOrganization
    {
        $id = Claims::string($entry, 'id');

        if ($id === null) {
            return null;
        }

        $role = Claims::string($entry, 'role');

        return new MirroredOrganization(
            $id,
            Claims::string($entry, 'slug') ?? $id,
            Claims::string($entry, 'name') ?? $id,
            $role !== null ? OrganizationRole::tryFrom($role) : null,
        );
    }
}
