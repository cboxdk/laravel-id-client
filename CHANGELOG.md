# Changelog

All notable changes to `cboxdk/laravel-id-client`. Earlier releases are described on
their GitHub release pages.

## [Unreleased]

### Added

- **Typed management clients, generated from the server's OpenAPI documents** —
  `Management\EnvironmentClient`, `WorkspaceClient`, `PlatformClient` and `AccountClient`,
  the PHP twin of id-js's management client. Methods are the server's action names
  (`$env->apps->secrets->rotate($id, [...])`), path parameters first, then a body or query
  array with a PHPStan-checked shape, then `CallOptions`. Results are readonly schema
  objects in an `ApiResponse`; paged lists return a `Page` and have a lazy `…All()`
  generator. Each plane's `Operations` table lists every action's method, path, scope and
  danger.
- The runtime under them (`Management\Transport`): management-key (plane-checked) or
  access-token / token-provider auth; `Cbox-Environment` for a root-host token; an
  `Idempotency-Key` on every write, reused on safe retries (network, 5xx, 429,
  `409 idempotency_in_progress`; `Retry-After` honoured) and a `replayed` flag; the approval
  flow (`202 approval_required` → `onApprovalRequired` → poll on the same origin only →
  repeat with `Cbox-Approval`), with `ApprovalDenied` / `ApprovalExpired` and
  `CallOptions::returnPendingApproval()`.
- `Exceptions\CboxIdApiException` (status, error, message, errors, requestId — the body's
  `request_id`, else `X-Request-Id` — and retryAfter), `ManagementNetworkException`,
  `UnexpectedResponse`.
- **Audit Logs helpers**: a buffered `AuditLogger` (batches of up to 100, one idempotency key
  per batch, flushed when the app terminates), `AuditLogs::export()` that waits for a CSV
  export, and `AuditChain::verify()` — byte-compatible with the server's canonical JSON,
  proven against the shared cross-SDK fixture.
- **Laravel**: the `CboxIdApi` facade (`environment()`, `workspace()`, `environmentAs()`,
  `workspaceAs()`, `platform()`, `account()`, `auditLogs()`, `auditLogger()`),
  `EnvironmentClient` / `WorkspaceClient` / `AuditLogger` container bindings, the
  `ManagementApprovalRequired` event, and `management.workspace_key`, `root_url`, `retries`,
  `timeout`, `approval_poll_interval` config (`CBOX_ID_WORKSPACE_KEY`, `CBOX_ID_ROOT_URL`, …).
- `composer generate` (`bin/generate-management`, with `--check` and `--fetch plane=url`)
  and the four specs vendored in `openapi/` from cbox-id `integration/wave-6`. The suite fails
  when the generated code is stale, and snapshots the generated surface.

### Changed

- The existing environment management client's contract test now reads the vendored
  `openapi/environment.yaml` (wave-6) instead of its own older copy.

## [0.13.0] - 2026-09-24

The tenancy and authorization layer: what every consuming app was writing by hand.

### Added

- **Tenancy on every principal.** `CboxUser`, `VerifiedToken`, the new `VerifiedApiKey`
  and the session `Identity` implement `Contracts\Principal`: `organization()` (id,
  name and the `org_role` tier as `Enums\OrganizationRole`), `roles()`, `permissions()`,
  `hasPermission()`, `hasRole()`, `actor()` / `isSupportSession()` (RFC 8693 `act`), and
  `organizations()` from the `organizations` scope.
- **Session identity.** `authenticate()` remembers an allow-listed claim subset (never
  tokens) in the session, bound to the local user your callback logs in and forgotten on
  logout or a different login. `CboxId::principal()`, `CboxId::currentOrganization()`,
  `rememberIdentity()`, `forgetIdentity()`.
- **Middleware**: `cbox-id.org[:tier]`, `cbox-id.permission:a,b`, `cbox-id.api-key[:perm]`,
  `cbox-id.configured[:keys]`.
- **Permission gate** (opt-in, `CBOX_ID_GATE=true`): `feature:action` abilities answered
  from Cbox ID permissions for `@can`, `can()` and `authorize()`. Only ever grants.
- **Organization selection**: `redirect(organization:, organizationHint:)`, the
  `select_organization` / `create_organization` prompts (`Enums\Prompt`),
  `switchOrganization()`, `selectOrganization()`, `createOrganization()`. A switch that
  comes back bound to another organization is refused.
- **`refresh()`** — the refresh-token grant, with the JavaScript SDK's behaviour.
- **Environment management client** (`Contracts\Management`, `CboxIdManagement`):
  organizations, members, ownership transfer, invitations with roles and `return_to`,
  role assignments (by id, or manifest key + client id), environment (staff) roles, apps
  and blueprints, APIs, API keys and support sessions — typed request and response
  objects, typed errors (`ManagementApiException`, `ResourceNotFound`,
  `ValidationFailed`), and a contract test against cbox-id's OpenAPI document.
- **Customer API keys**: `CboxId::verifyApiKey()` against `POST /oauth/api-keys/verify`,
  briefly cached (`CBOX_ID_API_KEY_CACHE_TTL`), and the `cbox-id.api-key` middleware.
- **`CboxId::fake()`**: `actingAs()`, `signIn()`, real signed `token()`s, an in-memory
  management API with assertions, and in-memory API keys.
- **Webhooks**: `WebhookEvent::$sequence`, and `Webhooks\EventType` with the full event
  catalogue; `on()` accepts it.
- **Config**: `CBOX_ID_SCOPES`, `session.remember`, `authorization.gate`,
  `organizations.picker`, `management.key` / `management.url`, `api_keys.cache_ttl`.
- `Exceptions\CboxIdException`, the base of everything the SDK throws.
- `CboxId::apiKeysUrl()` / `redirectToApiKeys()` — the hosted page where a person makes
  API keys for your app (`/account/api-keys`, this app preselected, a link back).
- **Back-channel logout** (OIDC Back-Channel Logout 1.0, opt-in with
  `CBOX_ID_BACKCHANNEL_LOGOUT=true`): a receiver that validates logout tokens strictly
  (§2.6) and ends the matching local sessions — destroyed at once on database/redis/
  cache-backed drivers, signed out on the next request everywhere else — plus the
  `cbox-id.session` middleware and the `BackchannelLogoutReceived` event.
- **Staff roles** in the manifest: `'tenant_assignable' => false` on a role.
- `ManifestPublisher::checksum()` — the canonical checksum Cbox ID computes, asserted
  against the shared cross-SDK fixture.

### Fixed

- `account_path` defaulted to `/settings`, the organization's settings page on Cbox ID;
  it is now `/account`, the person's own area (`CBOX_ID_ACCOUNT_PATH`).

- An empty issuer surfaced as Guzzle's "URI must include a scheme" 500. It is now
  `NotConfigured` — naming the key and its environment variable — rendered as a 503.
- The callback dropped `error_description`; `AuthenticationFailed` now carries it.
- The manifest `version` was a hash of the config as written, so it changed when the
  config was reordered and matched no other SDK. It is now the canonical checksum's first
  16 characters, the same as every other SDK.
- `VerifiedToken::organizationOrFail()` threw a bare `RuntimeException` (a 500); it now
  throws `OrganizationRequired`, a 403 with a reason.

See [UPGRADING.md](UPGRADING.md).
