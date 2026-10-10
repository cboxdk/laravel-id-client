<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\RadarDecision;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `radar.decisions.*` on the environment plane. */
class RadarDecisions
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read one Radar decision: the verdict, whether it was enforced, the deciding rule, every rule that fired, the reasons, the risk score and the facts it was decided on.
     *
     * `GET /radar/decisions/{id}` · action `radar.decisions.get` · scope `radar:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarDecision>|PendingApprovalResult<ApiResponse<RadarDecision>> : ApiResponse<RadarDecision>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.decisions.get'), [$id], [], $options, Value::dto(RadarDecision::fromArray(...)));
    }

    /**
     * List this environment's Radar decisions newest first — verdict, deciding rule, every rule that fired, reasons and facts — filtered by verdict, flow, rule, country, email, IP, device or time; pass `next_cursor` as `after` to page. Email and IP are matched by keyed pseudonym and never returned.
     *
     * `GET /radar/decisions` · action `radar.decisions.list` · scope `radar:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{verdict?: 'allow'|'challenge'|'block', flow?: 'sign_in'|'sign_up', rule?: string, country?: string, email?: string, ip?: string, device?: string, enforced?: bool, from?: string, to?: string, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<RadarDecision>|PendingApprovalResult<Page<RadarDecision>> : Page<RadarDecision>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('radar.decisions.list'), [], $query, $options, Value::dto(RadarDecision::fromArray(...)));
    }

    /**
     * Every item of `radar.decisions.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{verdict?: 'allow'|'challenge'|'block', flow?: 'sign_in'|'sign_up', rule?: string, country?: string, email?: string, ip?: string, device?: string, enforced?: bool, from?: string, to?: string, limit?: int}  $query
     * @return Generator<int, RadarDecision, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('radar.decisions.list'), [], $query, $options, Value::dto(RadarDecision::fromArray(...)));
    }
}
