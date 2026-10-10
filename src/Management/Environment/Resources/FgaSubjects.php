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

/** `fga.subjects.*` on the environment plane. */
class FgaSubjects
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List the subjects of one type that have a relation on a resource (e.g. every user who can view the readme), groups and inheritance expanded; sorted ids, paged with after.
     *
     * `GET /fga/subjects` · action `fga.subjects.list` · scope `fga:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{resource_type: string, resource_id: string, relation: string, subject_type: string, consistency_token?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<FgaObject>|PendingApprovalResult<Page<FgaObject>> : Page<FgaObject>)
     */
    public function list(array $query, ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('fga.subjects.list'), [], $query, $options, Value::dto(FgaObject::fromArray(...)));
    }

    /**
     * Every item of `fga.subjects.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{resource_type: string, resource_id: string, relation: string, subject_type: string, consistency_token?: string|null, limit?: int}  $query
     * @return Generator<int, FgaObject, mixed, void>
     */
    public function listAll(array $query, ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('fga.subjects.list'), [], $query, $options, Value::dto(FgaObject::fromArray(...)));
    }
}
