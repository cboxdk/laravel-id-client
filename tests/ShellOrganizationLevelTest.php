<?php

declare(strict_types=1);

use Cbox\Console\Kit\ConsoleKitServiceProvider;
use Cbox\Console\Kit\Facades\Console;
use Cbox\Console\Kit\Shell\ContextItem;
use Cbox\Console\Kit\Shell\ContextQuery;
use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Contracts\OrganizationDirectory;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ValidationFailed;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Http\BindOrganizationFromUrl;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\HttpManagementClient;
use Cbox\Id\Client\Shell\MirroredOrganization;
use Cbox\Id\Client\Shell\OrganizationLevel;
use Cbox\Id\Client\Shell\SessionOrganizationDirectory;
use Cbox\Id\Client\Testing\CboxIdFake;
use Cbox\Id\Client\Tests\Fixtures\LocalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The shell's Organization level on the app's local directory, and the token following
 * the URL's organization (shell spec §5.6). console-kit is a dev dependency here; in an
 * app it is suggested, not required.
 */

beforeEach(function (): void {
    $this->app->register(ConsoleKitServiceProvider::class);
    config(['session.driver' => 'array']);

    $this->cbox = CboxId::fake();
    $this->actingAs(LocalUser::withId(7));
    actAsMemberOf($this->cbox, 'org_1');

    $echo = static fn () => response()->json(['url' => request()->path()]);

    Route::middleware(['web', 'auth', 'cbox-id.org-url'])->group(static function () use ($echo): void {
        Route::get('/orgs', $echo)->name('orgs.index');
        Route::get('/orgs/{org}', $echo)->name('orgs.show');
        Route::get('/orgs/{org}/settings', $echo)->name('orgs.settings');
        Route::get('/orgs/{org}/invoices/{invoice}', $echo)->name('invoices.show');
        Route::get('/orgs/{org}/invoices', $echo)->name('invoices.index');
        Route::get('/teams/{team}', $echo)->middleware('cbox-id.org-url:team');
    });

    Route::middleware(['web', 'auth'])->get('/shell-prop/orgs/{org}/settings', static fn (Request $r) => Console::shell()->build($r)->toArray());

    Console::shellRoutes();
    Console::nav()->area('billing', 'Billing')->page('invoices.index', 'Invoices');
    Console::shell()->app('billing', 'Cbox Billing')->levels(app(OrganizationLevel::class));
});

/** The person, bound to `$bound`, member of Cbox (owner) and Acme. */
function actAsMemberOf(CboxIdFake $cbox, string $bound, ?string $actor = null): void
{
    $cbox->actingAs('usr_1', organization: $bound, role: OrganizationRole::Owner, organizationName: $bound === 'org_1' ? 'Cbox' : 'Acme', actor: $actor, claims: [
        'organizations' => [
            ['id' => 'org_1', 'name' => 'Cbox', 'slug' => 'cbox', 'role' => 'owner'],
            ['id' => 'org_2', 'name' => 'Acme Corp', 'slug' => 'acme', 'role' => 'member'],
        ],
    ]);
}

it('is bound only because console-kit is installed, and resolves the current organization from the URL slug', function (): void {
    expect(app(OrganizationLevel::class))->toBeInstanceOf(OrganizationLevel::class);

    $shell = $this->getJson('/shell-prop/orgs/cbox/settings')->assertOk()->json();

    expect($shell['context']['levels'][0])->toBe([
        'key' => 'organization',
        'label' => 'Organization',
        'current' => ['id' => 'org_1', 'name' => 'Cbox', 'initials' => 'C'],
        'listUrl' => '/shell/context/organization',
        'createUrl' => '/shell/context/organization',
        'allUrl' => '/orgs',
        'settingsUrl' => '/shell/context/organization/{id}/settings',
    ]);
});

it('lists the person\'s organizations from local data, searchable, without calling Cbox ID', function (): void {
    $this->getJson('/shell/context/organization')->assertOk()->assertJson([
        'object' => 'list',
        'data' => [
            ['id' => 'org_1', 'name' => 'Cbox', 'initials' => 'C'],
            ['id' => 'org_2', 'name' => 'Acme Corp', 'initials' => 'AC'],
        ],
        'has_more' => false,
    ]);

    $this->getJson('/shell/context/organization?q=acme')->assertJsonPath('data.*.id', ['org_2']);
    $this->get('/shell/context/organization/org_2/settings')->assertRedirect('/orgs/acme/settings');

    $this->cbox->management()->assertNothingCalled();
});

it('switches to the same page in the other organization, and falls back from a record page', function (): void {
    $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/settings'])
        ->assertOk()->assertJson(['url' => '/orgs/acme/settings', 'resolution' => 'same_page']);

    $this->postJson('/shell/switch', ['select' => ['organization' => 'org_2'], 'from' => '/orgs/cbox/invoices/inv_9'])
        ->assertOk()->assertJson(['url' => '/orgs/acme/invoices', 'resolution' => 'list_fallback']);

    $this->postJson('/shell/switch', ['select' => ['organization' => 'org_9'], 'from' => '/orgs/cbox/settings'])->assertNotFound();
});

it('lets a page through when the token is bound to the URL\'s organization', function (): void {
    $this->getJson('/orgs/cbox/settings')->assertOk()->assertJson(['url' => 'orgs/cbox/settings']);
    $this->getJson('/orgs')->assertOk();
});

it('re-binds the token silently when the URL names another organization, then returns to it', function (): void {
    $response = $this->get('/orgs/acme/settings?tab=members');

    $response->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($response->headers->get('Location'))->toStartWith(CboxIdFake::ISSUER)
        ->and($query['organization'])->toBe('org_2')
        ->and($query)->not->toHaveKey('prompt')
        ->and(session('url.intended'))->toBe('http://localhost/orgs/acme/settings?tab=members');

    // The callback came back bound to Acme: the page renders.
    actAsMemberOf($this->cbox, 'org_2');
    $this->get('/orgs/acme/settings?tab=members')->assertOk();
    expect(session()->has(BindOrganizationFromUrl::ATTEMPT_KEY))->toBeFalse();
});

it('asks an Inertia visit to make the round trip as a full page load', function (): void {
    $this->get('/orgs/acme', ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');

    session()->forget(BindOrganizationFromUrl::ATTEMPT_KEY);
    $this->getJson('/orgs/acme')->assertStatus(409)->assertJsonPath('error', 'organization_rebind');
});

it('refuses rather than loops when Cbox ID comes back still bound elsewhere', function (): void {
    $this->get('/orgs/acme')->assertRedirect();
    $this->get('/orgs/acme')->assertForbidden();
    $this->getJson('/orgs/acme')->assertForbidden()->assertJsonPath('error.type', 'organization_mismatch');

    $this->travel(BindOrganizationFromUrl::ATTEMPT_WINDOW + 1)->seconds();
    $this->get('/orgs/acme')->assertRedirect();
});

it('answers 404 for an organization the person does not belong to', function (): void {
    $this->get('/orgs/globex/settings')->assertNotFound();
});

it('pins a support session to its organization', function (): void {
    actAsMemberOf($this->cbox, 'org_1', actor: 'usr_support');

    $this->get('/orgs/acme')->assertForbidden();
});

it('reads another route parameter when told', function (): void {
    $this->get('/teams/acme')->assertRedirect();
    session()->forget(BindOrganizationFromUrl::ATTEMPT_KEY);
    $this->get('/teams/cbox')->assertOk();
});

it('creates an organization with the person as Owner and lands on its details page', function (): void {
    $response = $this->postJson('/shell/context/organization', ['name' => 'Globex'])->assertCreated();

    $id = $response->json('id');
    expect($response->json())->toBe(['id' => $id, 'detailsUrl' => '/orgs/'.$id.'/settings']);

    $this->cbox->management()->assertCalled('createOrganization', fn (array $args) => $args[0] instanceof NewOrganization && $args[0]->name === 'Globex' && $args[0]->ownerUserId === 'usr_1');

    // Remembered locally: the menu and the URL find it before any webhook mirrors it.
    $this->getJson('/shell/context/organization?q=glob')->assertJsonPath('data.0.id', $id);
});

it('says why when Cbox ID refuses the name or is unreachable', function (): void {
    $refusal = new ValidationFailed('The given data was invalid.');
    $refusal->errors = ['slug' => ['That slug is already in use.']];
    $this->cbox->management()->failNext('createOrganization', $refusal);

    $this->postJson('/shell/context/organization', ['name' => 'Cbox'])
        ->assertUnprocessable()->assertJsonPath('errors.name.0', 'That slug is already in use.');
});

it('falls back to Cbox ID\'s hosted create step without a management key', function (): void {
    app()->instance(Management::class, new HttpManagementClient('', '', 5));
    app()->forgetInstance(OrganizationLevel::class);
    Console::shell()->levels(app(OrganizationLevel::class));

    $response = $this->postJson('/shell/context/organization', ['name' => 'Globex'])->assertCreated();
    parse_str((string) parse_url($response->json('detailsUrl'), PHP_URL_QUERY), $query);

    expect($response->json('id'))->toBeNull()
        ->and($response->json('detailsUrl'))->toStartWith(CboxIdFake::ISSUER)
        ->and($query['prompt'])->toBe('create_organization')
        ->and(session('url.intended'))->toBe('http://localhost/orgs');
});

it('pages and searches the session directory, falling back to the id for a missing slug', function (): void {
    $this->cbox->actingAs('usr_1', organization: 'org_3', organizationName: 'Initech', claims: [
        'organizations' => [['id' => 'org_1', 'name' => 'Cbox', 'slug' => 'cbox'], ['id' => 'org_2', 'name' => 'Acme']],
    ]);
    $directory = app(OrganizationDirectory::class);
    $request = Request::create('/');

    expect($directory)->toBeInstanceOf(SessionOrganizationDirectory::class);

    $first = $directory->organizations($request, limit: 2);
    $second = $directory->organizations($request, cursor: $first->nextCursor, limit: 2);

    expect(array_map(fn ($o) => $o->id, $first->items))->toBe(['org_1', 'org_2'])
        ->and($first->nextCursor)->toBe('2')
        ->and($second->items[0])->toEqual(new MirroredOrganization('org_3', 'org_3', 'Initech', OrganizationRole::Member))
        ->and($second->nextCursor)->toBeNull()
        ->and($directory->findBySlug($request, 'org_2')?->name)->toBe('Acme')
        ->and($directory->organizations($request, 'ini')->items[0]->id)->toBe('org_3');
});

it('knows no organizations without a principal', function (): void {
    $this->cbox->actingAsGuest();
    app('auth')->forgetGuards();

    $level = app(OrganizationLevel::class);
    $request = Request::create('/');

    expect($level->list($request, new ContextQuery)->items)->toBe([])
        ->and($level->find($request, 'org_1', []))->toBeNull()
        ->and($level->url(new ContextItem('org_1', 'Cbox', 'cbox'), []))->toBe('/orgs/cbox')
        ->and($level->allUrl([]))->toBe('/orgs');
});
