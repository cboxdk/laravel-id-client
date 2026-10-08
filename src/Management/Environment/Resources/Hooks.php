<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\InlineHook;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `hooks.*` on the environment plane. */
class Hooks
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Register an inline hook at a hook point (token minting, login, registration, password change). Returns its signing secret once.
     *
     * `POST /hooks` · action `hooks.create` · scope `hooks:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{hook_point: 'token_minting'|'post_login'|'pre_registration'|'post_registration'|'pre_password_change'|'post_password_change', url: string, organization_id?: string|null, environment_wide?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<InlineHook>|PendingApprovalResult<ApiResponse<InlineHook>> : ApiResponse<InlineHook>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('hooks.create'), [], $body, $options, Value::dto(InlineHook::fromArray(...)));
    }

    /**
     * Remove an inline hook. It is no longer called at its hook point.
     *
     * `DELETE /hooks/{id}` · action `hooks.delete` · scope `hooks:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('hooks.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Get one inline hook: its URL, hook point, owner and whether it is active. Never its signing secret.
     *
     * `GET /hooks/{id}` · action `hooks.get` · scope `hooks:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<InlineHook>|PendingApprovalResult<ApiResponse<InlineHook>> : ApiResponse<InlineHook>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('hooks.get'), [$id], [], $options, Value::dto(InlineHook::fromArray(...)));
    }

    /**
     * List the inline hooks — endpoints called during sign-in and token issuance — with their hook point, owner and whether each is active.
     *
     * `GET /hooks` · action `hooks.list` · scope `hooks:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<InlineHook>|PendingApprovalResult<Page<InlineHook>> : Page<InlineHook>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('hooks.list'), [], $query, $options, Value::dto(InlineHook::fromArray(...)));
    }

    /**
     * Every item of `hooks.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, InlineHook, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('hooks.list'), [], $query, $options, Value::dto(InlineHook::fromArray(...)));
    }

    /**
     * Pause (active: false) or activate (active: true) an inline hook.
     *
     * `PATCH /hooks/{id}` · action `hooks.update` · scope `hooks:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{active: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<InlineHook>|PendingApprovalResult<ApiResponse<InlineHook>> : ApiResponse<InlineHook>)
     */
    public function update(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('hooks.update'), [$id], $body, $options, Value::dto(InlineHook::fromArray(...)));
    }
}
