<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\App;
use Cbox\Id\Client\Management\Environment\Schemas\AppBlueprint;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `apps.*` on the environment plane. */
class Apps
{
    public readonly AppsManifest $manifest;

    public readonly AppsScopes $scopes;

    public readonly AppsSecrets $secrets;

    public readonly AppsSettings $settings;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->manifest = new AppsManifest($transport);
        $this->scopes = new AppsScopes($transport);
        $this->secrets = new AppsSecrets($transport);
        $this->settings = new AppsSettings($transport);
    }

    /**
     * Export an app's configuration as a blueprint
     *
     * `{id}` is the app's id or its `client_id`. The document
     * carries the app's settings and none of its identity or credentials: no client id,
     * no secret, no key set, no owning organization. `POST /apps` with it as `blueprint`
     * creates the same app in another environment.
     *
     * `GET /apps/{id}/blueprint` · action `apps.blueprint` · scope `apps:read`
     *
     * @return ApiResponse<AppBlueprint>
     */
    public function blueprint(string $id, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('apps.blueprint'), [$id], [], $options, Value::dto(AppBlueprint::fromArray(...)));
    }

    /**
     * Copy an app into another environment of this project, with a new client id and secret there. A person on the environment console only; a key exports the blueprint instead.
     *
     * `POST /apps/{id}/copy` · action `apps.copy` · scope `apps:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{environment_id: string, name: string, redirect_uris?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function copy(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.copy'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Register an app
     *
     * Two ways to describe it:
     *
     * - **From a blueprint** — send `GET /apps/{id}/blueprint`'s document from another
     *   environment as `blueprint` (promote staging to production). `name`,
     *   `redirect_uris` and `post_logout_redirect_uris` beside it replace the
     *   blueprint's own, since those usually name the environment it came from. A
     *   `private_key_jwt` blueprint needs `jwks`.
     * - **Short form** — `name` and `type` (`web`, `spa`, `cli`, `service`, `agent`; default
     *   `web`), which decide the client type, grants and default scopes as the console's
     *   "Create app" does. `advanced` takes `client_type` and `grant_types`.
     *
     * The response carries `client_secret` for an app that authenticates with one —
     * **once**. Only its hash is kept, and an idempotent replay returns `client_secret: null`.
     *
     * A platform scope reserved for the console (`vault.manage`, `decisions:read`) is
     * refused (`scope_not_grantable`), as is a registered API's scope this app's owner may
     * not hold. Danger: critical — it mints a credential.
     *
     * `POST /apps` · action `apps.create` · scope `apps:write` · danger: critical
     *
     * @param  array{blueprint?: array{kind: 'cbox-id.client-blueprint', version: 1, name: string, client_type: 'confidential'|'public', token_endpoint_auth_method?: string|null, grant_types?: list<string>, redirect_uris?: list<string>, post_logout_redirect_uris?: list<string>, scopes?: list<string>, first_party?: bool, manifest_url?: string|null, access_token_ttl?: int|null, backchannel_logout_uri?: string|null, backchannel_logout_session_required?: bool, api_key_prefix?: string|null}, name?: string, type?: 'web'|'spa'|'cli'|'service'|'agent'|'advanced', client_type?: 'confidential'|'public', grant_types?: list<string>, redirect_uris?: list<string>, post_logout_redirect_uris?: list<string>, scopes?: list<string>, first_party?: bool, manifest_url?: string|null, organization_id?: string|null, jwks?: array<string, mixed>|null}  $body
     * @return ApiResponse<App>
     */
    public function create(array $body = [], ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('apps.create'), [], $body, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Delete an app and every secret it holds. Anything signing in as it stops working immediately.
     *
     * `DELETE /apps/{id}` · action `apps.delete` · scope `apps:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Get one app (OAuth client) by its id or its client_id: its kind, grants, redirect URIs, scopes and settings. Never a secret.
     *
     * `GET /apps/{id}` · action `apps.get` · scope `apps:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.get'), [$id], [], $options, Value::dto(App::fromArray(...)));
    }

    /**
     * List apps
     *
     * Never a secret.
     *
     * `GET /apps` · action `apps.list` · scope `apps:read`
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return Page<App>
     */
    public function list(array $query = [], ?CallOptions $options = null): Page
    {
        return $this->transport->pageAndWait(Operations::spec('apps.list'), [], $query, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Every item of `apps.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, App, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('apps.list'), [], $query, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Rename an app and replace its redirect URIs or sign-out redirect URIs. Fields left out are unchanged.
     *
     * `PATCH /apps/{id}` · action `apps.update` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name?: string, redirect_uris?: list<string>, post_logout_redirect_uris?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.update'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }
}
