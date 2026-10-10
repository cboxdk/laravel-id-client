<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\FgaCheck;
use Cbox\Id\Client\Management\Environment\Schemas\FgaCheckBatch;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `fga.*` on the environment plane. */
class Fga
{
    public readonly FgaResources $resources;

    public readonly FgaSchema $schema;

    public readonly FgaSubjects $subjects;

    public readonly FgaTuples $tuples;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->resources = new FgaResources($transport);
        $this->schema = new FgaSchema($transport);
        $this->subjects = new FgaSubjects($transport);
        $this->tuples = new FgaTuples($transport);
    }

    /**
     * Check whether a subject has a relation on a resource — directly, through computed relations, parents or nested groups. Optionally at least as fresh as a consistency_token.
     *
     * `GET /fga/check` · action `fga.check` · scope `fga:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{resource_type: string, resource_id: string, relation: string, subject_type: string, subject_id: string, subject_relation?: string, consistency_token?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<FgaCheck>|PendingApprovalResult<ApiResponse<FgaCheck>> : ApiResponse<FgaCheck>)
     */
    public function check(array $query, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('fga.check'), [], $query, $options, Value::dto(FgaCheck::fromArray(...)));
    }

    /**
     * Run 1–100 checks in one round trip, each written resource#relation@subject (document:readme#viewer@user:alice), all at the same revision, answered in order.
     *
     * `GET /fga/check/batch` · action `fga.check.batch` · scope `fga:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{checks: list<string>, consistency_token?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<FgaCheckBatch>|PendingApprovalResult<ApiResponse<FgaCheckBatch>> : ApiResponse<FgaCheckBatch>)
     */
    public function checkBatch(array $query, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('fga.check.batch'), [], $query, $options, Value::dto(FgaCheckBatch::fromArray(...)));
    }
}
