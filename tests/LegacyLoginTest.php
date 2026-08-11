<?php

declare(strict_types=1);

use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Migration\LegacyLogin;
use Cbox\Id\Client\Migration\LegacyUser;
use Illuminate\Http\Request;

const LEGACY_SECRET = 'a-secret-long-enough-to-mean-something-here';

function signedRequest(string $body, ?string $secret = LEGACY_SECRET, ?int $timestamp = null): Request
{
    $timestamp ??= time();
    $signature = $secret === null
        ? null
        : 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return Request::create('/cbox-legacy', 'POST', server: array_filter([
        'HTTP_X_CBOX_SIGNATURE' => $signature,
    ]), content: $body);
}

function handle(Request $request, ?Closure $verify = null): array
{
    $handler = LegacyLogin::using($verify ?? fn (): ?LegacyUser => new LegacyUser('ada@acme.test', 'Ada'), LEGACY_SECRET);
    $response = $handler($request, app(IdentityClient::class));

    return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
}

/**
 * An endpoint that exists and cannot verify anything answers 500 to Cbox ID and looks like
 * an outage. Refusing to build it is a stack trace in the deploy, where somebody is
 * already looking.
 */
it('refuses to build without a real secret', function (): void {
    expect(fn () => LegacyLogin::using(fn () => null, 'too-short'))
        ->toThrow(InvalidArgumentException::class);
});

it('answers with the person when the credentials are good', function (): void {
    [$status, $body] = handle(signedRequest(json_encode(['email' => 'ada@acme.test', 'password' => 'pw'])));

    expect($status)->toBe(200)
        ->and($body['email'])->toBe('ada@acme.test');
});

/**
 * The check this factory exists to own — the part every adopter would otherwise write
 * themselves, and the part people get wrong.
 */
it('refuses unsigned, wrongly signed and stale requests without consulting the store', function (): void {
    $called = false;
    $verify = function () use (&$called): ?LegacyUser {
        $called = true;

        return null;
    };

    $body = json_encode(['email' => 'ada@acme.test', 'password' => 'pw']);

    expect(handle(signedRequest($body, null), $verify)[0])->toBe(401)
        ->and(handle(signedRequest($body, 'a-different-secret-that-is-long-enough!!'), $verify)[0])->toBe(401)
        // Outside the freshness window: a captured request must stop working.
        ->and(handle(signedRequest($body, LEGACY_SECRET, time() - 400), $verify)[0])->toBe(401)
        ->and($called)->toBeFalse();
});

/**
 * The signature covers the RAW bytes. Re-encoding a parsed payload produces different ones
 * and would refuse every valid request — a failure that looks like a wrong secret.
 */
it('verifies the exact bytes it was sent', function (): void {
    $body = '{"email":"ada@acme.test",  "password":"pw"}';

    expect(handle(signedRequest($body))[0])->toBe(200);
});

it('says no plainly when the password is wrong', function (): void {
    [$status, $body] = handle(
        signedRequest(json_encode(['email' => 'ada@acme.test', 'password' => 'wrong'])),
        fn (): ?LegacyUser => null,
    );

    expect($status)->toBe(200)->and($body)->toBe(['user' => null]);
});

/**
 * A throw means the store could not DECIDE, which is not a wrong password — and Cbox ID
 * needs to tell the two apart in its own logs.
 */
it('answers 503 when the store throws, never 401', function (): void {
    [$status] = handle(
        signedRequest(json_encode(['email' => 'ada@acme.test', 'password' => 'pw'])),
        fn (): ?LegacyUser => throw new RuntimeException('database is down'),
    );

    expect($status)->toBe(503);
});

it('answers a password-less probe without consulting the store', function (): void {
    $called = false;

    [$status, $body] = handle(
        signedRequest(json_encode(['email' => 'ada@acme.test'])),
        function () use (&$called): ?LegacyUser {
            $called = true;

            return null;
        },
    );

    expect($status)->toBe(200)->and($body)->toBe(['user' => null])->and($called)->toBeFalse();
});

it('passes a stored hash through when the store can expose one', function (): void {
    [, $body] = handle(
        signedRequest(json_encode(['email' => 'ada@acme.test', 'password' => 'pw'])),
        fn (): ?LegacyUser => new LegacyUser('ada@acme.test', 'Ada', true, '$2y$10$abcdefghijklmnopqrstuv'),
    );

    expect($body['password_hash'])->toBe('$2y$10$abcdefghijklmnopqrstuv')
        ->and($body['email_verified'])->toBeTrue();
});
