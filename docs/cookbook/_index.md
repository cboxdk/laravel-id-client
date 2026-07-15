---
title: Cookbook
description: Task-oriented recipes — log in, hosted profile management, back-channel API calls, and webhook verification.
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
