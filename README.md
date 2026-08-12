# cboxdk/laravel-id-client

Laravel/PHP **consumer** SDK for Cbox ID — the package a *product* installs to
authenticate its users against a running Cbox ID instance (the opposite end from
[`cboxdk/laravel-id`](../laravel-id), which *is* the identity platform).

It speaks standard OpenID Connect, so integrating is a login redirect and a callback —
not a rewrite — with PKCE, CSRF `state`, a nonce, and full id_token signature/issuer/
audience verification handled for you. It adds the two conveniences a hosted-identity
product needs: a **redirect to the instance's hosted profile-management page**, and
back-channel helpers (**machine tokens, userinfo, introspection, revocation, webhook
verification**).

Part of **Cbox ID** — the self-hostable, Laravel-native identity platform. MIT licensed.

## Migrating off an old login

While you move users to Cbox ID, it can ask your system whether an email and password it
has never seen are good — and import that person on the yes. You write the one function
that knows your database; the handler owns the signature, the freshness window and the
constant-time compare:

```php
use Cbox\Id\Client\Migration\{LegacyLogin, LegacyUser};

Route::post('/cbox-legacy', LegacyLogin::using(function (string $email, string $password): ?LegacyUser {
    $row = DB::connection('legacy')->table('users')->where('email', $email)->first();

    return $row && Hash::check($password, $row->password)
        ? new LegacyUser($row->email, $row->name, $row->confirmed_at !== null, $row->password)
        : null;
}));
```

Set `CBOX_ID_LEGACY_SECRET` to at least 32 characters. `LegacyLogin::using()` refuses to
build without it — at boot, where somebody is looking, rather than as a 500 that reads as
an outage.

Return `null` for "wrong password". **Throwing is different**: it means your store could
not decide, and is answered with 503 so Cbox ID refuses the sign-in rather than reading an
outage as a bad credential. Returning the stored hash lets the person keep their password
verbatim; omit it and Cbox ID hashes the one they just proved they know.

No route is registered for you, deliberately: unlike webhooks, this endpoint receives
passwords, so where it lives and what sits in front of it should be a decision somebody
made rather than a default they inherited.

## Install

```bash
composer require cboxdk/laravel-id-client
php artisan vendor:publish --tag=cbox-id-client-config
```

Requires PHP `^8.4` and Laravel 12 or 13.

Configure the instance and your OAuth client (registered on the Cbox ID instance):

```dotenv
CBOX_ID_ISSUER=https://acme.cboxid.com
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

## Draw your own sign-in box

Reading the environment's public configuration from PHP lets a Blade page render a sign-in
box in the customer's own branding — with no JavaScript SDK, and no flash of unstyled form
while one loads. A **publishable** key is the opposite of the client secret above: public on
purpose, and useful only from the origins its owner listed against it.

```php
// config/cbox-id-client.php — CBOX_ID_PUBLISHABLE_KEY
use Cbox\Id\Client\Frontend\FrontendClient;

$config = app(FrontendClient::class)->config();

$config->endpoint('authorization');  // where the form posts on to
$config->social;                     // the buttons this environment has enabled
$config->accent();                   // the customer's brand colour
$config->isLive();                   // false for a pk_test_ key — draw the badge
```

And who is signed in, given a token you already hold:

```php
$session = app(FrontendClient::class)->session($accessToken);

$session->signedIn();          // false is a state, not an error
$session->user?->initials();   // 'AL' — for the avatar fallback
```

The key grants nothing on its own: `session()` is authorized by the token, and `config()`
answers the same document to everybody. The configuration is cached for a minute
(`CBOX_ID_FRONTEND_CACHE_TTL`) because it decides layout and a page render is not a good
place for a network call.

**Before it works:** an operator turns the Frontend API on (`CBOX_ID_FRONTEND_API=true` —
it is off by default) and mints a key under **Developers → Frontend keys**, listing the
origins allowed to use it. Exact matches only: `https://acme.com` does not cover
`https://www.acme.com`.

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
CboxId::revoke($refreshToken, 'refresh_token');      // RFC 7009
```

Revoking a refresh token drops the whole token family — that's what "sign out
everywhere" needs.

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
`CBOX_ID_WEBHOOK_SECRET`. Signature verification (HMAC-SHA256, replay-bounded) and JSON
parsing are handled for you; a bad or stale signature is rejected before anything runs.

**The receiver is slim.** It verifies, acknowledges immediately, and runs your handlers
on a queued job (`ProcessCboxIdWebhook`) — so a slow handler never stalls the response
or trips the dispatcher's timeout/retry. Point `CBOX_ID_WEBHOOK_QUEUE_CONNECTION` /
`CBOX_ID_WEBHOOK_QUEUE` at a real async queue in production (with `QUEUE_CONNECTION=sync`
the job runs inline). Each event's `deliveryId` is stable, so dedupe retries with it.

## License

MIT © Cbox.
