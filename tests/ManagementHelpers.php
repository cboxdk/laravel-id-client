<?php

declare(strict_types=1);

use Cbox\Id\Client\Management\EnvironmentClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
 * Test doubles for the typed management clients: a fake server that answers requests in
 * the order given and remembers every request it saw — connection failures included,
 * which Laravel's own recorder does not keep.
 */

/**
 * @param  mixed  ...$responses  `Http::response(...)`, `Http::failedConnection()`, or a closure taking the request
 * @return ArrayObject<int, HttpRequest>
 */
function fakeManagement(mixed ...$responses): ArrayObject
{
    $queue = $responses;
    $seen = new ArrayObject;
    Sleep::fake();

    // A fresh factory, so a second fake in one test replaces the first instead of queueing behind it.
    Http::swap(new Factory);
    Http::fake(function (HttpRequest $request) use (&$queue, $seen) {
        $seen->append($request);
        $next = array_shift($queue);

        if ($next === null) {
            throw new RuntimeException('The fake management server has no answer left for '.$request->method().' '.$request->url());
        }

        return $next instanceof Closure ? $next($request) : $next;
    });

    return $seen;
}

/** An environment client against the fake server, with a key. */
function envClient(mixed ...$options): EnvironmentClient
{
    return new EnvironmentClient(...['baseUrl' => 'https://acme.test', 'apiKey' => 'cbid_env_test', ...$options]);
}

/** `{data: …}` with a status and headers. */
function envelope(mixed $data, int $status = 200, array $headers = [], array $meta = []): mixed
{
    return Http::response($meta === [] ? ['data' => $data] : ['data' => $data, 'meta' => $meta], $status, $headers);
}

/** A complete `App` as the environment plane answers it. */
function appData(array $overrides = []): array
{
    return [
        'id' => 'app_1',
        'client_id' => 'cid_1',
        'name' => 'Billing',
        'type' => 'web',
        'client_type' => 'confidential',
        'first_party' => false,
        'grant_types' => ['authorization_code', 'refresh_token'],
        'redirect_uris' => ['https://billing.acme.test/callback'],
        'scopes' => ['openid'],
        'client_secret' => 'csec_shown_once',
        ...$overrides,
    ];
}

/** A `202 approval_required`. */
function approvalRequired(string $id = 'apr_1', ?string $pollUrl = null, array $headers = []): mixed
{
    return Http::response([
        'error' => 'approval_required',
        'message' => 'This action needs approval.',
        'approval' => [
            'id' => $id,
            'status' => 'pending',
            'binding_code' => '42-17',
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
            'poll_url' => $pollUrl ?? "https://acme.test/api/v1/action-approvals/{$id}",
        ],
    ], 202, $headers);
}

function approvalStatus(string $status, string $id = 'apr_1'): mixed
{
    return Http::response(['data' => ['id' => $id, 'status' => $status]]);
}
