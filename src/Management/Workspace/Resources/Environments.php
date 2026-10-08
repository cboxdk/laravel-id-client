<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\Value;
use Cbox\Id\Client\Management\Workspace\Operations;
use Cbox\Id\Client\Management\Workspace\Schemas\CreatedEnvironment;
use Cbox\Id\Client\Management\Workspace\Schemas\Environment;
use Generator;

/** `environments.*` on the workspace plane. */
class Environments
{
    public readonly EnvironmentsDomain $domain;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->domain = new EnvironmentsDomain($transport);
    }

    /**
     * Create an environment
     *
     * Requires scope `environments:write` and the `manage-environments` capability
     * (owner/admin/developer). Danger: critical — with `initial_key` it mints a credential.
     *
     * With `initial_key`, the environment's first management key is minted in the same
     * call and returned once as `initial_key.token` — enough for an agent holding one
     * workspace key to stand an environment up and start configuring it on the
     * environment's own host. An idempotent replay returns `initial_key.token: null`.
     *
     * `POST /workspace/environments` · action `environments.create` · scope `environments:write` · danger: critical
     *
     * @param  array{name: string, type?: 'production'|'sandbox', project_id?: string, initial_key?: array{name: string, scopes: list<string>, expires_at?: string|null}|null}  $body
     * @return ApiResponse<CreatedEnvironment>
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('environments.create'), [], $body, $options, Value::dto(CreatedEnvironment::fromArray(...)));
    }

    /**
     * List environments
     *
     * Requires scope `workspace:read` (any role).
     *
     * `GET /workspace/environments` · action `environments.list` · scope `workspace:read`
     *
     * @param  array{limit?: int, page?: int}  $query
     * @return Page<Environment>
     */
    public function list(array $query = [], ?CallOptions $options = null): Page
    {
        return $this->transport->pageAndWait(Operations::spec('environments.list'), [], $query, $options, Value::dto(Environment::fromArray(...)));
    }

    /**
     * Every item of `environments.list`, fetching pages lazily as the iteration reaches them — `page` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, Environment, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('environments.list'), [], $query, $options, Value::dto(Environment::fromArray(...)));
    }
}
