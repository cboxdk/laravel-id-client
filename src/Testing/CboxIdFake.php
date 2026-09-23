<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Facades\CboxIdManagement;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Contracts\Container\Container;

/**
 * Cbox ID for your test suite — no fake issuer, no HTTP.
 *
 *     $cbox = CboxId::fake();
 *
 *     // Be somebody, in an organization, with permissions (session, gate, middleware):
 *     $cbox->actingAs('user_1', organization: 'org_1', role: OrganizationRole::Admin,
 *         permissions: ['invoices:create']);
 *
 *     // Sign in through your real callback route:
 *     $cbox->signIn('user_1', organization: 'org_1');
 *     $this->get('/auth/callback?code=x&state=y')->assertRedirect('/dashboard');
 *
 *     // Call your API with a real, signed bearer token:
 *     $this->withToken((string) $cbox->token()->permissions(['invoices:read']))->getJson('/api/invoices');
 *
 *     // Provisioning, with assertions:
 *     $cbox->management()->assertInvited('ada@example.com');
 *
 *     // Customer API keys:
 *     $cbox->apiKeys()->add('ctx_live_abc', permissions: ['reports:read']);
 *
 * Installing it swaps the container bindings for the lifetime of the test application;
 * the real `AccessTokenVerifier` stays in place, pointed at an in-memory key, so a token
 * is verified by production code. Any configuration the test left empty is filled with a
 * test value (issuer `https://id.test`, client `cid_test`) — never overwritten.
 */
class CboxIdFake
{
    public const ISSUER = 'https://id.test';

    private readonly FakeIdentityClient $client;

    private readonly FakeManagement $management;

    private readonly FakeApiKeyVerifier $apiKeys;

    private readonly TokenFactory $tokens;

    public function __construct(private readonly Container $app)
    {
        $issuer = $this->fill('issuer', self::ISSUER);
        $clientId = $this->fill('client_id', 'cid_test');
        $this->fill('client_secret', 'csec_test');
        $this->fill('redirect', 'http://localhost/auth/callback');

        $audience = config('cbox-id-client.audience');
        $audience = is_string($audience) && $audience !== '' ? $audience : $issuer;

        $discovery = new FakeDiscovery($issuer, TestKeys::pair()['jwks']);
        $this->tokens = new TokenFactory($issuer, $audience, $clientId);

        $config = config('cbox-id-client');
        $sessions = $this->app->make(SessionIdentityStore::class);

        $this->client = new FakeIdentityClient(is_array($config) ? $this->stringKeyed($config) : [], $discovery, $sessions, $this->tokens);
        $this->management = new FakeManagement;
        $this->apiKeys = new FakeApiKeyVerifier($clientId);

        $this->app->instance(IdentityClient::class, $this->client);
        $this->app->instance(AccessTokenVerifier::class, new AccessTokenVerifier($discovery, $issuer, $audience));
        $this->app->instance(Management::class, $this->management);
        $this->app->instance(VerifiesApiKeys::class, $this->apiKeys);

        CboxId::swap($this->client);
        CboxIdManagement::swap($this->management);
    }

    /**
     * Act as somebody for the rest of the test: the permission gate, `cbox-id.org`,
     * `cbox-id.permission` and `CboxId::principal()` all see this principal, whoever is
     * (or is not) logged in locally.
     *
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $claims  anything else the principal should carry
     */
    public function actingAs(
        Principal|string $subject = 'user_test',
        ?string $organization = 'org_test',
        OrganizationRole|string|null $role = OrganizationRole::Member,
        array $permissions = [],
        array $roles = [],
        ?string $organizationName = null,
        ?string $actor = null,
        array $claims = [],
    ): Principal {
        $principal = $subject instanceof Principal
            ? $subject
            : new Identity($subject, $this->claims($subject, $organization, $role, $permissions, $roles, $organizationName, $actor, $claims));

        $this->app->make(CurrentPrincipal::class)->fake($principal);

        return $principal;
    }

    /** Act as nobody: the principal comes from the request and the session again. */
    public function actingAsGuest(): static
    {
        $this->app->make(CurrentPrincipal::class)->fake(null);

        return $this;
    }

    /**
     * Queue the person the next `authenticate()` returns — your callback route runs for
     * real, with this identity.
     *
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $claims
     */
    public function signIn(
        string $subject = 'user_test',
        ?string $organization = 'org_test',
        OrganizationRole|string|null $role = OrganizationRole::Member,
        array $permissions = [],
        array $roles = [],
        ?string $email = null,
        ?string $name = null,
        ?string $organizationName = null,
        ?string $actor = null,
        array $claims = [],
    ): CboxUser {
        $email ??= $subject.'@example.test';

        $user = new CboxUser(
            id: $subject,
            email: $email,
            name: $name,
            organizationId: $organization,
            claims: array_filter(['email' => $email, 'email_verified' => true, 'name' => $name], static fn (mixed $v): bool => $v !== null)
                + $this->claims($subject, $organization, $role, $permissions, $roles, $organizationName, $actor, $claims),
            accessToken: $this->tokens->for($subject)->mint(),
            refreshToken: 'rt_fake_'.bin2hex(random_bytes(6)),
            idToken: null,
            expiresIn: 300,
        );

        $this->client->queue($user);

        return $user;
    }

    /** Make the next `authenticate()` fail the way Cbox ID's `?error=` callback would. */
    public function failSignIn(string $error = 'access_denied', ?string $description = null): static
    {
        $this->client->queue(AuthenticationFailed::fromCallback($error, $description));

        return $this;
    }

    /** A factory for real, signed access tokens this application will verify. */
    public function token(): TokenFactory
    {
        return $this->tokens;
    }

    public function management(): FakeManagement
    {
        return $this->management;
    }

    public function apiKeys(): FakeApiKeyVerifier
    {
        return $this->apiKeys;
    }

    public function client(): FakeIdentityClient
    {
        return $this->client;
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function claims(string $subject, ?string $organization, OrganizationRole|string|null $role, array $permissions, array $roles, ?string $organizationName, ?string $actor, array $extra): array
    {
        return array_filter([
            'sub' => $subject,
            'org' => $organization,
            'org_name' => $organization !== null ? ($organizationName ?? $organization) : null,
            'org_role' => $organization !== null ? ($role instanceof OrganizationRole ? $role->value : $role) : null,
            'roles' => $roles,
            'permissions' => $permissions,
            'act' => $actor !== null ? ['sub' => $actor] : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== []) + $extra;
    }

    private function fill(string $key, string $default): string
    {
        $value = config('cbox-id-client.'.$key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        config(['cbox-id-client.'.$key => $default]);

        return $default;
    }

    /**
     * @param  array<mixed>  $config
     * @return array<string, mixed>
     */
    private function stringKeyed(array $config): array
    {
        $out = [];

        foreach ($config as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
