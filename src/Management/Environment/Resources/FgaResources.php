<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\FgaObject;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `fga.resources.*` on the environment plane. */
class FgaResources
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List the resources of one type a subject has a relation on (e.g. every document alice can view), through all inheritance; sorted ids, paged with after.
     *
     * `GET /fga/resources` · action `fga.resources.list` · scope `fga:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{resource_type: string, relation: string, subject_type: string, subject_id: string, subject_relation?: string, consistency_token?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<FgaObject>|PendingApprovalResult<Page<FgaObject>> : Page<FgaObject>)
     */
    public function list(array $query, ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('fga.resources.list'), [], $query, $options, Value::dto(FgaObject::fromArray(...)));
    }

    /**
     * Every item of `fga.resources.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{resource_type: string, relation: string, subject_type: string, subject_id: string, subject_relation?: string, consistency_token?: string|null, limit?: int}  $query
     * @return Generator<int, FgaObject, mixed, void>
     */
    public function listAll(array $query, ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('fga.resources.list'), [], $query, $options, Value::dto(FgaObject::fromArray(...)));
    }
}
