<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\FgaTuple;
use Cbox\Id\Client\Management\Environment\Schemas\FgaTupleWrite;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `fga.tuples.*` on the environment plane. */
class FgaTuples
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Delete 1–100 relationship tuples in one atomic batch. Revokes the access they granted, and everything inherited through them, at once.
     *
     * `POST /fga/tuples/delete` · action `fga.tuples.delete` · scope `fga:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{tuples: list<array{resource_type: string, resource_id: string, relation: string, subject: array{type: string, id: string, relation?: string|null}}>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<FgaTupleWrite>|PendingApprovalResult<ApiResponse<FgaTupleWrite>> : ApiResponse<FgaTupleWrite>)
     */
    public function delete(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('fga.tuples.delete'), [], $body, $options, Value::dto(FgaTupleWrite::fromArray(...)));
    }

    /**
     * List stored relationship tuples oldest first, filtered by resource type and id, relation and subject; pass next_cursor as after to page.
     *
     * `GET /fga/tuples` · action `fga.tuples.list` · scope `fga:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{resource_type?: string, resource_id?: string, relation?: string, subject_type?: string, subject_id?: string, subject_relation?: string, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<FgaTuple>|PendingApprovalResult<Page<FgaTuple>> : Page<FgaTuple>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('fga.tuples.list'), [], $query, $options, Value::dto(FgaTuple::fromArray(...)));
    }

    /**
     * Every item of `fga.tuples.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{resource_type?: string, resource_id?: string, relation?: string, subject_type?: string, subject_id?: string, subject_relation?: string, limit?: int}  $query
     * @return Generator<int, FgaTuple, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('fga.tuples.list'), [], $query, $options, Value::dto(FgaTuple::fromArray(...)));
    }

    /**
     * Write 1–100 relationship tuples (resource#relation@subject) in one atomic batch, each checked against the schema. Grants access at once; returns a consistency_token for checks that must see it.
     *
     * `POST /fga/tuples` · action `fga.tuples.write` · scope `fga:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{tuples: list<array{resource_type: string, resource_id: string, relation: string, subject: array{type: string, id: string, relation?: string|null}}>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<FgaTupleWrite>|PendingApprovalResult<ApiResponse<FgaTupleWrite>> : ApiResponse<FgaTupleWrite>)
     */
    public function write(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('fga.tuples.write'), [], $body, $options, Value::dto(FgaTupleWrite::fromArray(...)));
    }
}
