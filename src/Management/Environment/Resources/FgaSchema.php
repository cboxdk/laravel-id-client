<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\FgaSchema as FgaSchemaSchema;
use Cbox\Id\Client\Management\Environment\Schemas\FgaSchemaValidation;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `fga.schema.*` on the environment plane. */
class FgaSchema
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read this environment's fine-grained authorization schema: the source text, its parsed types and relations, its version, and the current consistency token.
     *
     * `GET /fga/schema` · action `fga.schema.get` · scope `fga:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<FgaSchemaSchema>|PendingApprovalResult<ApiResponse<FgaSchemaSchema>> : ApiResponse<FgaSchemaSchema>)
     */
    public function get(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('fga.schema.get'), [], [], $options, Value::dto(FgaSchemaSchema::fromArray(...)));
    }

    /**
     * Replace this environment's fine-grained authorization schema (types, relations and how each is decided). Changes the answer to every check at once; refused if invalid or if existing tuples would no longer fit.
     *
     * `PUT /fga/schema` · action `fga.schema.update` · scope `fga:schema` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{schema: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<FgaSchemaSchema>|PendingApprovalResult<ApiResponse<FgaSchemaSchema>> : ApiResponse<FgaSchemaSchema>)
     */
    public function update(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('fga.schema.update'), [], $body, $options, Value::dto(FgaSchemaSchema::fromArray(...)));
    }

    /**
     * Check a fine-grained authorization schema without saving it: every error by line, or the parsed types and canonical text.
     *
     * `GET /fga/schema/validate` · action `fga.schema.validate` · scope `fga:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{schema: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<FgaSchemaValidation>|PendingApprovalResult<ApiResponse<FgaSchemaValidation>> : ApiResponse<FgaSchemaValidation>)
     */
    public function validate(array $query, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('fga.schema.validate'), [], $query, $options, Value::dto(FgaSchemaValidation::fromArray(...)));
    }
}
