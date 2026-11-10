<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\Shell\MirroredOrganization;
use Cbox\Id\Client\Shell\MirroredOrganizationPage;
use Cbox\Id\Client\Shell\SessionOrganizationDirectory;
use Illuminate\Http\Request;

/**
 * The organizations the request's person belongs to, from the app's LOCAL copy: its
 * mirror of Cbox ID (kept by webhooks), never a call to Cbox ID. The shell's organization
 * menu, the URL's `/orgs/{slug}` and the token re-bind all read it, on every page.
 *
 * Bound to {@see SessionOrganizationDirectory}, which reads the `organizations` claim the
 * person signed in with. An app that mirrors memberships binds its own over its tables.
 *
 * Deny by default: answer only organizations the person is an active member of.
 */
interface OrganizationDirectory
{
    /** One page of the person's organizations, in the order to show them, narrowed by name. */
    public function organizations(Request $request, ?string $search = null, ?string $cursor = null, int $limit = 25): MirroredOrganizationPage;

    public function findById(Request $request, string $id): ?MirroredOrganization;

    public function findBySlug(Request $request, string $slug): ?MirroredOrganization;

    /**
     * Keep an organization the person just created, so the next page finds it before the
     * webhook that mirrors it arrives.
     */
    public function remember(Request $request, MirroredOrganization $organization): void;
}
