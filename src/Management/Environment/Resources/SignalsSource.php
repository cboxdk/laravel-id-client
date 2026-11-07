<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SignalsSourceListItem;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `signals.source.*` on the environment plane. */
class SignalsSource
{
    public readonly SignalsSourceErasures $erasures;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->erasures = new SignalsSourceErasures($transport);
    }

    /**
     * Read the environment-owner identity source
     *
     * Requires an environment key with `signals_source:read` and explicit operator
     * opt-in for this environment. Organization grants and delegated user tokens
     * cannot read this source. Records include pre-organization authentication.
     * The cursor follows committed sequence order and is bound to the source generation.
     * Follow `next_cursor`, including on empty pages; a stale generation or purged
     * cursor requires an explicit restart. Pages are at most 1000 records / 1 MiB.
     * Data follows the Signals 2.0.0 identity event contracts. Scope is attached by
     * the consumer's explicit grant; producer_environment is always host-bound.
     * Replay is limited to seven days, including raw-IP retention. A heartbeat
     * proves exporter maintenance, not complete coverage of every identity flow.
     *
     * `GET /signals/source`
     *
     * @param  array{after?: string, limit?: int}  $query
     * @return Page<SignalsSourceListItem>
     */
    public function list(array $query = [], ?CallOptions $options = null): Page
    {
        return $this->transport->pageAndWait(Operations::spec('signals.source.list'), [], $query, $options, Value::dto(SignalsSourceListItem::fromArray(...)));
    }

    /**
     * Every item of `signals.source.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, SignalsSourceListItem, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('signals.source.list'), [], $query, $options, Value::dto(SignalsSourceListItem::fromArray(...)));
    }
}
