<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\FeatureFlag;
use Cbox\Id\Client\Management\Environment\Schemas\FeatureFlagEvaluation;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `feature_flags.*` on the environment plane. */
class FeatureFlags
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Define a feature flag: a key apps ask about, its default, and who it is on for — named users, named organizations, a rollout percentage.
     *
     * `POST /feature-flags` · action `feature_flags.create` · scope `feature_flags:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{key: string, description?: string|null, enabled?: bool, default_value?: bool, users?: list<array{id: string, enabled?: bool}>, organizations?: list<array{id: string, enabled?: bool}>, rollout_percentage?: int|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<FeatureFlag>|PendingApprovalResult<ApiResponse<FeatureFlag>> : ApiResponse<FeatureFlag>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('feature_flags.create'), [], $body, $options, Value::dto(FeatureFlag::fromArray(...)));
    }

    /**
     * Delete a feature flag and its rules. Its key evaluates to off from now on; tokens already minted keep it until they expire.
     *
     * `DELETE /feature-flags/{id}` · action `feature_flags.delete` · scope `feature_flags:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('feature_flags.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Evaluate every feature flag for a user in an organization: whether each is on, and the rule that decided. For app backends without a token in hand.
     *
     * `GET /feature-flags/evaluate` · action `feature_flags.evaluate` · scope `feature_flags:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{user_id?: string, organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<FeatureFlagEvaluation>|PendingApprovalResult<ApiResponse<FeatureFlagEvaluation>> : ApiResponse<FeatureFlagEvaluation>)
     */
    public function evaluate(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('feature_flags.evaluate'), [], $query, $options, Value::dto(FeatureFlagEvaluation::fromArray(...)));
    }

    /**
     * Read one feature flag: its default, whether it is switched on, and every user, organization and rollout rule.
     *
     * `GET /feature-flags/{id}` · action `feature_flags.get` · scope `feature_flags:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<FeatureFlag>|PendingApprovalResult<ApiResponse<FeatureFlag>> : ApiResponse<FeatureFlag>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('feature_flags.get'), [$id], [], $options, Value::dto(FeatureFlag::fromArray(...)));
    }

    /**
     * List this environment's feature flags with their default, kill switch and targeting rules.
     *
     * `GET /feature-flags` · action `feature_flags.list` · scope `feature_flags:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<FeatureFlag>|PendingApprovalResult<Page<FeatureFlag>> : Page<FeatureFlag>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('feature_flags.list'), [], $query, $options, Value::dto(FeatureFlag::fromArray(...)));
    }

    /**
     * Every item of `feature_flags.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, FeatureFlag, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('feature_flags.list'), [], $query, $options, Value::dto(FeatureFlag::fromArray(...)));
    }

    /**
     * Change a feature flag: switch it on or off for everyone, change its default, or replace its user rules, organization rules or rollout percentage.
     *
     * `PATCH /feature-flags/{id}` · action `feature_flags.update` · scope `feature_flags:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{description?: string|null, enabled?: bool, default_value?: bool, users?: list<array{id: string, enabled?: bool}>, organizations?: list<array{id: string, enabled?: bool}>, rollout_percentage?: int|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<FeatureFlag>|PendingApprovalResult<ApiResponse<FeatureFlag>> : ApiResponse<FeatureFlag>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('feature_flags.update'), [$id], $body, $options, Value::dto(FeatureFlag::fromArray(...)));
    }
}
