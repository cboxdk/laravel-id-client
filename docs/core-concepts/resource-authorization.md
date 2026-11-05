---
title: Resource authorization
weight: 4
description: Acquire a dedicated API bearer for an already signed-in person with PKCE and exact identity bindings.
---

# Authorizing a separate resource API

`ResourceAuthorizationClient` obtains a dedicated API token for a person already authenticated in your product. It does not sign the person into the product and never calls issuer-only UserInfo. Existing `IdentityClient` login behavior is unchanged.

Resolve the client from Laravel's container. Call `redirect($resource, $organizationId, $expectedSubject, $redirectUri, $scopes, $context)` from an authenticated route. Use a configured HTTPS resource and callback, the current server-side identity's subject and organization, and explicit API scopes. Context is a string map kept on the server (for example, a product resource ID), not a browser-controlled return URL. `openid` and `offline_access` are refused.

On the fixed callback route, call `authenticate($request)`. The typed result exposes `subject`, `organizationId`, `resource`, `scopes`, `expiresAt` (Unix seconds), `context`, and `accessToken()`. Recheck that the current product session still represents the result's subject and organization before storing the credential against that product resource. Keep the bearer server-side; do not return or log it. JSON and debug output omit it.

Use a server-side session driver and Laravel session blocking (`->block()`) on both routes to serialize state consumption across concurrent requests. Cookie sessions are refused. Each authorization has independent random state and PKCE S256, expires after ten minutes, and is consumed once even on error. At most ten outstanding authorizations are retained. Starting another tab does not overwrite the first tab's state.

The exchange uses the saved callback and PKCE verifier. The existing AccessTokenVerifier checks the JWT signature, issuer, audience, expiry and requested scopes; this client also requires the exact single resource audience, configured client, expected subject and organization, and rejects impersonation and machine subjects. Transport failures produce a redacted exception, without token-response bodies. Local verification does not establish current membership or revocation: the receiving resource server must apply its own online authorization boundary.

Register the exact callback and resource/scopes in Cbox ID before enabling the flow. This local additive implementation does not create those registrations, deploy server introspection grants, or release the package.

### Sender-constrained account grants

Pass `dpop: true` when starting a resource authorization for a client that uses DPoP. The SDK generates a P-256 holder key for this authorization, keeps it with the server-side PKCE transaction and signs the token exchange. The callback checks the returned token type and the signed token’s confirmation thumbprint against that holder key. A bearer response or a different thumbprint fails authorization.

Use `$grant->requestHeaders('GET', $endpoint)` for each request to the authorized resource origin. It generates a fresh signed proof including the method, endpoint and access-token hash. Keep the grant in server-side storage and discard it on sign-out, expiry or a change of subject or organization. Existing inbound bearer-token verification continues to reject sender-constrained tokens.
