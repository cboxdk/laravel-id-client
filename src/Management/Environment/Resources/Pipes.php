<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Pipe;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `pipes.*` on the environment plane. */
class Pipes
{
    public readonly PipesConnections $connections;

    public readonly PipesGrants $grants;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->connections = new PipesConnections($transport);
        $this->grants = new PipesGrants($transport);
    }

    /**
     * Configure a pipe: the environment's OAuth app at a third-party provider (GitHub, Google, Microsoft 365, Slack, Salesforce, HubSpot, Linear, Notion), so people can connect their accounts. The client secret is sealed and never returned.
     *
     * `POST /pipes` · action `pipes.create` · scope `pipes:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{provider: 'github'|'google'|'microsoft'|'slack'|'salesforce'|'hubspot'|'linear'|'notion', client_id: string, client_secret: string, scopes?: list<string>, parameters?: array{tenant?: string, domain?: string}}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Pipe>|PendingApprovalResult<ApiResponse<Pipe>> : ApiResponse<Pipe>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('pipes.create'), [], $body, $options, Value::dto(Pipe::fromArray(...)));
    }

    /**
     * Remove a pipe and every connection through it. Their tokens are revoked here, not at the provider — disconnect people first if that matters.
     *
     * `DELETE /pipes/{id}` · action `pipes.delete` · scope `pipes:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('pipes.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Show one pipe: its provider, OAuth client id, scopes, the redirect URI to register at the provider, and the apps granted its tokens.
     *
     * `GET /pipes/{id}` · action `pipes.get` · scope `pipes:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Pipe>|PendingApprovalResult<ApiResponse<Pipe>> : ApiResponse<Pipe>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('pipes.get'), [$id], [], $options, Value::dto(Pipe::fromArray(...)));
    }

    /**
     * List the pipes — the third-party providers people can connect their accounts to — with their scopes and the apps granted their tokens. Never a client secret.
     *
     * `GET /pipes` · action `pipes.list` · scope `pipes:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<Pipe>|PendingApprovalResult<Page<Pipe>> : Page<Pipe>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('pipes.list'), [], $query, $options, Value::dto(Pipe::fromArray(...)));
    }

    /**
     * Every item of `pipes.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, Pipe, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('pipes.list'), [], $query, $options, Value::dto(Pipe::fromArray(...)));
    }

    /**
     * Change a pipe — client id, client secret (sealed), scopes, parameters, or whether it is enabled. Only what is sent changes.
     *
     * `PATCH /pipes/{id}` · action `pipes.update` · scope `pipes:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string, client_secret?: string, scopes?: list<string>, parameters?: array{tenant?: string, domain?: string}, enabled?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Pipe>|PendingApprovalResult<ApiResponse<Pipe>> : ApiResponse<Pipe>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('pipes.update'), [$id], $body, $options, Value::dto(Pipe::fromArray(...)));
    }
}
