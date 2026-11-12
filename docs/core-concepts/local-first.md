---
title: What a request waits for
description: Which calls to Cbox ID happen inside a person's request, which happen in the background, and what the app needs to run for that.
weight: 2
---

# What a request waits for

A page or a sign-in reads what the app already holds. Cbox ID is asked again in the
background: after the response, in a queued job, or by the scheduled `cbox-id:refresh`.
A request waits for Cbox ID in three cases only:

- nothing is kept yet (the app's first request, a person's first sign-in in this app);
- the authorization-code exchange of a sign-in;
- a change (POST, PUT, PATCH, DELETE) that would otherwise act on a stale permission answer.

## Call by call

| Call | A returning person's request | The first time |
|---|---|---|
| `POST /oauth/token` (code exchange) | Made. It is the sign-in. | Made. |
| `GET /oauth/userinfo` | Not made when the verified id_token carries what the requested scopes promise. | Made when the id_token leaves any of it out. |
| Discovery document, JWKS | Read from the cache. Fetched again in the background once older than `cache_ttl`. | Fetched once per app. |
| `POST /oauth/token` (machine token) | Read from the cache. Renewed before it expires. | Fetched once per scope set. |
| `POST /oauth/decisions` | A page view reads the kept answer and a job asks again. A change with a stale answer asks now. | Asked. |
| `GET /app-catalog/for-user` | Read from the kept catalog. Read again in the background when stale. | Queued at sign-in. Asked in the request only when the switcher opens before that job ran. |
| `GET /oauth/users/{id}/properties/{namespace}` | `KeptUserProperties` reads the kept copy. | Asked. |

## UserInfo at sign-in

`authenticate()` verifies the id_token and then decides. It skips UserInfo when the
id_token holds:

- `sub`;
- `email` when the `email` scope was requested;
- `name` when the `profile` scope was requested;
- `organizations` (a list, possibly empty) when the `organizations` scope was requested;
- at least one of `email`, `name`, `organizations`, `roles`, `permissions`.

The last rule tells a complete id_token from a minimal one (`sub`, `org`, `org_name`,
`org_role`), which never carries roles or permissions. For a minimal one UserInfo is
called, as before. A Cbox ID that puts `email` or `name` in the id_token must therefore
put the rest of UserInfo there too: `email_verified`, `org`, `org_name`, `org_role`,
`roles` and `permissions` when the person holds any, and `feature_flags`.

Nothing about verification changes. The subject comes from the signature. When UserInfo
is called, its `sub` must match and its claims never replace signed ones. An `openid`
sign-in without an id_token is refused. `refresh(remember: true)` decides the same way.

Set `CBOX_ID_USERINFO=always` to call UserInfo on every sign-in.

## What the app must run

Nothing new is required. Each piece falls back to the request when the app runs less:

| The app runs | What it gets |
|---|---|
| A scheduler (`schedule:run`) | `cbox-id:refresh` every five minutes: discovery, JWKS and machine tokens are renewed before any request finds them stale. The package registers the schedule itself. |
| A queue worker | Refreshes of permission decisions, the app catalog and user properties run as jobs. |
| Neither | The same refreshes run after the response of the request that found something stale. A machine token that expired while the app had no traffic is fetched by the next request, as before. |

Turn the schedule off with `CBOX_ID_BACKGROUND_SCHEDULE=false`. Send the jobs to a
queue of their own with `CBOX_ID_BACKGROUND_QUEUE_CONNECTION` and `CBOX_ID_BACKGROUND_QUEUE`.

## How old a kept copy can be

| Kept | Refreshed in the background after | Used at most |
|---|---|---|
| Discovery, JWKS | `cache_ttl`, one hour | `cache_keep`, seven days |
| Machine token | when less than `token_cache.refresh_ahead` (ten minutes) is left | its own lifetime minus a minute |
| Permission decision | `decisions.fresh_for`, 30 seconds | `decisions.max_age`, thirty days |
| App catalog | Cbox ID's `refresh_after`, at least a minute | thirty days |
| User properties | `user_properties.fresh_for`, five minutes | `user_properties.keep_for`, thirty days |

A token signed with a key the kept JWKS does not have forces a fetch at once, so a
rotated signing key never waits for the background.

## When access is taken away

A kept permission "yes" ends when Cbox ID says so. The package handles these webhooks
itself and marks the person, or the organization, as changed. Every answer kept before
the mark is ignored and the next check asks Cbox ID:

- for one person: `user.deactivated`, `user.reactivated`, `organization.member_removed`,
  `organization.member_role_changed`, `membership.deleted`, `membership.updated`,
  `role.assigned`, `role.unassigned`, `role.assigned_everywhere`,
  `role.unassigned_everywhere`, `governance.access.revoked`,
  `directory.user.deprovisioned`, `directory.user.deactivated`,
  `directory.group.membership_changed`;
- for an organization: `organization.suspended`, `organization.reactivated`,
  `organization.archived`, `organization.deleted`;
- a back-channel logout, for the person it names.

This needs the [webhook receiver](../cookbook/verify-webhooks.md) with its secret, the
endpoint subscribed to those events in Cbox ID, and a worker for the webhook queue.

How long a revoked person keeps access through kept decisions:

| Situation | Longest delay |
|---|---|
| A change (POST, PUT, PATCH, DELETE, or `acting: true`) | `fresh_for`: 30 seconds. Never longer, with or without webhooks. |
| A page view, webhooks delivered | Until the webhook job has run. |
| A page view, no webhooks | `fresh_for`, then one more page view shows the old answer while the refresh runs. A person who was away sees one stale page when they come back. |
| A page view, no webhooks, and the refresh cannot run (Cbox ID or the queue is down) | `max_age`: thirty days. Lower `CBOX_ID_DECISIONS_MAX_AGE` to shorten it. |

The roles and permissions the session remembers from sign-in are separate from kept
decisions and are as old as the sign-in. Back-channel logout ends those sessions.
