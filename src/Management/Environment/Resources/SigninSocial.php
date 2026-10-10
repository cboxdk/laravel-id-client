<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\OfferedSocialProviders;
use Cbox\Id\Client\Management\Environment\Schemas\SocialProvider;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `signin.social.*` on the environment plane. */
class SigninSocial
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Stop offering a social login provider. People who used it keep their accounts.
     *
     * `DELETE /sign-in/social-providers/{id}` · action `signin.social.delete` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.social.delete'), [$id], $body, $options, Value::none(...));
    }

    /**
     * Turn a social login provider off without removing it. People who used it keep their accounts.
     *
     * `POST /sign-in/social-providers/{id}/disable` · action `signin.social.disable` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SocialProvider>|PendingApprovalResult<ApiResponse<SocialProvider>> : ApiResponse<SocialProvider>)
     */
    public function disable(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.social.disable'), [$id], $body, $options, Value::dto(SocialProvider::fromArray(...)));
    }

    /**
     * Turn a social login provider that was turned off back on, with the credentials it already has.
     *
     * `POST /sign-in/social-providers/{id}/enable` · action `signin.social.enable` · scope `signin:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SocialProvider>|PendingApprovalResult<ApiResponse<SocialProvider>> : ApiResponse<SocialProvider>)
     */
    public function enable(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.social.enable'), [$id], $body, $options, Value::dto(SocialProvider::fromArray(...)));
    }

    /**
     * Turn one of the environment's social login providers off (or back on) for one organization's sign-in page.
     *
     * `PUT /sign-in/social-providers/inherited/{provider}` · action `signin.social.inherit` · scope `signin:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id: string, offered: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<OfferedSocialProviders>|PendingApprovalResult<ApiResponse<OfferedSocialProviders>> : ApiResponse<OfferedSocialProviders>)
     */
    public function inherit(string $provider, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.social.inherit'), [$provider], $body, $options, Value::dto(OfferedSocialProviders::fromArray(...)));
    }

    /**
     * List the social login providers (Google, GitHub, Apple…) set up in this environment — the environment's own and organizations' — optionally for one organization.
     *
     * `GET /sign-in/social-providers` · action `signin.social.list` · scope `signin:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string, level?: 'environment'|'organization', limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<SocialProvider>|PendingApprovalResult<Page<SocialProvider>> : Page<SocialProvider>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('signin.social.list'), [], $query, $options, Value::dto(SocialProvider::fromArray(...)));
    }

    /**
     * Every item of `signin.social.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string, level?: 'environment'|'organization', limit?: int}  $query
     * @return Generator<int, SocialProvider, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('signin.social.list'), [], $query, $options, Value::dto(SocialProvider::fromArray(...)));
    }

    /**
     * The social login buttons one organization's sign-in page shows (or the plain sign-in page's), after inheritance from the environment, with where each comes from.
     *
     * `GET /sign-in/social-providers/offered` · action `signin.social.offered` · scope `signin:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<OfferedSocialProviders>|PendingApprovalResult<ApiResponse<OfferedSocialProviders>> : ApiResponse<OfferedSocialProviders>)
     */
    public function offered(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.social.offered'), [], $query, $options, Value::dto(OfferedSocialProviders::fromArray(...)));
    }

    /**
     * Enable a social login provider (Google, GitHub, Apple…) for the whole environment or for one organization, with its client credentials. The secret is never returned.
     *
     * `POST /sign-in/social-providers` · action `signin.social.set` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, environment_wide?: bool, provider: string, client_id: string, client_secret?: string|null, parameters?: array{directory?: string|null, domain?: string|null, host?: string|null, key_id?: string|null, private_key?: string|null, realm?: string|null, team_id?: string|null}, scopes?: list<string>, reserved_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SocialProvider>|PendingApprovalResult<ApiResponse<SocialProvider>> : ApiResponse<SocialProvider>)
     */
    public function set(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.social.set'), [], $body, $options, Value::dto(SocialProvider::fromArray(...)));
    }

    /**
     * Change a social login provider's client credentials, provider values or extra scopes. Secrets left out keep the ones on file.
     *
     * `PATCH /sign-in/social-providers/{id}` · action `signin.social.update` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, client_id?: string, client_secret?: string|null, parameters?: array{directory?: string|null, domain?: string|null, host?: string|null, key_id?: string|null, private_key?: string|null, realm?: string|null, team_id?: string|null}, scopes?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SocialProvider>|PendingApprovalResult<ApiResponse<SocialProvider>> : ApiResponse<SocialProvider>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.social.update'), [$id], $body, $options, Value::dto(SocialProvider::fromArray(...)));
    }
}
