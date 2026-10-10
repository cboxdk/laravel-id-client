<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\RadarRule;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `radar.rules.*` on the environment plane. */
class RadarRules
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Add a Radar rule: when EVERY condition (field, operator, value) holds, allow, challenge or block. Rules run in order after the allow/deny lists and before the built-in rules; the first match decides. Example: country not_in [DK, SE] → challenge.
     *
     * `POST /radar/rules` · action `radar.rules.create` · scope `radar:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, description?: string|null, action: 'allow'|'challenge'|'block', applies_to?: 'all'|'sign_in'|'sign_up', conditions: list<array{field: 'ip'|'country'|'asn'|'as_organization'|'is_hosting'|'is_vpn'|'is_proxy'|'is_tor'|'email'|'email_domain'|'disposable_email'|'user_agent'|'method'|'new_device'|'impossible_travel'|'travel_kmh'|'risk_score'|'ip_attempts_1m'|'ip_attempts_1h'|'ip_distinct_emails_10m'|'ip_failures_1h'|'email_attempts_1h'|'email_failures_1h'|'device_attempts_1h', operator: 'eq'|'neq'|'in'|'not_in'|'gt'|'gte'|'lt'|'lte'|'contains'|'not_contains'|'starts_with'|'ends_with'|'in_cidr'|'not_in_cidr', value?: string, values?: list<string>}>, enabled?: bool, position?: int}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarRule>|PendingApprovalResult<ApiResponse<RadarRule>> : ApiResponse<RadarRule>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.rules.create'), [], $body, $options, Value::dto(RadarRule::fromArray(...)));
    }

    /**
     * Delete a Radar rule. Attempts it decided are decided by the next matching rule, or the built-in rules, from then on.
     *
     * `DELETE /radar/rules/{id}` · action `radar.rules.delete` · scope `radar:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.rules.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Read one Radar rule: its action, the flows it applies to, its position and its conditions.
     *
     * `GET /radar/rules/{id}` · action `radar.rules.get` · scope `radar:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarRule>|PendingApprovalResult<ApiResponse<RadarRule>> : ApiResponse<RadarRule>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.rules.get'), [$id], [], $options, Value::dto(RadarRule::fromArray(...)));
    }

    /**
     * List this environment's own Radar rules in evaluation order (the first whose conditions all hold decides), with their conditions and a readable summary of each.
     *
     * `GET /radar/rules` · action `radar.rules.list` · scope `radar:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<RadarRule>|PendingApprovalResult<Page<RadarRule>> : Page<RadarRule>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('radar.rules.list'), [], $query, $options, Value::dto(RadarRule::fromArray(...)));
    }

    /**
     * Every item of `radar.rules.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, RadarRule, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('radar.rules.list'), [], $query, $options, Value::dto(RadarRule::fromArray(...)));
    }

    /**
     * Set the order Radar rules are evaluated in: `rule_ids` lists every rule of the environment exactly once, first evaluated first.
     *
     * `PUT /radar/rules/order` · action `radar.rules.reorder` · scope `radar:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{rule_ids: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function reorder(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.rules.reorder'), [], $body, $options, Value::none(...));
    }

    /**
     * Change a Radar rule: its name, description, action, flows, enabled state, position, or (replaced whole) its conditions.
     *
     * `PATCH /radar/rules/{id}` · action `radar.rules.update` · scope `radar:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name?: string, description?: string|null, action?: 'allow'|'challenge'|'block', applies_to?: 'all'|'sign_in'|'sign_up', conditions?: list<array{field: 'ip'|'country'|'asn'|'as_organization'|'is_hosting'|'is_vpn'|'is_proxy'|'is_tor'|'email'|'email_domain'|'disposable_email'|'user_agent'|'method'|'new_device'|'impossible_travel'|'travel_kmh'|'risk_score'|'ip_attempts_1m'|'ip_attempts_1h'|'ip_distinct_emails_10m'|'ip_failures_1h'|'email_attempts_1h'|'email_failures_1h'|'device_attempts_1h', operator: 'eq'|'neq'|'in'|'not_in'|'gt'|'gte'|'lt'|'lte'|'contains'|'not_contains'|'starts_with'|'ends_with'|'in_cidr'|'not_in_cidr', value?: string, values?: list<string>}>, enabled?: bool, position?: int}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarRule>|PendingApprovalResult<ApiResponse<RadarRule>> : ApiResponse<RadarRule>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.rules.update'), [$id], $body, $options, Value::dto(RadarRule::fromArray(...)));
    }
}
