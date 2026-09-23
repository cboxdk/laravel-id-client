# Upgrading

## From 0.12 to 0.13

Nothing you call has been removed or renamed. The changes below are the ones an existing
application can notice.

### Exceptions

- Every exception now extends `Cbox\Id\Client\Exceptions\CboxIdException`, which extends
  `RuntimeException` — existing `catch` blocks keep working. Consider catching
  `CboxIdException` instead of `RuntimeException` at your callback.
- A missing required setting throws `NotConfigured` (a subclass of
  `ClientConfigurationException`, so existing catches still match). **The message
  changed** from `Cbox ID client config 'x' is not set.` to one naming the key and its
  environment variable; update any test that matched the old text.
- An **empty issuer** is now `NotConfigured` before any request, rendered as a 503. If
  you wrote a `RequireConfiguredIdentity` middleware for this, you can replace it with
  `cbox-id.configured`.
- `AuthenticationFailed` from a callback carrying `?error=` now includes the
  `error_description` in its message, and sets `$error` / `$errorDescription`.
- `VerifiedToken::organizationOrFail()` throws `OrganizationRequired` (still a
  `RuntimeException`) and renders as a 403 instead of a 500.

### The session now remembers who signed in

`authenticate()` writes `cbox-id-client.identity` to the session: subject, organization,
tier, roles, permissions — never tokens. Laravel's `Login` event binds it to the user you
log in, and `Logout` removes it. If you keep this yourself, set
`CBOX_ID_REMEMBER_IDENTITY=false` (or `session.remember` in a published config).

### Organization switches are checked

If `redirect(organization: …)` / `switchOrganization()` asked for an organization,
`authenticate()` refuses a token bound to any other one. Plain logins are unaffected.

### The manifest version changes once

`cbox-id:publish-manifest` now sends a `version` derived from the canonical checksum
every Cbox ID SDK uses. The first publish after upgrading reports a new version for an
unchanged manifest; Cbox ID decides whether anything changed from its own checksum, which
is unaffected, so nothing is re-synced. A role's `tenant_assignable` must be a boolean —
a string is now refused before publishing.

### Back-channel logout is off until you turn it on

Nothing changes unless you set `CBOX_ID_BACKCHANNEL_LOGOUT=true`. Sessions remembered by
0.12 carry no `sid` or sign-in time: they cannot be ended by `sid`, and a sign-out of the
whole person ends them (unknown age counts as older).

### Published config files

New keys have defaults and need nothing. To read scopes from `CBOX_ID_SCOPES`, replace
the `scopes` array in a published `config/cbox-id-client.php` with the new line from the
package's config (or re-publish it). A plain string there is also accepted now.

### Subclasses of `IdentityClient`

`redirect()` gained trailing optional parameters (`organization`, `organizationHint`)
and `prompt` is now `string|Prompt|null`; the constructor gained an optional third
parameter. A subclass that overrides `redirect()` must widen its signature to match.
`WebhookEvent` gained an optional trailing `sequence`.
