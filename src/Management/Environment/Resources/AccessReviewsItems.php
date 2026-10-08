<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AccessReviewItem;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `access_reviews.items.*` on the environment plane. */
class AccessReviewsItems
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Certify or revoke one item of an open access review. Revokes are applied when the review closes.
     *
     * `POST /access-reviews/{id}/items/{item_id}` · action `access_reviews.items.decide` · scope `governance:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{decision: 'certified'|'revoked', note?: string|null, organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AccessReviewItem>|PendingApprovalResult<ApiResponse<AccessReviewItem>> : ApiResponse<AccessReviewItem>)
     */
    public function decide(string $id, string $itemId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('access_reviews.items.decide'), [$id, $itemId], $body, $options, Value::dto(AccessReviewItem::fromArray(...)));
    }

    /**
     * List the items an access review asks someone to certify or revoke: who holds what, and the decision on each.
     *
     * `GET /access-reviews/{id}/items` · action `access_reviews.items.list` · scope `governance:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<AccessReviewItem>|PendingApprovalResult<Page<AccessReviewItem>> : Page<AccessReviewItem>)
     */
    public function list(string $id, array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('access_reviews.items.list'), [$id], $query, $options, Value::dto(AccessReviewItem::fromArray(...)));
    }

    /**
     * Every item of `access_reviews.items.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, AccessReviewItem, mixed, void>
     */
    public function listAll(string $id, array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('access_reviews.items.list'), [$id], $query, $options, Value::dto(AccessReviewItem::fromArray(...)));
    }
}
