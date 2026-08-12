<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\FrontendApiUnavailable;
use Cbox\Id\Client\Frontend\FrontendClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The browser-facing channel, read from PHP so a Blade page can draw a sign-in box in the
 * customer's own branding without a JavaScript round trip — and without a flash of
 * unstyled form while one arrives.
 */
function frontend(string $key = 'pk_test_abc'): FrontendClient
{
    return new FrontendClient('https://id.acme.test', $key, cacheTtl: 60, timeout: 5);
}

const CONFIG_DOCUMENT = [
    'mode' => 'live',
    'issuer' => 'https://id.acme.test',
    'endpoints' => ['authorization' => 'https://id.acme.test/oauth/authorize'],
    'social' => [['provider' => 'google', 'name' => 'Google']],
    'appearance' => ['light' => ['primary' => '#0ea5e9']],
];

beforeEach(function (): void {
    Cache::flush();
});

/**
 * A CLIENT SECRET WHERE A PUBLIC KEY BELONGS is the exact mistake this channel exists to
 * make unnecessary, and as an opaque 401 in a log the cause is invisible. Refused at
 * construction, where somebody is looking.
 */
it('refuses anything that is not a publishable key', function (string $value): void {
    expect(fn (): FrontendClient => frontend($value))->toThrow(ClientConfigurationException::class);
})->with([
    'a client secret' => ['cbox_sk_live_abcdefghijklmnop'],
    'an api key' => ['sk_live_abcdefghijklmnop'],
    'empty' => [''],
]);

it('reads the document a browser would draw a sign-in box from', function (): void {
    Http::fake(['*/frontend/v1/config' => Http::response(CONFIG_DOCUMENT, 200)]);

    $config = frontend()->config();

    expect($config->issuer)->toBe('https://id.acme.test')
        ->and($config->isLive())->toBeTrue()
        ->and($config->endpoint('authorization'))->toBe('https://id.acme.test/oauth/authorize')
        ->and($config->social)->toHaveCount(1)
        ->and($config->social[0]->name)->toBe('Google')
        // The accent is reached through a method rather than left to the template: the
        // theme is a nested document, and a Blade file digging into it has no way to be
        // wrong out loud.
        ->and($config->accent())->toBe('#0ea5e9');
});

/**
 * A HEADER, NEVER A QUERY STRING. A query string puts the key in server logs, in `Referer`
 * on every outbound link, and in browser history.
 */
it('presents the key in a header and nowhere else', function (): void {
    Http::fake(['*' => Http::response(CONFIG_DOCUMENT, 200)]);

    frontend()->config();

    Http::assertSent(fn ($request): bool => $request->header('X-Cbox-Publishable-Key') === ['pk_test_abc']
        && ! str_contains($request->url(), 'pk_test_abc'));
});

/**
 * One application may legitimately read two environments. A cache keyed on the issuer
 * alone would serve one customer's branding on the other customer's page.
 */
it('does not serve one key\'s configuration under another key', function (): void {
    Http::fake([
        '*' => Http::sequence()
            ->push(CONFIG_DOCUMENT, 200)
            ->push([...CONFIG_DOCUMENT, 'mode' => 'test', 'social' => []], 200),
    ]);

    $first = frontend('pk_live_one')->config();
    $second = frontend('pk_test_two')->config();

    expect($first->isLive())->toBeTrue()
        ->and($second->isLive())->toBeFalse();
});

it('asks once for a document that decides layout', function (): void {
    Http::fake(['*' => Http::response(CONFIG_DOCUMENT, 200)]);

    $client = frontend();
    $client->config();
    $client->config();

    Http::assertSentCount(1);
});

/**
 * Signed out is a STATE, not an error — a page that renders an avatar on every request
 * should not treat "nobody is signed in" as a failure — and an empty token is answered
 * without a network call at all.
 */
it('answers an empty token without asking anybody', function (): void {
    Http::fake();

    expect(frontend()->session(null)->signedIn())->toBeFalse();

    Http::assertNothingSent();
});

it('names the person a live token belongs to', function (): void {
    Http::fake(['*/frontend/v1/session' => Http::response([
        'user' => ['id' => 'sub_1', 'email' => 'ada@acme.test', 'name' => 'Ada Lovelace'],
    ], 200)]);

    $session = frontend()->session('an-access-token');

    expect($session->signedIn())->toBeTrue()
        ->and($session->user?->email)->toBe('ada@acme.test')
        ->and($session->user?->initials())->toBe('AL');

    // The TOKEN is the authority here; the key only says a browser is asking.
    Http::assertSent(fn ($request): bool => $request->header('Authorization') === ['Bearer an-access-token']);
});

it('reads a signed-out session as nobody rather than as a failure', function (): void {
    Http::fake(['*' => Http::response(['user' => null], 200)]);

    expect(frontend()->session('a-stale-token')->signedIn())->toBeFalse();
});

/**
 * The server answers precisely — "No publishable key was presented" is a wiring mistake and
 * "cannot be used from this origin" is an allow-list — and substituting one guess for both
 * would make this client less useful than the API it wraps.
 */
it('keeps the reason the server gave', function (): void {
    Http::fake(['*' => Http::response([
        'error' => 'unauthorized',
        'error_description' => 'That publishable key cannot be used from this origin.',
    ], 401)]);

    expect(fn () => frontend()->config())
        ->toThrow(FrontendApiUnavailable::class, 'That publishable key cannot be used from this origin.');
});

it('says what to check when the server gave no reason', function (): void {
    Http::fake(['*' => Http::response('', 403)]);

    expect(fn () => frontend()->config())->toThrow(FrontendApiUnavailable::class, 'allow-list');
});

it('does not cache a refusal as though it were a document', function (): void {
    Http::fake([
        '*' => Http::sequence()
            ->push('', 503)
            ->push(CONFIG_DOCUMENT, 200),
    ]);

    $client = frontend();

    expect(fn () => $client->config())->toThrow(FrontendApiUnavailable::class);

    // A blip must not decide what this page looks like for the whole cache window.
    expect($client->config()->isLive())->toBeTrue();
});
