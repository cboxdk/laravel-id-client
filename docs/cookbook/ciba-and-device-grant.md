---
title: CIBA and the device grant
weight: 11
description: Ask a person to approve on their phone (OpenID CIBA, poll mode), or sign a CLI in with a code (RFC 8628) — typed, tested, no hand-rolled polling.
---

# CIBA and the device grant

Two flows get a person's token without a browser redirect in your app. Don't write
the polling loop yourself. Use the SDK's helpers:

- `Contracts\BackchannelAuthentication` for OpenID CIBA Core 1.0 in poll mode.
- `Contracts\DeviceAuthorization` for the RFC 8628 device authorization grant.

Both find their endpoints in the issuer's RFC 8414 metadata
(`{issuer}/.well-known/oauth-authorization-server`). If an endpoint is missing they
fall back to `/oauth/backchannel_authentication`, `/oauth/device_authorization` and
`/oauth/token`. They wait the `interval` Cbox ID asks for, add five seconds on every
`slow_down`, and throw a typed exception for every other answer.

## Which one?

| | CIBA | Device grant |
|---|---|---|
| Who starts it | Your server, for a person it already knows | A device or CLI, for whoever is at the keyboard |
| How you name the person | `login_hint`: a Cbox ID user id (`usr_…`) or a confirmed email | You don't. They sign in themselves |
| What the person does | Approves a prompt from Cbox ID on their own device | Opens a URL, enters a short code, signs in |
| Typical use | An agent or background job that needs approval for one action ("Approve invoice export #42") | `mycli login`, a TV app, a terminal with no browser |
| Client | Confidential (`private_key_jwt` or a secret). A public `none` client only where the resource allows public backchannel requests; elsewhere Cbox ID answers `invalid_target` | Public (`client_id` only) or confidential |

Use CIBA when your system already knows who must approve. Use the device grant when
the person has to prove who they are.

## Configure

The helpers read the same `client_id`, `client_secret` and `issuer` as the rest of
the SDK. Client authentication is chosen like this:

| Config | Env | Effect |
|---|---|---|
| `client_authentication.method` | `CBOX_ID_CLIENT_AUTH_METHOD` | `client_secret_basic`, `client_secret_post`, `private_key_jwt` or `none`. Leave it empty to pick automatically: a private key means `private_key_jwt`, a secret means `client_secret_basic`, neither means a public client. |
| `client_authentication.private_key` | `CBOX_ID_PRIVATE_KEY` | The PEM private key, or a path to it. |
| `client_authentication.key_id` | `CBOX_ID_KEY_ID` | The `kid` of the public key registered with Cbox ID. |
| `client_authentication.algorithm` | `CBOX_ID_KEY_ALGORITHM` | `RS256` (RSA, 2048 bits or more) or `ES256` (P-256). |

With `private_key_jwt`, every request carries a new client assertion (RFC 7523):
`iss` and `sub` are the client id, `aud` is the token endpoint, and it has a fresh
`jti` and expires after 60 seconds. Cbox ID accepts each `jti` only once, so an
assertion is never reused.

A self-registered client registers either `private_key_jwt` with an inline `jwks`
(recommended) or `none`. It must send a `resource` (RFC 8707) when it starts a
request and on every token poll, and for CIBA a `binding_message`. Pass `resource`
to `start()`; the helper sends the same value on every poll.

Cbox ID supports CIBA in poll mode only, and doesn't support
`backchannel_user_code_parameter`. The helper never sends a `user_code`.

## Laravel: approve an action with CIBA

Start the request in a web request or a job, keep the pending request, and poll from
a queued job. Never block a web request on `waitForToken()`.

The example below asks a person to approve an agent writing to their Cbox Brain
over MCP. The resource is Cbox Brain's MCP endpoint, `https://app.cboxbrain.com/mcp`,
with the scopes `mcp:read` and `mcp:write`.

```php
use Cbox\Id\Client\Contracts\BackchannelAuthentication;
use Cbox\Id\Client\Exceptions\AuthorizationDenied;
use Cbox\Id\Client\Exceptions\GrantRateLimited;
use Cbox\Id\Client\ValueObjects\PendingBackchannelAuthentication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class AskToSaveNotes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public AgentTask $task) {}

    public function handle(BackchannelAuthentication $ciba): void
    {
        try {
            $pending = $ciba->start(
                loginHint: $this->task->user->cbox_id,       // usr_… or a confirmed email
                scope: 'mcp:read mcp:write',
                bindingMessage: 'Let the agent save meeting notes to Cbox Brain',
                resource: 'https://app.cboxbrain.com/mcp',
            );
        } catch (GrantRateLimited $e) {
            $this->release($e->retryAfter);                 // Cbox ID asked us to back off

            return;
        }

        $this->task->update(['approval' => $pending->toArray()]);
        WaitForApproval::dispatch($this->task);
    }
}

class WaitForApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public AgentTask $task) {}

    public function handle(BackchannelAuthentication $ciba): void
    {
        $pending = PendingBackchannelAuthentication::fromArray($this->task->approval);

        try {
            $tokens = $ciba->waitForToken($pending, maxWait: 240);
        } catch (AuthorizationDenied) {
            $this->task->markDeclined();

            return;
        }

        $this->task->runAgainstBrain($tokens->accessToken);  // Bearer token for the MCP endpoint
    }
}
```

To check once and come back later, for example from a scheduled command, call
`poll($pending, $interval)`. It returns a `PollResult`. If `granted()` is false,
wait `$result->interval` seconds before you poll again.

## CLI: sign in with the device grant

Outside a Laravel container, build the helper with `make()`:

```php
use Cbox\Id\Client\Grants\ClientAuthentication;
use Cbox\Id\Client\Grants\HttpDeviceAuthorization;

$device = HttpDeviceAuthorization::make(
    'https://id.example.com',
    ClientAuthentication::none('cid_mycli'),          // a public CLI client
);

$pending = $device->start('mcp:read mcp:write', resource: 'https://app.cboxbrain.com/mcp');

echo "Open {$pending->verificationUri} and enter {$pending->userCode}\n";
// or render $pending->verificationUriComplete as a QR code

$tokens = $device->waitForToken($pending);          // blocks until approved or expired

file_put_contents($credentialsPath, json_encode([
    'access_token' => $tokens->accessToken,
    'refresh_token' => $tokens->refreshToken,
]));
chmod($credentialsPath, 0600);
```

A CLI agent uses `HttpBackchannelAuthentication::make($issuer, $client)` the same
way, with a confidential client, for example
`ClientAuthentication::privateKeyJwt($clientId, $pem, kid: 'key-1', algorithm: 'ES256')`.

## Errors

Every failure is a `Cbox\Id\Client\Exceptions\GrantFailed` with the OAuth `error`,
the `description` and the HTTP `status`. Catch the subclasses you handle
differently:

| Cbox ID answers | Exception | What to do |
|---|---|---|
| `authorization_pending` | none. `poll()` returns a pending `PollResult` | Poll again after `interval` |
| `slow_down` while polling (400 or 429) | none. The interval goes up by 5 seconds, or to `Retry-After` if that is longer | Handled for you |
| 429 `slow_down` when starting | `GrantRateLimited` (`$retryAfter`) | Start no sooner than `retryAfter` seconds |
| `access_denied` | `AuthorizationDenied` | The person said no. Don't retry unprompted |
| `expired_token`, or the request expired before the next poll | `AuthorizationExpired` | Start a new request |
| `invalid_grant` | `InvalidGrant` | Unknown, already used, or another client's `auth_req_id` / `device_code` |
| `invalid_target` | `InvalidTarget` | The `resource` is missing or not allowed for this client, or a public client asked for CIBA on a resource that doesn't allow it |
| `invalid_client` | `InvalidClient` | Wrong id, secret or key |
| `unknown_user_id` | `UnknownUser` | The `login_hint` matches nobody Cbox ID can ask |
| `invalid_binding_message` | `InvalidBindingMessage` | Missing when required, or longer than 255 characters (the length is checked before sending) |
| `waitForToken()` hits `maxWait` | `GrantTimedOut` | The request may still be open. Poll later or let it expire |
| anything else (`invalid_scope`, `unauthorized_client`, a malformed answer, no connection) | `GrantFailed` | Check `$e->error` |

The returned `TokenResult` holds `accessToken`, `tokenType`, `expiresIn`, `scope`
(and `scopes()`), `refreshToken` and `idToken`. The SDK doesn't verify the `idToken`
here, so verify it before you read claims from it. Treat the access token as a
credential: don't log it and don't send it to a browser.

## Testing

`CboxId::fake()` swaps both contracts for scripted fakes. They send no HTTP and
don't wait:

```php
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\ValueObjects\TokenResult;

$ciba = CboxId::fake()->backchannelAuthentication();
$ciba->pending(2)->approve(new TokenResult('at_test', 'Bearer', 900, 'mcp:read mcp:write'));
// or ->deny(), ->expire(), ->failPoll($exception), ->throttle(retryAfter: 30)

AskToSaveNotes::dispatchSync($task);
WaitForApproval::dispatchSync($task);

$ciba->assertStarted(
    loginHint: $task->user->cbox_id,
    resource: 'https://app.cboxbrain.com/mcp',
    bindingMessage: 'Let the agent save meeting notes to Cbox Brain',
);
$ciba->assertPolled(3);
```

`CboxId::fake()->deviceAuthorization()` works the same way. Its `start()` returns
the user code `FakeDeviceAuthorization::USER_CODE`, and it has
`assertStarted(resource:, scope:)`. If nothing is scripted, a poll stays pending and
`waitForToken()` throws `GrantTimedOut`, so the fake never grants anything you didn't
script.

To test the HTTP layer itself, use the real classes with `Http::fake()` and swap the
timer so waits finish at once:

```php
use Cbox\Id\Client\Grants\Timer;
use Cbox\Id\Client\Testing\FakeTimer;

$timer = new FakeTimer;
$this->app->instance(Timer::class, $timer);

Http::fake([
    'id.example.com/.well-known/*' => Http::response(['issuer' => 'https://id.example.com']),
    'id.example.com/oauth/token' => Http::sequence()
        ->push(['error' => 'authorization_pending'], 400)
        ->push(['access_token' => 'at', 'token_type' => 'Bearer']),
]);

// … run your code …

expect($timer->sleeps)->toBe([5, 5]);
```
