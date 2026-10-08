<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\ErasureReceipt;
use Cbox\Id\Client\Management\Environment\Schemas\User;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `users.*` on the environment plane. */
class Users
{
    public readonly UsersEnvironmentRoles $environmentRoles;

    public readonly UsersMfa $mfa;

    public readonly UsersPassword $password;

    public readonly UsersPasswordReset $passwordReset;

    public readonly UsersSessions $sessions;

    public readonly UsersVerification $verification;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->environmentRoles = new UsersEnvironmentRoles($transport);
        $this->mfa = new UsersMfa($transport);
        $this->password = new UsersPassword($transport);
        $this->passwordReset = new UsersPasswordReset($transport);
        $this->sessions = new UsersSessions($transport);
        $this->verification = new UsersVerification($transport);
    }

    /**
     * Create a user
     *
     * Password is optional — omit it to create a passwordless identity the user completes via an invite/magic-link.
     *
     * `POST /users` · action `users.create` · scope `users:write`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{email: string, name?: string|null, password?: string|null, send_sign_in_link?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<User>|PendingApprovalResult<ApiResponse<User>> : ApiResponse<User>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.create'), [], $body, $options, Value::dto(User::fromArray(...)));
    }

    /**
     * Deactivate a user
     *
     * A **soft disable** — the identity can no longer
     * authenticate, but is never hard-deleted — and every OAuth grant they hold is
     * revoked, which reactivating them does not bring back. Returns the user in its new
     * `disabled` state. Idempotent. The console's "Deactivate" is this action.
     *
     * `DELETE /users/{id}` · action `users.deactivate` · scope `users:write`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<User>|PendingApprovalResult<ApiResponse<User>> : ApiResponse<User>)
     */
    public function deactivate(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.deactivate'), [$id], [], $options, Value::dto(User::fromArray(...)));
    }

    /**
     * Erase a person (GDPR Art. 17): revoke their sessions and tokens, delete their credentials, memberships and personal data, and pseudonymise their account. Cannot be undone.
     *
     * `POST /users/{id}/erase` · action `users.erase` · scope `users:erase` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<ErasureReceipt>|PendingApprovalResult<ApiResponse<ErasureReceipt>> : ApiResponse<ErasureReceipt>)
     */
    public function erase(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.erase'), [$id], [], $options, Value::dto(ErasureReceipt::fromArray(...)));
    }

    /**
     * Get a user
     *
     * `GET /users/{id}` · action `users.get` · scope `users:read`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<User>|PendingApprovalResult<ApiResponse<User>> : ApiResponse<User>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.get'), [$id], [], $options, Value::dto(User::fromArray(...)));
    }

    /**
     * List users
     *
     * A page at a time, in id order. Narrow it the ways you
     * look for somebody: `email` (exactly that address, case-insensitive), `q` (a fragment
     * of the address or the name) and `status`. Filters combine.
     *
     * `GET /users` · action `users.list` · scope `users:read`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string, email?: string, q?: string, status?: 'active'|'disabled'|'locked'}  $query
     * @return ($options is ReturnPendingApproval ? Page<User>|PendingApprovalResult<Page<User>> : Page<User>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('users.list'), [], $query, $options, Value::dto(User::fromArray(...)));
    }

    /**
     * Every item of `users.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int, email?: string, q?: string, status?: 'active'|'disabled'|'locked'}  $query
     * @return Generator<int, User, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('users.list'), [], $query, $options, Value::dto(User::fromArray(...)));
    }

    /**
     * Reactivate a deactivated user so they can sign in again. Grants revoked on deactivation stay revoked.
     *
     * `POST /users/{id}/reactivate` · action `users.reactivate` · scope `users:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<User>|PendingApprovalResult<ApiResponse<User>> : ApiResponse<User>)
     */
    public function reactivate(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.reactivate'), [$id], [], $options, Value::dto(User::fromArray(...)));
    }

    /**
     * Change a user's name and/or email address. A changed address must be verified again.
     *
     * `PATCH /users/{id}` · action `users.update` · scope `users:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name?: string|null, email?: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<User>|PendingApprovalResult<ApiResponse<User>> : ApiResponse<User>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.update'), [$id], $body, $options, Value::dto(User::fromArray(...)));
    }

    /**
     * Mark a user's email address verified without the emailed link. It can then be used to recover the account.
     *
     * `POST /users/{id}/verify` · action `users.verify` · scope `users:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<User>|PendingApprovalResult<ApiResponse<User>> : ApiResponse<User>)
     */
    public function verify(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.verify'), [$id], [], $options, Value::dto(User::fromArray(...)));
    }
}
