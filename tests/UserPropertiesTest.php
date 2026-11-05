<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\UserPropertiesFailed;
use Cbox\Id\Client\Testing\FakeUserProperties;
use Cbox\Id\Client\UserProperties\HttpUserProperties;
use Cbox\Id\Client\UserProperties\PropertyDocument;
use Cbox\Id\Client\UserProperties\PropertyPatch;
use Cbox\Id\Client\UserProperties\PropertyToken;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('preserves JSON objects arrays null booleans and the document version', function (): void {
    Http::fake(['https://id.example/oauth/users/u%2F1/properties/planning' => Http::response('{"user_id":"u/1","namespace":"planning","version":7,"data":{"object":{},"list":[],"nothing":null,"enabled":true}}')]);
    $client = new HttpUserProperties(app(Factory::class), 'https://id.example');
    $document = $client->read('u/1', 'planning', new PropertyToken('test-token'));
    expect($document->data->object)->toBeInstanceOf(stdClass::class)
        ->and($document->data->list)->toBe([])->and($document->data->nothing)->toBeNull()
        ->and($document->data->enabled)->toBeTrue()->and($document->version)->toBe(7);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('sends explicit removals and an empty object without overwriting other properties', function (): void {
    Http::fake(['*' => Http::response(['user_id' => 'user', 'namespace' => 'preferences', 'version' => 5, 'data' => (object) ['language' => 'da']])]);
    $client = new HttpUserProperties(app(Factory::class), 'https://id.example');
    $client->patch('user', 'preferences', new PropertyPatch(4, remove: ['theme']), new PropertyToken('test-token'));
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body());

        return $request->method() === 'PATCH' && $body->version === 4 && $body->set instanceof stdClass && $body->remove === ['theme'];
    });
});

it('reports conflicts and revocation without retrying or leaking response text', function (int $status): void {
    Http::fake(['*' => Http::response(['error' => 'secret response must stay private'], $status)]);
    $client = new HttpUserProperties(app(Factory::class), 'https://id.example');
    try {
        $client->read('user', 'preferences', new PropertyToken('private-token'));
        test()->fail('Expected refusal');
    } catch (UserPropertiesFailed $failure) {
        expect($failure->status)->toBe($status)->and($failure->getMessage())->not->toContain('secret', 'private-token');
    }
    Http::assertSentCount(1);
})->with([401, 403, 409, 429, 503]);

it('rejects another subjects response instead of caching it', function (): void {
    Http::fake(['*' => Http::response(['user_id' => 'other', 'namespace' => 'preferences', 'version' => 1, 'data' => new stdClass])]);
    expect(fn () => (new HttpUserProperties(app(Factory::class), 'https://id.example'))->read('user', 'preferences', new PropertyToken('token')))
        ->toThrow(UserPropertiesFailed::class);
});

it('uses the supplied DPoP signer with the exact method URL and token', function (): void {
    Http::fake(['*' => Http::response(['user_id' => 'user', 'namespace' => 'preferences', 'version' => 0, 'data' => new stdClass])]);
    $token = new PropertyToken('bound-token', function (string $method, string $url, string $access): string {
        expect([$method, $url, $access])->toBe(['GET', 'https://id.example/oauth/users/user/properties/preferences', 'bound-token']);

        return 'test-proof';
    });
    (new HttpUserProperties(app(Factory::class), 'https://id.example'))->read('user', 'preferences', $token);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'DPoP bound-token') && $request->hasHeader('DPoP', 'test-proof'));
});

it('requires explicit fake responses and never implicitly succeeds', function (): void {
    $fake = new FakeUserProperties([new PropertyDocument('user', 'preferences')]);
    expect($fake->read('user', 'preferences', new PropertyToken('test'))->version)->toBe(0);
    expect(fn () => $fake->read('user', 'preferences', new PropertyToken('test')))->toThrow(LogicException::class);
});
