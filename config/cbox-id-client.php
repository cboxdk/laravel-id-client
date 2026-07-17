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
     * Your OAuth client credentials, registered on the Cbox ID instance. The secret
     * is required for confidential clients (server-side apps) and for machine tokens
     * and introspection.
     */
    'client_id' => env('CBOX_ID_CLIENT_ID'),
    'client_secret' => env('CBOX_ID_CLIENT_SECRET'),

    /*
     * Your app's callback URL — must exactly match one registered on the client.
     */
    'redirect' => env('CBOX_ID_REDIRECT'),

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
    'webhooks' => [
        'secret' => env('CBOX_ID_WEBHOOK_SECRET'),

        // When true (and a secret is set), the SDK registers a POST route at `path`.
        // Turn off to mount the controller yourself (custom middleware/path).
        'route' => env('CBOX_ID_WEBHOOK_ROUTE', true),
        'path' => env('CBOX_ID_WEBHOOK_PATH', '/cbox-id/webhooks'),

        // Reject a signature whose timestamp is older/newer than this many seconds
        // (replay + clock-skew bound). Matches Cbox ID's signing window.
        'tolerance' => (int) env('CBOX_ID_WEBHOOK_TOLERANCE', 300),
    ],

];
