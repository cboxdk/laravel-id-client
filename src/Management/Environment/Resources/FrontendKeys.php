<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\FrontendKey;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `frontend_keys.*` on the environment plane. */
class FrontendKeys
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Create a publishable frontend key for a browser app, usable only from the origins listed. The key is public by design.
     *
     * `POST /frontend-keys` · action `frontend_keys.create` · scope `frontend_keys:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, mode: 'test'|'live', origins: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<FrontendKey>|PendingApprovalResult<ApiResponse<FrontendKey>> : ApiResponse<FrontendKey>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('frontend_keys.create'), [], $body, $options, Value::dto(FrontendKey::fromArray(...)));
    }

    /**
     * List the publishable frontend keys browser apps present to the Frontend API, in full, with their allowed origins.
     *
     * `GET /frontend-keys` · action `frontend_keys.list` · scope `frontend_keys:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<FrontendKey>|PendingApprovalResult<Page<FrontendKey>> : Page<FrontendKey>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('frontend_keys.list'), [], $query, $options, Value::dto(FrontendKey::fromArray(...)));
    }

    /**
     * Every item of `frontend_keys.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, FrontendKey, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('frontend_keys.list'), [], $query, $options, Value::dto(FrontendKey::fromArray(...)));
    }

    /**
     * Revoke a publishable frontend key. Pages still holding it stop working immediately.
     *
     * `DELETE /frontend-keys/{id}` · action `frontend_keys.revoke` · scope `frontend_keys:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('frontend_keys.revoke'), [$id], [], $options, Value::none(...));
    }

    /**
     * Replace the exact list of origins allowed to present a publishable frontend key. Live on the next request.
     *
     * `PUT /frontend-keys/{id}/origins` · action `frontend_keys.set_origins` · scope `frontend_keys:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{origins: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<FrontendKey>|PendingApprovalResult<ApiResponse<FrontendKey>> : ApiResponse<FrontendKey>)
     */
    public function setOrigins(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('frontend_keys.set_origins'), [$id], $body, $options, Value::dto(FrontendKey::fromArray(...)));
    }
}
