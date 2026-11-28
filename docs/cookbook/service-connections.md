---
title: Organization service connections
weight: 65
description: Approve an organization grant once, lease scoped provider access in the background, and revoke centrally.
---

# Organization service connections

`Contracts\ServiceConnections` wraps the hosted Cbox ID service-connection API.
It requires a provider registered by the ID operator and a registered consumer
client. Installing this client does not register services or grant access.

Start authorization with `IdentityClient::redirect`, requesting
`service_connections.manage` alongside the login scopes and passing the selected
organization. Keep the selected organization, acting user, operation and expiry
in the session. Complete the normal PKCE/state/nonce callback and re-check both
the user and their current organization administrator role before calling
`connect($accessToken, $organizationId, $service, $expectedAudience)`.

The returned `ServiceConnection` contains the grant ID, organization, consumer,
service, exact audience and granted scopes. Check these scopes against the
provider contract before persisting the grant. It contains no secret. Do not
persist the human access token as a background credential.

`lease($connection)` obtains a machine token with `service_connections.lease`,
then asks ID for a short-lived provider token. The response must match the
grant's exact audience and scopes. Send that credential only to the configured
provider and never to browser props or logs. Renew before `expiresAt`.

`status($connection)` checks current validity. An inactive grant returns false;
network failure, invalid responses and mismatched identities throw
`ServiceConnectionFailed`. Never interpret an outage as permission to continue.
`revoke($humanAccessToken, $connection)` requires explicit server confirmation.
A user's logout does not revoke an organization grant; an authorized revocation
does. Reauthorize the human before revocation and verify the selected organization
again.

HTTP requests require HTTPS, refuse redirects and use bounded timeouts. There
is no automatic mutation retry: reconcile an uncertain result through the server
contract before retrying. Provider feed authorization and tenant isolation remain
the provider's responsibility.

Tests can replace the interface with `Testing\FakeServiceConnections`, queued
with explicit grants, leases, booleans or exceptions. The fake fails when no
result is queued; it does not manufacture a successful integration.

## First-party machine provisioning

`Contracts\ProvisionsServiceConnections` is a separate, optional capability for
operator-authorized first-party connections. It does not change the existing
`ServiceConnections` interface or bypass authorization for other consumers.
It requires `CBOX_ID_API_URL` and a confidential client allowed to request
`id:service-connection-provisions:write`.

```php
$connection = app(\Cbox\Id\Client\Contracts\ProvisionsServiceConnections::class)
    ->provision($organizationId, 'cbox_assets', $expectedAssetsAudience);
```

The SDK obtains its own machine credential and posts the explicit organization
to `/v1/service-connections/{service}/provision`. It never uses a person's token
or falls back to the legacy connect endpoint. The ID server determines whether
the consumer and organization are eligible. A matching active connection is
reused; a revoked or conflicting connection fails with HTTP 409 and is never
automatically reactivated. Caller or eligibility denial is 403; unavailable
eligibility is 503. `ServiceConnectionFailed::getCode()` preserves HTTP refusals.
After 401, the next explicit call obtains a fresh provisioning token; the SDK
does not retry the mutation automatically.

Provisioning, status and leasing also translate machine-token and discovery failures
into sanitized `ServiceConnectionFailed` exceptions, without chaining upstream
payloads. Token refusals preserve their HTTP status; unavailable discovery or transport
uses 503, and a successful token response missing its credential uses 502.

Validate the returned scopes against the provider contract before storing the
connection. Existing `status()` and `lease()` calls still check central revocation.

For tests, bind **both** contracts to the same queued fake:

```php
$fake = new \Cbox\Id\Client\Testing\FakeServiceConnections([$connection, $lease]);
app()->instance(\Cbox\Id\Client\Contracts\ProvisionsServiceConnections::class, $fake);
app()->instance(\Cbox\Id\Client\Contracts\ServiceConnections::class, $fake);
```

`provision()` consumes a matching `ServiceConnection` or throws the queued
exception. `lease()` consumes a `ServiceConnectionLease`. No separate fake or
person authorization is needed for this path.
