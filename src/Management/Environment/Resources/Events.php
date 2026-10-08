<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\DomainEvent;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `events.*` on the environment plane. */
class Events
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read this environment's domain events (the facts webhooks deliver) oldest first; pass the last id as `after` to poll for new ones.
     *
     * `GET /events` · action `events.list` · scope `events:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string, types?: list<string>, organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<DomainEvent>|PendingApprovalResult<Page<DomainEvent>> : Page<DomainEvent>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('events.list'), [], $query, $options, Value::dto(DomainEvent::fromArray(...)));
    }

    /**
     * Every item of `events.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int, types?: list<string>, organization_id?: string}  $query
     * @return Generator<int, DomainEvent, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('events.list'), [], $query, $options, Value::dto(DomainEvent::fromArray(...)));
    }
}
