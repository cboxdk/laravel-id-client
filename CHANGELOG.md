# Changelog

All notable changes to `cboxdk/laravel-id-client`. Earlier releases are described on
their GitHub release pages.

## [Unreleased]

## [0.19.2] - 2026-11-10

### Changed

- The app catalog reads the person's catalog with the app's own machine token by default (`app_catalog.mode`, `CBOX_APP_CATALOG_MODE=machine`): `GET {issuer}/api/v1/app-catalog/for-user?user_id=…&organization_id=…` with the cached `client_credentials` token for `app_catalog:resolve`, through `CBOX_INTERNAL_ROUTES` when set, with a 1 s connect and 2 s total timeout. The switcher no longer needs a sign-in hop or a reconnect: nothing per person is kept in the session. The 0.19.0 grant flow failed in production when a login callback was aborted, and for sessions from before 0.19 that had no token to renew with.
- Answers are cached per issuer, client, person and organization in the catalog's cache store, fresh for `refresh_after` (at least 60 s). The last good answer is kept for 24 h and served (`200`, `refresh_after` 30) when Cbox ID errors, rate-limits or times out. With no earlier answer the route answers `503 {error: "catalog_unavailable"}`. A `404` from Cbox ID (the person is no longer an active member) answers `401 {signin_url}`. A refused machine token is dropped and asked for again once.
- In machine mode, logins no longer ask for `account:apps:read`, and `authenticate()` keeps no login or refresh token for the catalog. `rememberAppCatalogSignIn()` returns `false`.
- While Cbox ID has not given the client `app_catalog:resolve` (the token request is refused with `invalid_scope` or `unauthorized_client`, or the endpoint answers `403` `insufficient_scope`/`invalid_scope`), a read falls back to the grant flow. The warning is logged at most once an hour, and the machine read is retried after five minutes. While the fallback has been used within the hour, sign-ins prepare a grant again, so the fallback can actually get one.
- `CBOX_APP_CATALOG_MODE=grant` keeps the 0.19.0 behaviour.

### Fixed

- The app catalog's sign-in loop guard was set when the catalog answered `401`, not when a sign-in happened, so a poll the page did not follow left the person on `503` for five minutes. It is now set when `/apps/signin` is opened, or when a grant-mode sign-in completes, which covers an app's own launch route. Inside five minutes of that the route answers `503`; after that it answers `401` again.
- `CboxId::appCatalogRoutes()` no longer builds the identity client while routes load, so a test that sets the Cbox ID config after the routes are loaded gets that config.

## [0.19.1] - 2026-11-10

### Added

- CIBA ping mode: `BackchannelAuthentication::start()` takes an optional `clientNotificationToken`, sent as `client_notification_token` (at most 1024 characters, refused locally beyond that). Cbox ID requires it from a client an operator registered for ping (laravel-id 2.3), and sends it as `Authorization: Bearer …` with `{"auth_req_id": "…"}` to the client's notification endpoint when the person answers. Poll-mode callers are unchanged. `FakeBackchannelAuthentication` records it.

## [0.19.0] - 2026-11-10

### Added

- App catalog module (`Cbox\Id\Client\AppCatalog`), replacing the copy every Cbox app kept in `app/Chrome`. `CboxId::appCatalogRoutes()` registers `GET /apps/catalog` (JSON `{apps, revision, refresh_after}` in Cbox ID's order), `/apps/authorize`, `/apps/callback` and `/apps/signin`.
- With `app_catalog.enabled` (`CBOX_APP_CATALOG_ENABLED`), logins also ask for `account:apps:read`, and `authenticate()` seals the login access token (until it expires) and the refresh token (when there is one) into the session. No extra call to Cbox ID: the callback stays an SSO hop. `CboxId::rememberAppCatalogSignIn($user)` does the same for a `CboxUser` built another way.
- The catalog route gets a grant on the server when it is missing or within five minutes of expiry: exchange the login token while it is valid (RFC 8693, DPoP-bound) and drop it; otherwise refresh the person's tokens, store the rotated refresh token, and exchange the new access token. It runs under a cache lock per session, and reads the newest rotated token from the cache, so a spent refresh token is never presented. When only a sign-in can help it answers `401 {signin_url}` (your app's sign-in with `return_to` and `organization`), at most once per five minutes; when Cbox ID is down, `503 {error: catalog_unavailable}`.
- `Contracts\ResolvesAppCatalogContext` decides whose catalog a request reads. The default is the session's signed-in identity; bind your own when the URL names the organization.
- Grants are kept only when signed for exactly this person, organization and client, with no `act`, exactly `account:apps:read`, audience `{issuer}/mcp` and our DPoP key. The cookie session driver is refused. `Logout` forgets the grant and the refresh token.
- `offline_access` is not added for you. Without it Cbox ID issues no refresh token: the first grant still comes from the login token, but an expired grant means one trip through sign-in instead of a server-side renewal.

## [0.18.2] - 2026-11-10

### Added

- `PersonalAppCatalog` passes each app's `icon_dark_url` through next to `icon_url`, with the same check that it is an HTTPS URL on the issuer. `null` when the app has no dark icon. Cbox ID's catalog is the one source of every app's light and dark icon; apps render those instead of keeping their own copy.

## [0.18.1] - 2026-11-10

### Added

- `internal_routes` (`CBOX_INTERNAL_ROUTES`): `public-host=internal-base-url` pairs. An HTTPS request on port 443 to a listed host goes to its in-cluster address instead, with the same path and query, the public `Host`, and `X-Forwarded-Proto: https`, `X-Forwarded-Host` and `X-Forwarded-Port: 443`. It is a global request middleware on Laravel's HTTP client, so it covers this package's calls and the application's own `Http::` calls. The issuer, discovery and token `iss`/`aud` checks keep using the public URL. Internal hosts must end in `.svc` or `.svc.cluster.local`; other entries are ignored with a logged warning. Empty (the default) changes nothing.

## [0.18.0] - 2026-11-09

### Added

- `Contracts\KeptPermissionDecisions` (bound to `Decisions\KeptDecisions`): Cbox ID's permission decisions, kept in the cache so pages render from local data and keep rendering while Cbox ID is down. A page view (GET, HEAD) gets the kept answer at once, even a stale one, and a queued `Decisions\RefreshKeptDecision` asks again. A change (POST, PUT, PATCH, DELETE), a call with `acting: true`, and a call outside an HTTP request ask Cbox ID now when the answer is stale. Yes and no are both kept, as plain arrays checked on read. It fetches its own `decisions:read` machine token and asks once more with a new one after a 401. `refresh()` and `forget()` for asking now and dropping an answer.
- `decisions.fresh_for` (30 s), `decisions.max_age` (one day), `decisions.store`, `decisions.queue_connection` and `decisions.queue` config.
- `http_connect_timeout` (`CBOX_ID_HTTP_CONNECT_TIMEOUT`, default 3 s): every HTTP call the package makes now has a connect timeout as well as its overall timeout. The management clients take an optional `connectTimeout` constructor argument.

### Changed

- A machine token that lives under two minutes is kept for half its life instead of not at all.
- A 401 from a pipe lease or a service-connection call drops the kept machine token, so the next call asks for a new one.
- `PermissionDecisions::check()` is unchanged: it still asks Cbox ID on every call.

## [0.17.1] - 2026-11-09

### Fixed

- `machineToken()` no longer asks Cbox ID for a new token on every call. It keeps each token in the app's cache, encrypted with the app key, until 60 seconds before it expires (from the token's `exp`, else `expires_in`, else 240 seconds; at most an hour), and fetches under a cache lock so concurrent workers ask once. Cbox ID throttles token requests per source address, so per-request tokens from one app caused 429s for every app behind the same address. Entries that cannot be decrypted are dropped and fetched again.

### Added

- `forgetMachineToken($scopes, $resource)` drops a cached token, for when an API refuses it.
- `token_cache.enabled` (`CBOX_ID_TOKEN_CACHE`, default on) and `token_cache.store` (`CBOX_ID_TOKEN_CACHE_STORE`, default store) config.
- `IdentityClient` takes an optional fourth constructor argument, a `Support\MachineTokenCache`. A client built by hand without one caches nothing, as before.

## [0.17.0] - 2026-11-08

### Added

- `BackchannelAuthentication` (OpenID CIBA, poll mode) and `DeviceAuthorization` (RFC 8628) start a request, poll the token endpoint and wait for tokens. They discover endpoints from the RFC 8414 metadata, honour `interval` and `slow_down`, carry `resource` (RFC 8707) through to the token request, and return a typed `TokenResult`.
- Typed exceptions for each refusal: `AuthorizationDenied`, `AuthorizationExpired`, `InvalidGrant`, `InvalidTarget`, `InvalidClient`, `UnknownUser`, `InvalidBindingMessage`, `GrantRateLimited` (429 with `retryAfter`) and `GrantTimedOut`, all under `GrantFailed`.
- `ClientAuthentication` for `client_secret_basic`, `client_secret_post`, `private_key_jwt` (RS256 or ES256, a fresh assertion per request) and public clients, configured under `client_authentication`.
- `Testing\FakeTimer`, so tests of the blocking wait finish at once.
- `CboxId::fake()->backchannelAuthentication()` and `->deviceAuthorization()` return scripted fakes (`pending()`, `approve()`, `deny()`, `expire()`, `throttle()`) with `assertStarted()` and `assertPolled()`. Unscripted, they never grant.

## [0.16.1] - 2026-11-07

### Fixed

- Regenerate the production SBOM from the canonical PHP 8.4 / Testbench 11 dependency resolution, including `symfony/polyfill-time`. Runtime APIs and authorization behavior are unchanged. The published 0.16.0 tag remains immutable.

## [0.16.0] - 2026-11-07

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
