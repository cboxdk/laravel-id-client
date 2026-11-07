# Changelog

All notable changes to `cboxdk/laravel-id-client`. Earlier releases are described on
their GitHub release pages.

## [0.16.0] — prepared candidate, not published

### Upgrade

Workspace team.remove now requires a body with roles_version. Send role arrays and current versions for member writes; refresh on conflict. Environment access writes require environment_access_version. Custom Management implementations must implement invitation and replaceAppRoles. No ownership transfer through ordinary role assignment.

### Added

- Atomically replace one app's manually assigned member roles with an optimistic version check. Other app assignments and organization membership remain unchanged.
- Read an invitation's accepted subject and timestamp from the management API so apps can bind local resource grants to verified acceptance.
- Service connection management and leasing, with typed failures and an offline test fake.
- Generated management resources for staged role definitions, app-role replacement and user property namespaces.

### Changed

- Workspace `team.remove()` now requires a body containing `roles_version` and removes native ID app roles only. Role edits require multiple `roles` plus `roles_version`; environment grants use an independent `environment_access_version`.
- Custom implementations of `Management` must implement the new `invitation()` and `replaceAppRoles()` methods.
- Manifest receipts must match the submitted client, version and full checksum, with valid change flags and non-negative publication counts.
- Manifest publication validates complete permission and role definitions before sending them, and refuses redirects or an insecure production endpoint.
- Permission gates require a current app-scoped decision instead of relying on stored token claims.

## [0.15.4] - 2026-11-05

### Added

- Versioned shared user property reads and patches through `UserProperties`, with typed documents, sanitized errors and a test fake.
- Verified preference and metadata webhooks dispatch `UserPropertiesUpdated` on the webhook worker for app-owned cache sync and private broadcasts. Invalid property payloads are rejected before queuing; existing handlers remain supported.
- Personal app catalog reads and server-held DPoP keys for delegated resource authorization, plus directory revision metadata.
- Integration recipes for private browser sync, duplicate delivery, version reconciliation and reconnect recovery.

## [0.15.3] - 2026-11-04

### Added

- `PermissionDecisions` queries current app-scoped RBAC through Cbox ID's decision endpoint. The client binds responses to the requested subject, organization, configured app and exact permission set, refuses malformed responses and redirects, and makes no local authorization cache.
- `PermissionDecision` exposes explicit per-permission results and current organization membership. `CboxId::fake()->permissionDecisions()` supplies default-denying outcomes and records only token hashes for consumer tests. Authentication and resource ownership remain the consuming application's responsibility.

## [0.15.2] - 2026-11-04

### Added

- `ResourceAuthorizationClient` acquires a dedicated API bearer for an already authenticated person, separately from the existing OIDC login. It uses PKCE S256 and independent ten-minute session transactions, checks the expected subject and organization plus exact resource audience, client, scopes, signature and expiry, and never calls issuer-only UserInfo.
- `ResourceAuthorizationResult` exposes expiry, organization, subject and server-bound context with a private bearer accessor. State is JSON-session-compatible; server-side sessions and blocked authorize/callback routes are required. Browser-visible callbacks never carry API credentials.
- Integration documentation and tests for simultaneous tabs, replay, stale state, malformed URLs, cookie-session refusal, signature failures and cross-identity tokens. Existing `IdentityClient` methods are unchanged.

## [0.15.1] - 2026-10-28

### Added

- `IdentityClient::redirect()` takes a trailing optional `idpHint` (`google`, `github`), sent as `idp_hint`. Cbox ID then skips its sign-in page and sends a signed-out person straight to that provider, for an app that draws its own social buttons. An instance without it, or a provider the page would not offer, shows the sign-in page as before.

## [0.15.0] - 2026-10-10

Needs laravel-id 1.24 / the Cbox ID release with feature flags, fine-grained
authorization, Pipes and SMS as a second factor. Against an older instance the new claim
reads as absent (every feature off) and the new endpoints answer 404.

### Added

- **Feature flags.** `featureFlags()` and `hasFeature($key)` on `CboxUser`,
  `VerifiedToken` and the session `Identity` (new `Contracts\HasFeatureFlags`, with
  `HasFeatureFlags::SCOPE`), read from the `feature_flags` claim — `null` when the scope was
  not requested, `[]` when nothing is on, and never on for a malformed claim. The session
  identity now remembers `feature_flags`.
- `cbox-id.feature:key[,key…]` route middleware (all must be on; a flag that is off answers
  404), with a short `feature:key` alias, and a `@feature('key')` … `@else` … `@endfeature`
  Blade directive. Both short names are configurable under `feature_flags` (or `null` to
  skip — Laravel Pennant also registers `@feature`); an alias the application already has
  is never replaced.
- `CboxId::fake()->actingAs(featureFlags: […])` and `signIn(featureFlags: […])`.
- **Pipes.** `CboxId::leasePipeToken($provider, $purpose, $userId, $accessToken)` leases a
  fresh token for a person's connected account (as the app with a `vault.lease` machine
  token, or with a token you hold) and returns a `PipeToken`. Refusals are typed under
  `PipeLeaseFailed`: `PipeNotConnected` and `PipeReauthorizationRequired` carry
  `connectUrl` (and `connectUrlWith($clientId, $returnTo)`), `PipeTemporarilyUnavailable`
  carries `retryAfter`, `PipeLeaseDenied` is the 403. `CboxId::pipeConnectUrl()` and
  `redirectToPipeConnect()` build the hosted connect page.
- `FrontendClient::config($organization)`: the sign-in document for one organization (by
  id or slug), whose `social` list is exactly what that organization's hosted page offers.
  Each organization is cached on its own. `FrontendConfig::$methods` and `offers()` say
  whether passkeys and magic links are switched on.
- Management clients regenerated from the current specs (Cbox ID wave 10): `featureFlags`
  (including `evaluate(['user_id' => …, 'organization_id' => …])`), `fga` (`check`,
  `checkBatch`, tuples write/delete/list, resources and subjects lists, schema
  get/update/validate, with `consistency_token` on every read), `pipes` (setup, grants,
  connections), `radar`, `signin->sms`, the environment sign-in methods (`signin->social`
  update, enable, disable, inherit and offered; `environment_wide` / `organization_id` on
  social set; the auth policy's method fields), `branding->appearance->set()` taking the
  logo and favicon as base64 data URIs, `users->mfa->sms->remove()`, the HRIS directory
  routes, and on the account plane `mfa->sms->remove()` and `pipes->disconnect()`.
  `Fga\FgaTuple::format()` / `check()` write the tuple notation the batch check takes.

### Changed

- The generator reads an action's own `202 Accepted` body (documented as `oneOf` it and the
  approval body), so `$env->directories->sync()` now returns the `Directory`.

## [0.14.0] - 2026-10-08

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
- **Standard Webhooks verification**: `CboxId::verifyStandardWebhook()` and
  `Webhooks\StandardWebhookSignature` (`verify()`, `sign()`, `secretFor()`), tested against
  the specification's vector. The webhook receiver accepts deliveries signed either way with
  the one configured secret (a hex Cbox secret is converted as Cbox ID converts it).
- `composer generate` (`bin/generate-management`, with `--check` and `--fetch plane=url`)
  and the four specs vendored in `openapi/` from cbox-id `integration/wave-7`. The suite fails
  when the generated code is stale, and snapshots the generated surface.

### Changed

- The existing environment management client's contract test now reads the vendored
  `openapi/environment.yaml` (wave-7) instead of its own older copy.

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
