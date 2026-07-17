# cboxdk/laravel-id-client

Laravel/PHP **consumer** SDK for Cbox ID — the package a *product* installs to
authenticate its users against a running Cbox ID instance (the opposite end from
[`cboxdk/laravel-id`](../laravel-id), which *is* the identity platform).

It speaks standard OpenID Connect, so integrating is a login redirect and a callback —
not a rewrite — with PKCE, CSRF `state`, a nonce, and full id_token signature/issuer/
audience verification handled for you. It adds the two conveniences a hosted-identity
product needs: a **redirect to the instance's hosted profile-management page**, and
back-channel helpers (**machine tokens, userinfo, introspection, webhook verification**).

Part of **Cbox ID** — the self-hostable, Laravel-native identity platform. MIT licensed.

## Install

```bash
composer require cboxdk/laravel-id-client
php artisan vendor:publish --tag=cbox-id-client-config
```

Requires PHP `^8.4` and Laravel 12 or 13.

Configure the instance and your OAuth client (registered on the Cbox ID instance):

```dotenv
CBOX_ID_ISSUER=https://id.acme.com
CBOX_ID_CLIENT_ID=client_...
CBOX_ID_CLIENT_SECRET=secret_...
CBOX_ID_REDIRECT=https://app.acme.com/auth/callback
```

Every endpoint (authorize, token, userinfo, jwks) is discovered from the issuer, so
that's usually all you configure.

## Log a user in

```php
use Cbox\Id\Client\Facades\CboxId;

// routes/web.php
Route::get('/auth/redirect', fn () => CboxId::redirect());          // → Cbox ID login

Route::get('/auth/callback', function (\Illuminate\Http\Request $request) {
    $cbox = CboxId::authenticate($request);   // verifies state, PKCE, id_token

    $user = User::updateOrCreate(
        ['cbox_id' => $cbox->id],                              // the stable `sub`
        ['email' => $cbox->email, 'name' => $cbox->name],
    );

    auth()->login($user);
    return redirect('/dashboard');
});
```

`authenticate()` returns a `CboxUser` — `id` (subject), `email`, `name`,
`organizationId`, the full verified `claims`, and the `accessToken` / `refreshToken`.
It throws `InvalidState` on a forged/stale callback and `AuthenticationFailed`
otherwise.

## Send users to hosted profile management

Let users manage their own password, MFA, passkeys and sessions on the instance's
hosted account page, then come back to your app:

```php
Route::get('/account', fn () => CboxId::redirectToProfile(returnTo: route('dashboard')));
// or just the URL: CboxId::profileUrl(route('dashboard'))
```

## Call Cbox ID APIs

```php
$token   = CboxId::machineToken(['api.read']);       // client-credentials (M2M)
$claims  = CboxId::userinfo($accessToken);           // OIDC userinfo
$active  = CboxId::introspect($token)['active'];      // RFC 7662
```

## Verify a webhook / action

```php
$ok = CboxId::verifyWebhook(
    payload: $request->getContent(),                 // the RAW body
    signatureHeader: $request->header('X-Cbox-Signature'),
    secret: config('services.cbox.webhook_secret'),
);
abort_unless($ok, 400);
```

## Receive provisioning webhooks (outbound provisioning)

Instead of standing up a SCIM server, register a hook and let the SDK verify and
route Cbox ID's signed events. Set `CBOX_ID_WEBHOOK_SECRET`, then in a service
provider's `boot()`:

```php
use Cbox\Id\Client\Facades\CboxIdWebhooks;

CboxIdWebhooks::on('organization.member_added', fn ($e) => Seat::allocate($e->string('user_id')));
CboxIdWebhooks::on('organization.member_removed', fn ($e) => Seat::release($e->string('user_id')));
CboxIdWebhooks::on('role.assigned', fn ($e) => /* … */);
CboxIdWebhooks::on('*', fn ($e) => Log::info('cbox event', ['type' => $e->type]));
```

The SDK mounts a signed receiver at `POST /cbox-id/webhooks` (configurable). Register
that URL as a webhook endpoint on your Cbox ID instance (Developers → Webhooks),
subscribe it to the event types you handle, and copy its signing secret into
`CBOX_ID_WEBHOOK_SECRET`. Each event's `deliveryId` is stable, so dedupe retries with
it. Signature verification (HMAC-SHA256, replay-bounded) and JSON parsing are handled
for you; a bad or stale signature is rejected before any handler runs.

## License

MIT © Cbox.
