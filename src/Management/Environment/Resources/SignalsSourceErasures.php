<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `signals.source.erasures.*` on the environment plane. */
class SignalsSourceErasures
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Replay source deletion receipts
     *
     * Requires `signals_source:read` on an environment-owner key. Replay this stream
     * before making a restored or newly backfilled consumer eligible. It retains only
     * opaque subject references and deletion facts beyond the raw-event replay window.
     * Publication order and cursor integrity match the event source. The credential
     * is checked on every page. Ordinary tenant grants cannot access this stream.
     *
     * `GET /signals/source/erasures`
     *
     * @param  array{after?: string, limit?: int}  $query
     * @return Page<array<string, mixed>>
     */
    public function list(array $query = [], ?CallOptions $options = null): Page
    {
        return $this->transport->pageAndWait(Operations::spec('signals.source.erasures.list'), [], $query, $options, Value::object(...));
    }

    /**
     * Every item of `signals.source.erasures.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('signals.source.erasures.list'), [], $query, $options, Value::object(...));
    }
}
