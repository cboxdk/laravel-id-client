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
