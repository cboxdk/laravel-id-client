<?php

declare(strict_types=1);

return [

    /*
     * The base URL (issuer) of the Cbox ID instance you authenticate against, e.g.
     * https://id.acme.com. The SDK discovers every endpoint (authorize, token,
     * userinfo, jwks, end-session) from `{issuer}/.well-known/openid-configuration`,
     * so this is usually the only endpoint you configure.
     */
    'issuer' => env('CBOX_ID_ISSUER'),

    /*
    |---------------------------------------------------------------------------
    | Audience — this API's own resource identifier
    |---------------------------------------------------------------------------
    |
    | What an access token must be addressed to before this application will
    | accept it. Cbox ID binds every access token to a resource (RFC 8707/9068),
    | so a token minted to be spent elsewhere is refused here rather than honoured
    | because both services trust the same issuer.
    |
    | Give production and sandbox DIFFERENT values and the separation is enforced
    | by the signature instead of by a convention beside it: a sandbox token
    | simply cannot be spent against production.
    |
    | Left empty it falls back to the issuer, which is what Cbox ID stamps when no
    | resource was requested — so an application that has not thought about this
    | still verifies rather than rejecting everything.
    |
    */

    'audience' => env('CBOX_ID_AUDIENCE'),

    /*
     * Your OAuth client credentials, registered on the Cbox ID instance. The secret
     * is required for confidential clients (server-side apps) and for machine tokens,
     * introspection and revocation.
     */
    'client_id' => env('CBOX_ID_CLIENT_ID'),
    'client_secret' => env('CBOX_ID_CLIENT_SECRET'),

    /*
     * Your app's callback URL — must exactly match one registered on the client.
     */
    'redirect' => env('CBOX_ID_REDIRECT'),

    /*
     * A PUBLISHABLE key, for reading the browser-facing Frontend API — the environment's
     * public sign-in configuration and the current session. It is the opposite of the
     * secret above: public on purpose, safe in a page, and useful only from the origins
     * its owner listed against it in the console (Developers → Frontend keys).
     *
     * Optional. Set it when you render your own sign-in box and want it to carry the
     * environment's own branding and social buttons rather than hard-coding them.
     */
    'publishable_key' => env('CBOX_ID_PUBLISHABLE_KEY'),

    /*
     * How long the public sign-in configuration is cached, in seconds. It decides layout
     * and changes when somebody edits it in the console, so this is short: long enough
     * that a page render is not a network call, short enough that flipping a provider on
     * shows up while somebody is still looking at the console.
     */
    'frontend_cache_ttl' => env('CBOX_ID_FRONTEND_CACHE_TTL', 60),

    /*
    |---------------------------------------------------------------------------
    | Scopes requested at login
    |---------------------------------------------------------------------------
    |
    | Space- (or comma-) separated in CBOX_ID_SCOPES. `openid` is required for an
    | id_token. Add, when you need them — each must be allowed on your app in
    | the console, or the login comes back with `invalid_scope`:
    |
    |   organizations   every organization the person belongs to, for a switcher
    |                   (`$user->organizations()`). Not needed to act FOR one:
    |                   `org`, `org_name` and `org_role` come with every token.
    |   offline_access  a refresh token, so `CboxId::refresh()` can renew access
    |                   without sending the person back through a login.
    |   groups          your app's roles on the id_token too, for consumers that
    |                   read only the id_token.
    |
    */

    'scopes' => array_values(array_filter(preg_split('/[\s,]+/', (string) env('CBOX_ID_SCOPES', 'openid profile email')) ?: [])),

    /*
    |---------------------------------------------------------------------------
    | Remembering who signed in
    |---------------------------------------------------------------------------
    |
    | After `authenticate()`, the SDK keeps what the rest of the session needs —
    | subject, organization and tier, roles, permissions, support actor — in the
    | Laravel session (never the tokens). That is what `CboxId::principal()`,
    | the permission gate and the `cbox-id.org` / `cbox-id.permission`
    | middleware read. It is bound to the local user your callback logs in, and
    | forgotten on logout. Turn off if you keep this yourself.
    |
    */

    'session' => [
        'remember' => (bool) env('CBOX_ID_REMEMBER_IDENTITY', true),
    ],

    /*
    |---------------------------------------------------------------------------
    | Authorization
    |---------------------------------------------------------------------------
    |
    | `gate` — answer `feature:action` abilities from the principal's Cbox ID
    | permissions, so `@can('invoices:create')`, `$user->can(…)` and
    | `$this->authorize(…)` work with no policy per permission. It only ever
    | GRANTS; your own gates and policies still decide everything else. Off by
    | default, because it adds a before-callback to every check you make.
    |
    */

    'authorization' => [
        'gate' => (bool) env('CBOX_ID_GATE', false),
    ],

    /*
    |---------------------------------------------------------------------------
    | Organizations
    |---------------------------------------------------------------------------
    |
    | `picker` — when a browser session reaches a `cbox-id.org` route without an
    | organization, send it to Cbox ID's hosted organization picker (and back).
    | Off, it is a plain 403 instead. JSON requests always get the 403.
    |
    */

    'organizations' => [
        'picker' => (bool) env('CBOX_ID_ORGANIZATION_PICKER', true),
    ],

    /*
    |---------------------------------------------------------------------------
    | Environment management API
    |---------------------------------------------------------------------------
    |
    | `key` is an ENVIRONMENT API key (`cbid_env_…`, console → Developers → API
    | keys) with the scopes your provisioning needs — `organizations:write`,
    | `members:write`, `invitations:write`, `roles:write`, … It can provision
    | every tenant in the environment: keep it server-side and out of logs.
    | `url` defaults to `{issuer}/api/v1`; the key only works on the host of
    | the environment it was minted for.
    |
    */

    'management' => [
        'key' => env('CBOX_ID_MANAGEMENT_KEY'),
        'url' => env('CBOX_ID_MANAGEMENT_URL'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Customer API keys
    |---------------------------------------------------------------------------
    |
    | Keys YOUR customers mint for YOUR API, checked with `cbox-id.api-key` or
    | `CboxId::verifyApiKey()`. A live answer is cached this many seconds (never
    | past the key's expiry) — that is how long a revoked key keeps working.
    | 0 asks Cbox ID on every request.
    |
    */

    'api_keys' => [
        'cache_ttl' => (int) env('CBOX_ID_API_KEY_CACHE_TTL', 60),
    ],

    /*
    |---------------------------------------------------------------------------
    | Back-channel logout (OpenID Connect Back-Channel Logout 1.0)
    |---------------------------------------------------------------------------
    |
    | When a person signs out of Cbox ID — or an admin ends their sessions, or
    | they lose access — Cbox ID POSTs a signed logout token to this app and the
    | SDK ends their local sessions. Turn it on, then register
    | `{app_url}{path}` as the app's back-channel logout URI in the console.
    |
    | `cache_store` must be shared by every web server (redis, database,
    | memcached): it holds the replay cache, the sid→session index and the
    | revocation list. Sessions are destroyed immediately on the database,
    | redis and cache-backed session drivers (and `file` on ONE server); with
    | the `cookie` driver they end on the browser's next request instead.
    |
    | `remember_tokens` — Laravel's remember-me cookie is per user, not per
    | session: `subject` (default) cycles it when a logout ends every session
    | of a person, `always` also for a single-session logout, `never` leaves
    | it alone.
    |
    */

    'backchannel_logout' => [
        'enabled' => (bool) env('CBOX_ID_BACKCHANNEL_LOGOUT', false),
        'path' => env('CBOX_ID_BACKCHANNEL_LOGOUT_PATH', '/cbox-id/backchannel-logout'),
        'cache_store' => env('CBOX_ID_BACKCHANNEL_LOGOUT_CACHE'),
        'max_age' => (int) env('CBOX_ID_BACKCHANNEL_LOGOUT_MAX_AGE', 300),
        'destroy_sessions' => true,
        'remember_tokens' => env('CBOX_ID_BACKCHANNEL_LOGOUT_REMEMBER_TOKENS', 'subject'),
    ],

    /*
     * The path of the hosted account / profile page on the Cbox ID instance that
     * `profileUrl()` / `redirectToProfile()` send a signed-in user to (self-service
     * password, MFA, passkeys, sessions). A `return_to` is appended so the page can
     * offer a link back to your app.
     *
     * `/account` is the person's own account area. (Before 0.13 this defaulted to
     * `/settings`, which on Cbox ID is the ORGANIZATION's settings page.)
     */
    'account_path' => env('CBOX_ID_ACCOUNT_PATH', '/account'),

    /*
     * HTTP timeout (seconds) for back-channel calls, and how long the discovery
     * document and JWKS are cached.
     */
    'http_timeout' => (int) env('CBOX_ID_HTTP_TIMEOUT', 10),
    'cache_ttl' => (int) env('CBOX_ID_CACHE_TTL', 3600),

    /*
     * Authorization manifest — declare this app's ROLES and PERMISSIONS in code, and
     * `php artisan cbox-id:publish-manifest` (e.g. on deploy) pushes them to Cbox ID.
     * Cbox ID owns identity + assignment; your app owns what a role means. Assigned
     * roles then arrive in the token's `roles`/`permissions` claims for you to enforce.
     * Requires the app's client to hold the `apps.manifest` scope.
     *
     * Permissions are `feature:action` keys; each role grants a subset of them.
     */
    'authz' => [
        'permissions' => [
            // ['key' => 'invoices:create', 'description' => 'Create invoices'],
            // ['key' => 'invoices:read', 'description' => 'View invoices'],
        ],
        'roles' => [
            // ['key' => 'billing-admin', 'name' => 'Billing Admin', 'description' => 'Full billing access',
            //     'permissions' => ['invoices:create', 'invoices:read']],
            //
            // A STAFF role — yours, not your customers': `tenant_assignable => false` keeps it
            // out of every organization's role picker; it can only be granted
            // environment-wide (CboxIdManagement::grantEnvironmentRole()). Strictly a
            // boolean — anything else refuses the whole manifest.
            // ['key' => 'support', 'name' => 'Support', 'tenant_assignable' => false,
            //     'permissions' => ['invoices:read', 'support:impersonate']],
        ],
    ],

    /*
     * Inbound webhooks — the "outbound provisioning" receiver. Cbox ID pushes signed
     * events (member added/removed, role assigned/unassigned, directory user
     * provisioned, …) to this app; the SDK verifies the signature and hands each to a
     * handler you register in a service provider:
     *
     *     use Cbox\Id\Client\Facades\CboxIdWebhooks;
     *     CboxIdWebhooks::on('organization.member_added', fn ($e) => Seat::allocate($e->string('user_id')));
     *
     * Then register `{app_url}{path}` as a webhook endpoint on the Cbox ID instance
     * (Developers → Webhooks) subscribed to those event types, and copy its signing
     * secret into `CBOX_ID_WEBHOOK_SECRET`. This is the low-ceremony alternative to
     * standing up a full SCIM server — no token round-trip, react out-of-band.
     */
    /*
    |---------------------------------------------------------------------------
    | Migrating off an old login
    |---------------------------------------------------------------------------
    |
    | While you move users to Cbox ID, it can ask YOUR system whether an email and
    | password it has never seen are good — and import that person on the yes.
    | Mount the handler yourself, so the path and middleware are yours:
    |
    |     Route::post('/cbox-legacy', LegacyLogin::using(
    |         fn (string $email, string $password) => ...
    |     ));
    |
    | No route is registered for you, deliberately: unlike webhooks, this endpoint
    | receives PASSWORDS, and where it lives and what sits in front of it should be
    | a decision somebody made rather than a default they inherited.
    |
    */
    'migration' => [
        // At least 32 characters. It is the only thing proving a request came from
        // Cbox ID, and `LegacyLogin::using()` refuses to build a handler without it.
        'secret' => env('CBOX_ID_LEGACY_SECRET'),
    ],

    'webhooks' => [
        'secret' => env('CBOX_ID_WEBHOOK_SECRET'),

        // When true (and a secret is set), the SDK registers a POST route at `path`.
        // Turn off to mount the controller yourself (custom middleware/path).
        'route' => env('CBOX_ID_WEBHOOK_ROUTE', true),
        'path' => env('CBOX_ID_WEBHOOK_PATH', '/cbox-id/webhooks'),

        // Reject a signature whose timestamp is older/newer than this many seconds
        // (replay + clock-skew bound). Matches Cbox ID's signing window.
        'tolerance' => (int) env('CBOX_ID_WEBHOOK_TOLERANCE', 300),

        // The receiver verifies + acknowledges immediately and runs your handlers on a
        // queued job (ProcessCboxIdWebhook), so a slow handler never stalls the
        // response. Point these at a real async connection/queue for true off-thread
        // processing; null uses the app defaults. (With QUEUE_CONNECTION=sync the job
        // runs inline — set a real queue in production to avoid slow acks.)
        'connection' => env('CBOX_ID_WEBHOOK_QUEUE_CONNECTION'),
        'queue' => env('CBOX_ID_WEBHOOK_QUEUE'),
    ],

];
