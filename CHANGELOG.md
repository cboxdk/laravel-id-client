# Changelog

All notable changes to `cboxdk/laravel-id-client`. Earlier releases are described on
their GitHub release pages.

## Unreleased (0.13.0)

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
  role assignments, environment (staff) roles, apps and blueprints, APIs, API keys and
  support sessions — typed request and response objects, typed errors
  (`ManagementApiException`, `ResourceNotFound`, `ValidationFailed`).
- **Customer API keys**: `CboxId::verifyApiKey()` against `POST /oauth/api-keys/verify`,
  briefly cached (`CBOX_ID_API_KEY_CACHE_TTL`), and the `cbox-id.api-key` middleware.
- **`CboxId::fake()`**: `actingAs()`, `signIn()`, real signed `token()`s, an in-memory
  management API with assertions, and in-memory API keys.
- **Webhooks**: `WebhookEvent::$sequence`, and `Webhooks\EventType` with the full event
  catalogue; `on()` accepts it.
- **Config**: `CBOX_ID_SCOPES`, `session.remember`, `authorization.gate`,
  `organizations.picker`, `management.key` / `management.url`, `api_keys.cache_ttl`.
- `Exceptions\CboxIdException`, the base of everything the SDK throws.

### Fixed

- An empty issuer surfaced as Guzzle's "URI must include a scheme" 500. It is now
  `NotConfigured` — naming the key and its environment variable — rendered as a 503.
- The callback dropped `error_description`; `AuthenticationFailed` now carries it.
- `VerifiedToken::organizationOrFail()` threw a bare `RuntimeException` (a 500); it now
  throws `OrganizationRequired`, a 403 with a reason.

See [UPGRADING.md](UPGRADING.md).
