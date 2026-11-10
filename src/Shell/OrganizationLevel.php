<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Shell;

use Cbox\Console\Kit\Contracts\ContextLevel;
use Cbox\Console\Kit\Contracts\CreatesContextItems;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextPage;
use Cbox\Console\Kit\Shell\ContextQuery;
use Cbox\Console\Kit\Shell\CreatedContextItem;
use Cbox\Console\Kit\Shell\ShellPerson;
use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Contracts\OrganizationDirectory;
use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ManagementApiException;
use Cbox\Id\Client\Exceptions\ManagementNetworkException;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Exceptions\ValidationFailed;
use Cbox\Id\Client\Http\BindOrganizationFromUrl;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\HttpManagementClient;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

/**
 * The shell's Organization level (console-kit's {@see ContextLevel}), on the app's local
 * {@see OrganizationDirectory}. Available when `cboxdk/laravel-console-kit` is installed:
 *
 *     Console::shell()->levels(app(OrganizationLevel::class), new ProjectLevel, …);
 *
 * The URL carries the organization: `/orgs/{org}` with its Cbox ID slug. Switching only
 * changes the URL; the `cbox-id.org-url` middleware then re-binds the person's token to
 * that organization (see {@see BindOrganizationFromUrl}).
 *
 * "New organization" creates it through the management API with the person as its Owner
 * and lands on its settings page. It needs a management key with `organizations:write`
 * (`CBOX_ID_MANAGEMENT_KEY`). Without one, or in a support session, the menu offers no
 * "New organization" at all: the shell never sends the person to another product to
 * create something (spec §5.7).
 */
class OrganizationLevel implements ContextLevel, CreatesContextItems
{
    public function __construct(
        private readonly OrganizationDirectory $directory,
        private readonly CurrentPrincipal $principals,
        private readonly Management $management,
        private readonly string $routeParameter = 'org',
        private readonly string $path = '/orgs',
        private readonly string $detailsPath = '/orgs/{slug}/settings',
        private readonly string $label = 'Organization',
    ) {}

    public function key(): string
    {
        return 'organization';
    }

    public function label(): string
    {
        return $this->label;
    }

    public function routeParameter(): string
    {
        return $this->routeParameter;
    }

    public function current(Request $request, array $ancestors): ?ContextItem
    {
        $route = $request->route();
        $slug = $route instanceof Route ? $route->originalParameter($this->routeParameter) : null;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $organization = $this->directory->findBySlug($request, $slug);

        return $organization instanceof MirroredOrganization ? self::item($organization) : null;
    }

    public function find(Request $request, string $id, array $ancestors): ?ContextItem
    {
        $organization = $this->directory->findById($request, $id);

        return $organization instanceof MirroredOrganization ? self::item($organization) : null;
    }

    public function list(Request $request, ContextQuery $query): ContextPage
    {
        $page = $this->directory->organizations($request, $query->search, $query->cursor, $query->limit);

        return new ContextPage(array_map(self::item(...), $page->items), $page->nextCursor);
    }

    public function url(ContextItem $item, array $ancestors): string
    {
        return rtrim($this->path, '/').'/'.rawurlencode($item->routeKey);
    }

    public function allUrl(array $ancestors): string
    {
        return $this->path;
    }

    public function settingsUrl(ContextItem $item, array $ancestors): string
    {
        return $this->url($item, $ancestors).'/settings';
    }

    /**
     * A signed-in person, not in a support session, and a management client that has a key.
     * Local: reads configuration only.
     */
    public function canCreate(Request $request, array $ancestors): bool
    {
        $principal = $this->principals->resolve($request->user(), $request);

        return $principal instanceof Principal && ! $principal->isSupportSession()
            && (! $this->management instanceof HttpManagementClient || $this->management->isConfigured());
    }

    /**
     * Create the organization with the person as Owner (management API), remember it in
     * the local directory and land on its details page.
     *
     * @throws ValidationException when Cbox ID refuses the name or cannot be reached
     */
    public function create(Request $request, string $name, array $ancestors): CreatedContextItem
    {
        $principal = $this->principals->resolve($request->user(), $request);

        if (! $principal instanceof Principal || $principal->isSupportSession()) {
            abort(403);
        }

        try {
            $created = $this->management->createOrganization(new NewOrganization($name, ownerUserId: $principal->subjectId()));
        } catch (NotConfigured) {
            abort(403, 'Creating an organization needs CBOX_ID_MANAGEMENT_KEY.');
        } catch (ValidationFailed $e) {
            $messages = $e->errors['name'] ?? $e->errors['slug'] ?? [];

            throw ValidationException::withMessages(['name' => $messages !== [] ? $messages : [__('Cbox ID did not accept that name.')]]);
        } catch (ManagementNetworkException|ManagementApiException) {
            throw ValidationException::withMessages(['name' => [__('Cbox ID could not create the organization right now. Try again in a moment.')]]);
        }

        $organization = new MirroredOrganization($created->id, $created->slug ?? $created->id, $created->name, OrganizationRole::Owner);
        $this->directory->remember($request, $organization);

        return new CreatedContextItem($organization->id, str_replace('{slug}', rawurlencode($organization->slug), $this->detailsPath));
    }

    private static function item(MirroredOrganization $organization): ContextItem
    {
        return new ContextItem(
            $organization->id,
            $organization->name,
            $organization->slug,
            ShellPerson::initialsOf($organization->name),
        );
    }
}
