---
title: In-cluster routing
description: Send server-to-server calls to Cbox ID and other services straight to their address inside a Kubernetes cluster, while every check keeps seeing the public URL.
weight: 66
---

# In-cluster routing

When your app and Cbox ID run in the same Kubernetes cluster, a call to
`https://id.example.com` normally leaves the cluster, passes the edge proxy and comes
back in, with a new TLS handshake each time. `CBOX_INTERNAL_ROUTES` sends those calls
to the service address inside the cluster instead.

```dotenv
CBOX_INTERNAL_ROUTES=id.example.com=http://cbox-id.cbox-id.svc.cluster.local,billing.example.com=http://billing.billing.svc.cluster.local
```

Each entry is `public-host=internal-base-url`, separated by commas. Leave it empty (the
default) and nothing changes.

## What it does

The package adds a request middleware to Laravel's HTTP client. When a request is sent
to `https://<public-host>` (port 443) and the host matches an entry exactly, the request
goes to the internal base URL instead, with the same path and query, and with these
headers:

| Header | Value |
|---|---|
| `Host` | the public host |
| `X-Forwarded-Proto` | `https` |
| `X-Forwarded-Host` | the public host |
| `X-Forwarded-Port` | `443` |

Everything else is left alone: other hosts, subdomains of a listed host, plain `http`
URLs and other ports.

It applies to every call made through Laravel's HTTP client (`Http::`), so this package's
own calls (discovery, JWKS, token, userinfo, permission decisions, the management API) and
your application's own calls to the other hosts you list.

## Keeping one request on the public path

Some calls exist to test the public route: an uptime probe of a public URL, a check of
the certificate or of what the CDN serves. Add `InternalRoutes::publicRoute()` to the
headers of such a request:

```php
use Cbox\Id\Client\Support\InternalRoutes;
use Illuminate\Support\Facades\Http;

$response = Http::withHeaders(InternalRoutes::publicRoute())
    ->timeout(10)
    ->get('https://app.cboxbilling.com/up');
```

That request is sent as written, to the public host, with no `X-Forwarded-*` headers
added. The marker header (`X-Cbox-Public-Route`) is removed before the request leaves, so
the target never sees it. Every other request to the same host still goes inside.
`publicRoute()` returns an empty array when no internal routes are configured, so the
same code runs unchanged in an environment without them.

An app that probes public URLs can therefore list its API hosts in
`CBOX_INTERNAL_ROUTES` and mark only the probes. A host guard that inspects the URL of
the request (an SSRF check) keeps seeing the public URL for a marked request.

Only the transport changes. Your `CBOX_ID_ISSUER` stays the public URL, and the issuer
check on discovery, the `iss` and `aud` checks on tokens, and the HTTPS checks all keep
working against it, because the request is rewritten only when it is sent.

## Rules for entries

- The public host is a plain host name: no scheme, port, path or wildcard.
- The internal base URL is `http` or `https`, its host ends in `.svc` or
  `.svc.cluster.local`, and it has no path, query or credentials. A port is kept.
- `localhost` and `127.0.0.1` are accepted only when the application runs in the
  `testing` environment.

An entry that breaks a rule is ignored and a warning is logged at boot
(`Cbox internal route ignored: …`). The valid entries still apply.

## What the receiving app needs

- **Trust proxy headers from the pod network.** The receiving app must accept
  `X-Forwarded-*` from the addresses your pods call from, or it builds `http://` URLs
  and redirects. In Laravel that is `TRUSTED_PROXIES` / `trustProxies()` set to the pod
  CIDR (for example `10.244.0.0/16`). Cbox ID already trusts its pod network this way.
- **Answer on plain HTTP inside the cluster.** The service must serve the path you call
  without redirecting to HTTPS when `X-Forwarded-Proto: https` is present.

## Security

Traffic inside the cluster is plain HTTP when the internal URL is `http`. Tokens and
client secrets then cross the pod network unencrypted, unless the cluster encrypts pod
traffic (for example with WireGuard in the CNI or a service mesh). Decide that before
turning this on.

The rewrite only touches hosts you list, and only towards cluster service addresses, so a
typo cannot send traffic to a host outside the cluster.

## Testing

Leave `CBOX_INTERNAL_ROUTES` unset in your test environment. `Http::fake()` sees the
rewritten URL, so a fake keyed on the full public URL would no longer match while it is
set. Fakes keyed on a path (`'*/oauth/token'`) match either way.

A client built by hand outside Laravel (`HttpBackchannelAuthentication::make()`,
`HttpDeviceAuthorization::make()`, or a management client given its own `Factory`) uses
its own HTTP factory and is not routed.
