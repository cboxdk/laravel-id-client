<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AccessReview;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `access_reviews.*` on the environment plane. */
class AccessReviews
{
    public readonly AccessReviewsItems $items;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->items = new AccessReviewsItems($transport);
    }

    /**
     * Close an access review and apply it: every revoke is carried out, and undecided items get the review's pending policy (revoke by default).
     *
     * `POST /access-reviews/{id}/close` · action `access_reviews.close` · scope `governance:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AccessReview>|PendingApprovalResult<ApiResponse<AccessReview>> : ApiResponse<AccessReview>)
     */
    public function close(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('access_reviews.close'), [$id], $body, $options, Value::dto(AccessReview::fromArray(...)));
    }

    /**
     * Open an access review of one organization's roles and memberships — or of staff roles — for someone to certify or revoke.
     *
     * `POST /access-reviews` · action `access_reviews.create` · scope `governance:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, covers?: 'organization'|'staff', organization_id?: string|null, due_in_days?: int}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AccessReview>|PendingApprovalResult<ApiResponse<AccessReview>> : ApiResponse<AccessReview>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('access_reviews.create'), [], $body, $options, Value::dto(AccessReview::fromArray(...)));
    }

    /**
     * Read one access review: its status, due date, pending-item policy and item count.
     *
     * `GET /access-reviews/{id}` · action `access_reviews.get` · scope `governance:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<AccessReview>|PendingApprovalResult<ApiResponse<AccessReview>> : ApiResponse<AccessReview>)
     */
    public function get(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('access_reviews.get'), [$id], $query, $options, Value::dto(AccessReview::fromArray(...)));
    }

    /**
     * List access reviews (certification campaigns), open and closed, optionally those covering one organization.
     *
     * `GET /access-reviews` · action `access_reviews.list` · scope `governance:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<AccessReview>|PendingApprovalResult<Page<AccessReview>> : Page<AccessReview>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('access_reviews.list'), [], $query, $options, Value::dto(AccessReview::fromArray(...)));
    }

    /**
     * Every item of `access_reviews.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, AccessReview, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('access_reviews.list'), [], $query, $options, Value::dto(AccessReview::fromArray(...)));
    }
}
