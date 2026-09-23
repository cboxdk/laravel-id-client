<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management;

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ManagementApiException;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Exceptions\ResourceNotFound;
use Cbox\Id\Client\Management\Data\Api;
use Cbox\Id\Client\Management\Data\ApiChanges;
use Cbox\Id\Client\Management\Data\ApiKey;
use Cbox\Id\Client\Management\Data\App;
use Cbox\Id\Client\Management\Data\AppBlueprint;
use Cbox\Id\Client\Management\Data\Invitation;
use Cbox\Id\Client\Management\Data\Member;
use Cbox\Id\Client\Management\Data\NewApi;
use Cbox\Id\Client\Management\Data\NewApp;
use Cbox\Id\Client\Management\Data\NewInvitation;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\Data\NewSupportSession;
use Cbox\Id\Client\Management\Data\Organization;
use Cbox\Id\Client\Management\Data\OrganizationChanges;
use Cbox\Id\Client\Management\Data\Page;
use Cbox\Id\Client\Management\Data\Role;
use Cbox\Id\Client\Management\Data\RoleAssignment;
use Cbox\Id\Client\Management\Data\SupportSession;
use Cbox\Id\Client\Support\Claims;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * {@see Management} over HTTP: `Bearer cbid_env_…` against `{issuer}/api/v1`.
 *
 * The key is an ENVIRONMENT key and is bound to the host it was minted for, so the base
 * URL is the environment's own host — the issuer — unless `cbox-id-client.management.url`
 * says otherwise. It is a server-side secret with provisioning power over every tenant in
 * the environment: never put it in a browser, never log it.
 *
 * Every success is `{"data": …}`; every failure is `{"error", "message"}` and becomes a
 * {@see ManagementApiException} carrying both, so a caller branches on the code.
 */
class HttpManagementClient implements Management
{
    public function __construct(
        private readonly string $baseUrl,
        #[\SensitiveParameter]
        private readonly string $key,
        private readonly int $timeout = 10,
    ) {}

    public function organizations(?string $after = null, int $limit = 50): Page
    {
        return Page::fromResponse($this->get('/organizations', $this->cursor($after, $limit)), Organization::fromArray(...));
    }

    public function organization(string $organizationId): Organization
    {
        return Organization::fromArray($this->data($this->get('/organizations/'.$this->id($organizationId))));
    }

    public function createOrganization(NewOrganization $organization): Organization
    {
        return Organization::fromArray($this->data($this->send('post', '/organizations', $organization->toArray())));
    }

    public function updateOrganization(string $organizationId, OrganizationChanges $changes): Organization
    {
        return Organization::fromArray($this->data($this->send('patch', '/organizations/'.$this->id($organizationId), $changes->toArray())));
    }

    public function archiveOrganization(string $organizationId): void
    {
        $this->send('delete', '/organizations/'.$this->id($organizationId));
    }

    public function members(string $organizationId, ?string $after = null, int $limit = 50): Page
    {
        return Page::fromResponse($this->get($this->org($organizationId).'/members', $this->cursor($after, $limit)), Member::fromArray(...));
    }

    public function addMember(string $organizationId, string $userId, OrganizationRole $role = OrganizationRole::Member): Member
    {
        return Member::fromArray($this->data($this->send('post', $this->org($organizationId).'/members', [
            'user_id' => $userId,
            'role' => $role->value,
        ])));
    }

    public function updateMember(string $organizationId, string $userId, OrganizationRole $role): Member
    {
        return Member::fromArray($this->data($this->send('patch', $this->org($organizationId).'/members/'.$this->id($userId), [
            'role' => $role->value,
        ])));
    }

    public function removeMember(string $organizationId, string $userId): void
    {
        $this->send('delete', $this->org($organizationId).'/members/'.$this->id($userId));
    }

    public function transferOwnership(string $organizationId, string $userId): void
    {
        $this->send('post', $this->org($organizationId).'/transfer-ownership', ['user_id' => $userId]);
    }

    public function invitations(string $organizationId, ?string $after = null, int $limit = 50): Page
    {
        return Page::fromResponse($this->get($this->org($organizationId).'/invitations', $this->cursor($after, $limit)), Invitation::fromArray(...));
    }

    public function invite(string $organizationId, NewInvitation $invitation): Invitation
    {
        return Invitation::fromArray($this->data($this->send('post', $this->org($organizationId).'/invitations', $invitation->toArray())));
    }

    public function revokeInvitation(string $organizationId, string $invitationId): void
    {
        $this->send('delete', $this->org($organizationId).'/invitations/'.$this->id($invitationId));
    }

    public function resendInvitation(string $organizationId, string $invitationId): void
    {
        $this->send('post', $this->org($organizationId).'/invitations/'.$this->id($invitationId).'/resend');
    }

    public function memberRoles(string $organizationId, string $userId): array
    {
        return array_map(
            RoleAssignment::fromArray(...),
            Claims::objects($this->get($this->org($organizationId).'/members/'.$this->id($userId).'/roles'), 'data'),
        );
    }

    public function assignRole(string $organizationId, string $userId, string $roleId): void
    {
        $this->send('put', $this->org($organizationId).'/members/'.$this->id($userId).'/roles/'.$this->id($roleId));
    }

    public function unassignRole(string $organizationId, string $userId, string $roleId): void
    {
        $this->send('delete', $this->org($organizationId).'/members/'.$this->id($userId).'/roles/'.$this->id($roleId));
    }

    public function roles(): array
    {
        return array_map(Role::fromArray(...), Claims::objects($this->get('/roles'), 'data'));
    }

    public function hasEnvironmentRole(string $userId, string $roleId): bool
    {
        try {
            $this->get($this->environmentRole($userId, $roleId));
        } catch (ResourceNotFound) {
            return false;
        }

        return true;
    }

    public function grantEnvironmentRole(string $userId, string $roleId): void
    {
        $this->send('put', $this->environmentRole($userId, $roleId));
    }

    public function revokeEnvironmentRole(string $userId, string $roleId): void
    {
        $this->send('delete', $this->environmentRole($userId, $roleId));
    }

    public function apps(?string $after = null, int $limit = 50): Page
    {
        return Page::fromResponse($this->get('/apps', $this->cursor($after, $limit)), App::fromArray(...));
    }

    public function createApp(NewApp $app): App
    {
        return App::fromArray($this->data($this->send('post', '/apps', $app->toArray())));
    }

    public function appBlueprint(string $appId): AppBlueprint
    {
        return new AppBlueprint($appId, $this->data($this->get('/apps/'.$this->id($appId).'/blueprint')));
    }

    public function apis(?string $after = null, int $limit = 50): Page
    {
        return Page::fromResponse($this->get('/apis', $this->cursor($after, $limit)), Api::fromArray(...));
    }

    public function createApi(NewApi $api): Api
    {
        return Api::fromArray($this->data($this->send('post', '/apis', $api->toArray())));
    }

    public function updateApi(string $apiId, ApiChanges $changes): Api
    {
        return Api::fromArray($this->data($this->send('patch', '/apis/'.$this->id($apiId), $changes->toArray())));
    }

    public function deleteApi(string $apiId): void
    {
        $this->send('delete', '/apis/'.$this->id($apiId));
    }

    public function apiKeys(string $organizationId, ?string $after = null, int $limit = 50): Page
    {
        return Page::fromResponse($this->get($this->org($organizationId).'/api-keys', $this->cursor($after, $limit)), ApiKey::fromArray(...));
    }

    public function revokeApiKey(string $apiKeyId): void
    {
        $this->send('delete', '/api-keys/'.$this->id($apiKeyId));
    }

    public function startSupportSession(NewSupportSession $session): SupportSession
    {
        return SupportSession::fromArray($this->data($this->send('post', '/support-sessions', $session->toArray())));
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload  JSON body, or the query string on a GET
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $payload = []): array
    {
        if ($this->key === '') {
            throw NotConfigured::key('management.key', 'call the environment management API');
        }

        if ($this->baseUrl === '') {
            throw NotConfigured::key('issuer', 'call the environment management API');
        }

        $request = Http::withToken($this->key)->acceptJson()->asJson()->timeout($this->timeout);
        $url = $this->baseUrl.$path;

        try {
            /** @var Response $response */
            $response = match ($method) {
                'get' => $request->get($url, $payload),
                'post' => $request->post($url, $payload),
                'put' => $request->put($url, $payload),
                'patch' => $request->patch($url, $payload),
                default => $request->delete($url, $payload),
            };
        } catch (Throwable $e) {
            throw ManagementApiException::unreachable($this->baseUrl, $e);
        }

        if (! $response->successful()) {
            throw ManagementApiException::fromResponse(strtoupper($method).' '.$path, $response);
        }

        return Claims::normalize($response->json());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function data(array $body): array
    {
        return Claims::object($body, 'data');
    }

    /**
     * @return array<string, scalar>
     */
    private function cursor(?string $after, int $limit): array
    {
        $query = ['limit' => max(1, min(100, $limit))];

        if ($after !== null && $after !== '') {
            $query['after'] = $after;
        }

        return $query;
    }

    private function org(string $organizationId): string
    {
        return '/organizations/'.$this->id($organizationId);
    }

    private function environmentRole(string $userId, string $roleId): string
    {
        return '/users/'.$this->id($userId).'/environment-roles/'.$this->id($roleId);
    }

    /** Path segments are encoded: an id is data, and data never gets to add a path. */
    private function id(string $id): string
    {
        return rawurlencode($id);
    }
}
