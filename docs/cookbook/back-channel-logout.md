---
title: Back-channel logout
description: When a person signs out of Cbox ID, end their sessions in your app too — OpenID Connect Back-Channel Logout, validated strictly, with honest limits per session driver.
weight: 8
---

# Back-channel logout

When a person signs out of Cbox ID — or an administrator ends their sessions, their
account is deactivated, or they are removed from an organization — Cbox ID POSTs a signed
**logout token** to every app they used (OpenID Connect Back-Channel Logout 1.0). The SDK
validates it and ends their sessions in your app.

## Turn it on

```dotenv
CBOX_ID_BACKCHANNEL_LOGOUT=true
CBOX_ID_BACKCHANNEL_LOGOUT_CACHE=redis     # a cache every web server shares
```

Then register `https://your-app.example/cbox-id/backchannel-logout` as the app's
**back-channel logout URI** in the Cbox ID console (the path is
`CBOX_ID_BACKCHANNEL_LOGOUT_PATH`). Tick "session required" only if you want one token
per session rather than one per person — the SDK handles both.

That is all. The receiver is mounted outside the `web` group — no session, no CSRF check
to exclude it from — because the caller is Cbox ID's server and the token's signature is
its authentication. The `web` group gets a small middleware (`cbox-id.session`) that signs
out a browser whose session was ended.

Sign-in needs nothing new: `CboxId::authenticate()` already remembers the ID Token's `sid`,
which is what a logout token names.

## What is checked

Every point of §2.6, and a refusal says which one failed (Cbox ID records it in the
environment's audit trail):

- the signature, against the issuer's published keys, with the algorithm pinned;
- `typ` is `logout+jwt` when present;
- `iss` is your issuer and `aud` is your client id;
- `iat` and `exp` are present, the token has not expired, and it was issued within the
  last `CBOX_ID_BACKCHANNEL_LOGOUT_MAX_AGE` seconds (300);
- `jti` is present and has not been seen before;
- `events` carries the back-channel logout member, as an object;
- there is **no** `nonce` — so an ID Token can never be accepted here;
- `sub`, `sid` or both are present.

Answers: `200` done, `400 invalid_request` refused (final), `503` the signing keys could
not be read (Cbox ID retries).

## What gets ended

A token with `sid` ends the one session that signed in with it. A token with only `sub`
ends every session of that person that signed in **no later than** the token was issued —
so a late, retried notice cannot end the session they opened after signing straight back in.

| Session driver | What happens |
|---|---|
| `database`, `redis`, `memcached`, `dynamodb` | The sessions are deleted at once. |
| `file` | Deleted at once on a single server. Behind a load balancer, only the server that received the token can delete its files; the others' sessions end on their next request. |
| `cookie` | The session lives in the browser, so there is nothing to delete. It ends on the browser's next request. |
| `array` | Tests only. |

"On the next request" is the middleware: it checks every signed-in request against the
revocation list and, on a match, logs the user out and invalidates the session; the
request carries on as a guest, and your `auth` middleware decides what a guest sees. A browser that never comes back never needs signing out.

**The cache must be shared.** The replay cache, the session index and the revocation list
live in `CBOX_ID_BACKCHANNEL_LOGOUT_CACHE` (default: your default cache). If each web
server has its own (`array`, or `file` on local disks), a token received by one server is
invisible to the others.

### Remember-me

Laravel's remember-me cookie belongs to the **user**, not to a session. Deleting a session
does not clear it, and the next request would log the person straight back in. So when a
logout ends every session of a person, the SDK cycles the remember token of the local users
those sessions were logged in as, which invalidates every remember-me cookie they hold.

A single-session (`sid`) logout cannot do that without signing the person out on every
device, so by default it does not. Choose with `CBOX_ID_BACKCHANNEL_LOGOUT_REMEMBER_TOKENS`:
`subject` (default), `always`, or `never`. The middleware path clears the cookie on the one
browser it signs out either way.

## React to it

```php
use Cbox\Id\Client\Events\BackchannelLogoutReceived;

Event::listen(BackchannelLogoutReceived::class, function (BackchannelLogoutReceived $event) {
    // $event->token->subject, $event->token->sid
    // $event->ended->sessionIds, $event->ended->localUserIds
    PersonalAccessTokens::revokeFor($event->token->subject);
});
```

Anything else a sign-out should end — API tokens you minted, websocket connections,
per-user caches — belongs in a listener.

## Placing the middleware yourself

With the feature on, `cbox-id.session` is appended to the `web` group for you. If you
build your groups differently, add the alias to the routes that carry a signed-in session.
