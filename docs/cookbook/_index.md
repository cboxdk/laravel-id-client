---
title: Cookbook
description: Task-oriented recipes — log in, hosted profile management, back-channel API calls, webhook verification, protecting your own API, multi-tenant apps, and the management API.
weight: 5
---

# Cookbook

Each recipe is a self-contained task. All of them go through the `CboxId` facade
(`Cbox\Id\Client\Facades\CboxId`); resolve `Cbox\Id\Client\IdentityClient` from the
container if you prefer constructor injection.

- **[Log in users](log-in-users.md)** — the redirect/callback pair, the `CboxUser`
  object, scopes, and error handling.
- **[Hosted profile management](hosted-profile-management.md)** — send users to the
  instance's account page and back.
- **[Call Cbox ID APIs](call-cbox-id-apis.md)** — machine tokens, UserInfo, and RFC
  7662 introspection.
- **[Verify webhooks](verify-webhooks.md)** — validate an inbound
  `X-Cbox-Signature`.
- **[Protect your API](protect-your-api.md)** — verify a bearer token presented to
  your own API, locally.
- **[Multi-tenant apps](multi-tenant-apps.md)** — teams, tiers, roles and
  permissions, switching organizations, invitations, staff and support sessions, and
  your customers' API keys.
- **[Kept permission decisions](kept-permission-decisions.md)** — ask Cbox ID about a
  person's permissions without making pages wait on it, and keep pages working while it
  is down.
- **[Management API](management-api.md)** — the typed, generated clients for the
  environment, workspace, platform and account planes: idempotent writes, approvals,
  pagination and Audit Logs.
- **[Feature flags, fine-grained authorization and Pipes](feature-flags-fga-and-pipes.md)** —
  `hasFeature()`, the `feature:` middleware and `@feature`; relationship checks with
  consistency tokens; leasing a person's connected-account token.
- **[App catalog (Switch app)](app-catalog.md)** — serve the person's apps to the app
  switcher with one route line, renewed on the server so nobody clicks "reconnect".
- **[Organizations in the shared shell](shared-shell.md)** — the shell's Organization
  level on local data, the token following `/orgs/{slug}`, and "New organization".
- **[Back-channel logout](back-channel-logout.md)** — end a person's sessions in your
  app when they sign out of Cbox ID.
- **[Protect your API](protect-your-api.md)** — verify a token presented *to* you,
  and gate routes on scopes.
- **[CIBA and the device grant](ciba-and-device-grant.md)** — ask a known person to
  approve an action on their phone, or sign a CLI in with a code, without hand-rolling
  the polling.
- **[In-cluster routing](in-cluster-routing.md)** — send calls to Cbox ID and other
  services to their address inside your Kubernetes cluster with `CBOX_INTERNAL_ROUTES`.
- **[Integration prompt (for AI agents)](integration-prompt.md)** — a self-contained
  brief to hand Claude Code (or a developer) to wire up login in a new SaaS.

- **[User preferences](user-properties.md)** — versioned settings, namespace grants, conflict handling and cache invalidation.
