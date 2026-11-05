---
title: Read and change user preferences
description: Versioned preferences and custom metadata through the public OAuth property API.
weight: 8
---

# Read and change user preferences

Resolve `Cbox\Id\Client\Contracts\UserProperties` to read or patch a namespace.
The bound HTTP client uses the configured issuer, refuses redirects, preserves
JSON objects and arrays, and exposes sanitized `UserPropertiesFailed` errors.

```php
use Cbox\Id\Client\Contracts\UserProperties;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\UserProperties\PropertyPatch;
use Cbox\Id\Client\UserProperties\PropertyToken;

$token = new PropertyToken(app(IdentityClient::class)->machineToken([
    'user_properties:read', 'user_properties:write',
]));
$properties = app(UserProperties::class);
$document = $properties->read($userId, 'preferences', $token);
$updated = $properties->patch($userId, 'preferences', new PropertyPatch(
    version: $document->version,
    set: (object) ['language' => 'da'],
), $token);
```

The OAuth client also needs explicit read/write grants on the namespace. Scopes
alone do not grant access. User tokens are restricted to their own subject;
machine tokens require a namespace grant. Do not replace existing namespace grants
when adding an application. A DPoP-bound token must provide `PropertyToken`'s proof
callback `(method, absoluteUrl, accessToken): string`; the caller owns its key and
proof lifecycle. Plain tokens use Bearer authentication.

The endpoints are `GET` and `PATCH /oauth/users/{user}/properties/{namespace}`.
`PropertyDocument` contains `userId`, `namespace`, `data` (`stdClass`) and `version`.
An empty namespace has an empty object and version zero. `PropertyPatch` sends
`version`, object `set` and list `remove`. Null values remain null; they do not mean
remove. Only explicit choices belong in `set`. Never persist inferred defaults.

On a 409 conflict, refetch and reapply only the requested keys, with a bounded
retry. The SDK deliberately does not retry a patch. Clear cached data on 401/403;
retain the last known data during transient outages. Cache keys must include issuer,
user and namespace, and replacement must be atomic and monotonic by version.
Verified `user.preferences.updated` and `user.metadata.updated` webhooks invalidate
a version; their payload is not a replacement document. Refetch through your own
authorized credentials. Delete cached properties when a user is erased.

The shared `preferences` namespace distinguishes interface `language`, regional
`locale`, IANA `timezone`, `date_format`, `time_format`, `week_start` and `theme`.
If the host does not support a person's interface language, fall back locally
without overwriting their global choice. See the Cbox ID preference schema for
accepted values; custom namespaces are separately registered and granted.

In tests bind `Testing\FakeUserProperties` with an explicit queue of documents or
`UserPropertiesFailed` results. It records calls without retaining tokens and
throws when its queue is exhausted. A fake does not verify issuer grants or an
actual token exchange; integration tests must use an isolated Cbox ID instance.
