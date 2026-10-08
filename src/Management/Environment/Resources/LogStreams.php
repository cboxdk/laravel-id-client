<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\LogStream;
use Cbox\Id\Client\Management\Environment\Schemas\LogStreamTest;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `log_streams.*` on the environment plane. */
class LogStreams
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Stream the audit trail to a SIEM (Splunk, Elastic, Graylog, CEF, JSON). A generated HMAC key is returned once.
     *
     * `POST /log-streams` · action `log_streams.create` · scope `log_streams:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, destination: 'splunk_hec'|'elastic_ecs'|'graylog_gelf'|'cef_http'|'generic_json', endpoint_url: string, auth?: 'none'|'bearer'|'splunk'|'hmac', secret?: string|null, organization_id?: string|null, environment_wide?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<LogStream>|PendingApprovalResult<ApiResponse<LogStream>> : ApiResponse<LogStream>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('log_streams.create'), [], $body, $options, Value::dto(LogStream::fromArray(...)));
    }

    /**
     * Delete an audit log stream. Nothing more is delivered to its endpoint.
     *
     * `DELETE /log-streams/{id}` · action `log_streams.delete` · scope `log_streams:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('log_streams.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Get one audit log stream: destination, endpoint, auth scheme, owner and health. Never its secret.
     *
     * `GET /log-streams/{id}` · action `log_streams.get` · scope `log_streams:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<LogStream>|PendingApprovalResult<ApiResponse<LogStream>> : ApiResponse<LogStream>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('log_streams.get'), [$id], [], $options, Value::dto(LogStream::fromArray(...)));
    }

    /**
     * List the SIEM destinations this environment's audit trail is streamed to, and whether each is enabled. Never a secret.
     *
     * `GET /log-streams` · action `log_streams.list` · scope `log_streams:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<LogStream>|PendingApprovalResult<Page<LogStream>> : Page<LogStream>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('log_streams.list'), [], $query, $options, Value::dto(LogStream::fromArray(...)));
    }

    /**
     * Every item of `log_streams.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, LogStream, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('log_streams.list'), [], $query, $options, Value::dto(LogStream::fromArray(...)));
    }

    /**
     * Send one test entry to a log stream now and report whether the SIEM accepted it.
     *
     * `POST /log-streams/{id}/test` · action `log_streams.test` · scope `log_streams:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<LogStreamTest>|PendingApprovalResult<ApiResponse<LogStreamTest>> : ApiResponse<LogStreamTest>)
     */
    public function test(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('log_streams.test'), [$id], [], $options, Value::dto(LogStreamTest::fromArray(...)));
    }

    /**
     * Disable (enabled: false) or resume (enabled: true) an audit log stream. Disabled, entries are kept and delivered on resume.
     *
     * `PATCH /log-streams/{id}` · action `log_streams.update` · scope `log_streams:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{enabled: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<LogStream>|PendingApprovalResult<ApiResponse<LogStream>> : ApiResponse<LogStream>)
     */
    public function update(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('log_streams.update'), [$id], $body, $options, Value::dto(LogStream::fromArray(...)));
    }
}
