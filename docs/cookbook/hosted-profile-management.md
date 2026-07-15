---
title: Hosted profile management
description: Send a signed-in user to the Cbox ID instance's own account page — password, MFA, passkeys, sessions — and back to your app.
weight: 2
---

# Hosted profile management

Cbox ID hosts the account page — password, MFA, passkeys, active sessions. Rather
than rebuild any of that, send the user there and let them come back. This is the
same model as a hosted billing portal: you own the redirect, the instance owns the
screens.

## Redirect there and back

```php
use Cbox\Id\Client\Facades\CboxId;

Route::get('/account', fn () =>
    CboxId::redirectToProfile(returnTo: route('dashboard'))
)->middleware('auth');
```

The user is authenticated on the account page by **their existing Cbox ID session**
(the one established at login) — you are not passing any token. `returnTo` is handed
to the page so it can offer a link back to your app; the instance decides whether to
honor a given return URL.

## Just the URL

Need the link for a menu item or button rather than a redirect response? Use
`profileUrl()`:

```php
$href = CboxId::profileUrl(returnTo: route('dashboard'));
```

```blade
<a href="{{ CboxId::profileUrl(returnTo: route('dashboard')) }}">
    Manage your account
</a>
```

## Where it points

The target is `{issuer}{account_path}`, where `account_path` defaults to `/settings`
(see [Configuration](../getting-started/configuration.md)). Change `account_path`
only if your deployment serves its hosted profile at a different path.

## Scope note

Profile management — changing passwords, enrolling MFA, revoking sessions — is a
**platform** capability, served by the Cbox ID instance itself. This SDK's role is
only to route the user there with a return link; it does not expose APIs to mutate
those settings.
