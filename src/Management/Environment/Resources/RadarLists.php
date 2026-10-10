<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\RadarListEntry;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `radar.lists.*` on the environment plane. */
class RadarLists
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Add an entry to this environment's Radar allow or deny list: an IP or CIDR range, an email address, a mail domain (and its subdomains), or a device id from the decisions explorer. Deny blocks before every rule; allow skips every rule.
     *
     * `POST /radar/lists` · action `radar.lists.add` · scope `radar:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{list: 'allow'|'deny', kind: 'ip'|'email'|'email_domain'|'device', value: string, note?: string|null, expires_at?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarListEntry>|PendingApprovalResult<ApiResponse<RadarListEntry>> : ApiResponse<RadarListEntry>)
     */
    public function add(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.lists.add'), [], $body, $options, Value::dto(RadarListEntry::fromArray(...)));
    }

    /**
     * List this environment's Radar allow and deny entries — IPs and CIDR ranges, addresses, mail domains, devices — optionally one list or one kind.
     *
     * `GET /radar/lists` · action `radar.lists.list` · scope `radar:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{list?: 'allow'|'deny', kind?: 'ip'|'email'|'email_domain'|'device', limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<RadarListEntry>|PendingApprovalResult<Page<RadarListEntry>> : Page<RadarListEntry>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('radar.lists.list'), [], $query, $options, Value::dto(RadarListEntry::fromArray(...)));
    }

    /**
     * Every item of `radar.lists.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{list?: 'allow'|'deny', kind?: 'ip'|'email'|'email_domain'|'device', limit?: int}  $query
     * @return Generator<int, RadarListEntry, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('radar.lists.list'), [], $query, $options, Value::dto(RadarListEntry::fromArray(...)));
    }

    /**
     * Remove an entry from this environment's Radar allow or deny list.
     *
     * `DELETE /radar/lists/{id}` · action `radar.lists.remove` · scope `radar:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.lists.remove'), [$id], [], $options, Value::none(...));
    }
}
