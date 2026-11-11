<?php

declare(strict_types=1);

use Cbox\Id\Client\AppCatalog\CatalogPayload;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

/*
 * The catalog's newer optional fields (shell spec §9.3): `purpose`, `hue`, and apps the
 * organization does not have yet (`enabled: false` with an `admission_url`).
 */

function catalogAnswer(array $apps): Response
{
    return new Response(new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode([
        'data' => $apps, 'meta' => ['revision' => str_repeat('a', 64), 'refresh_after' => 60],
    ], JSON_THROW_ON_ERROR)));
}

it('passes purpose, hue and enabled through', function (): void {
    $catalog = CatalogPayload::parse(catalogAnswer([
        ['key' => 'id', 'name' => 'Cbox ID', 'launch_url' => 'https://app.cboxid.com/', 'purpose' => 'Identity and sign-in', 'hue' => '#4f46e5', 'enabled' => true],
        ['key' => 'work', 'name' => 'Cbox Work', 'launch_url' => 'https://app.cboxwork.com/', 'hue' => 152],
    ]), 'https://id.test');

    expect($catalog['apps'][0])->toBe([
        'key' => 'id', 'name' => 'Cbox ID', 'description' => null, 'launch_url' => 'https://app.cboxid.com/',
        'icon_url' => null, 'icon_dark_url' => null, 'purpose' => 'Identity and sign-in', 'hue' => '#4f46e5',
        'admission_url' => null, 'enabled' => true,
    ])->and($catalog['apps'][1]['hue'])->toBe('152')
        ->and($catalog['apps'][1]['enabled'])->toBeTrue()
        ->and($catalog['apps'][1]['purpose'])->toBeNull();
});

it('exposes apps the organization does not have yet, with where to sign up', function (): void {
    $catalog = CatalogPayload::parse(catalogAnswer([
        ['key' => 'billing', 'name' => 'Cbox Billing', 'enabled' => false, 'admission_url' => 'https://app.cboxbilling.com/signup?organization=org_1', 'purpose' => 'Plans and invoices'],
    ]), 'https://id.test');

    expect($catalog['apps'][0])->toMatchArray([
        'key' => 'billing', 'launch_url' => null, 'enabled' => false,
        'admission_url' => 'https://app.cboxbilling.com/signup?organization=org_1',
    ]);
});

it('tolerates an older Cbox ID that sends none of the new fields', function (): void {
    $catalog = CatalogPayload::parse(catalogAnswer([
        ['key' => 'work', 'name' => 'Work', 'description' => 'Time', 'launch_url' => 'https://work.test/'],
    ]), 'https://id.test');

    expect($catalog['apps'][0])->toMatchArray(['purpose' => null, 'hue' => null, 'admission_url' => null, 'enabled' => true]);
});

it('drops a hue it cannot prove safe instead of passing it to a page', function (mixed $hue): void {
    $catalog = CatalogPayload::parse(catalogAnswer([
        ['key' => 'work', 'name' => 'Work', 'launch_url' => 'https://work.test/', 'hue' => $hue],
    ]), 'https://id.test');

    expect($catalog['apps'][0]['hue'])->toBeNull();
})->with([
    'css injection' => ['red;background:url(x)'],
    'css function' => ['rgb(1,2,3)'],
    'uppercase name' => ['Indigo'],
    'too long hex' => ['#123456789'],
    'array' => [['h' => 1]],
]);

it('refuses an entry without the URL it opens', function (array $app): void {
    CatalogPayload::parse(catalogAnswer([['key' => 'billing', 'name' => 'Billing', ...$app]]), 'https://id.test');
})->with([
    'enabled without launch_url' => [['enabled' => true]],
    'not enabled without admission_url' => [['enabled' => false, 'launch_url' => 'https://billing.test/']],
    'plain-http admission_url' => [['enabled' => false, 'admission_url' => 'http://billing.test/signup']],
    'enabled is not a boolean' => [['enabled' => 'yes', 'launch_url' => 'https://billing.test/']],
])->throws(RuntimeException::class, 'invalid app catalog entry');

it('treats a catalog cached by an older SDK as missing', function (): void {
    expect(CatalogPayload::isCurrent(['apps' => [['key' => 'work', 'launch_url' => 'https://work.test/']]]))->toBeFalse()
        ->and(CatalogPayload::isCurrent(['apps' => [['key' => 'work', 'enabled' => true, 'admission_url' => null]]]))->toBeTrue()
        ->and(CatalogPayload::isCurrent(['apps' => []]))->toBeTrue()
        ->and(CatalogPayload::isCurrent('nope'))->toBeFalse();
});
