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
     * The scopes requested at login. `openid` is required for an id_token.
     *
     * @var list<string>
     */
    'scopes' => ['openid', 'profile', 'email'],

    /*
     * The path of the hosted account / profile page on the Cbox ID instance that
     * `profileUrl()` / `redirectToProfile()` send a signed-in user to (self-service
     * password, MFA, passkeys, sessions). A `return_to` is appended so the page can
     * offer a link back to your app.
     */
    'account_path' => '/settings',

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
