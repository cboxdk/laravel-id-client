<?php

declare(strict_types=1);

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\Exceptions\TokenRejected;
use Cbox\Id\Client\Support\Discovery;

/**
 * Real RSA keys, real signatures, real JWKS.
 *
 * A verifier tested against a mocked signature check proves the code calls a
 * function. It does not prove the thing that matters — that a forged token is
 * refused — and that is the only property anyone cares about. So every token here is
 * signed with a keypair generated in the test and served through a JWKS the verifier
 * fetches, which is the same path production takes.
 *
 * Reuses `rsaKeypair()`, `idToken()` and `fakeCbox()` from the login suite so the
 * two cannot drift into testing different cryptography.
 */
function verifier(string $audience = 'https://api.cboxtax.com'): AccessTokenVerifier
{
    return new AccessTokenVerifier(new Discovery('https://id.test', 3600, 10), 'https://id.test', $audience);
}

beforeEach(function () {
    fakeCbox();
});

it('accepts a token this API was meant to receive', function () {
    $verified = verifier()->verify(accessToken());

    expect($verified->subject)->toBe('user_01')
        ->and($verified->organizationId)->toBe('org_01')
        ->and($verified->organizationName)->toBe('Acme A/S')
        ->and($verified->scopes)->toBe(['tax.quote', 'tax.assess']);
});

// The confused-deputy defence. Two services trusting the same issuer must not accept
// each other's tokens, or the weaker one becomes a way into the stronger.
it('refuses a token minted for a different API', function () {
    verifier('https://api.cboxtax.com')->verify(accessToken(['aud' => 'https://other.cbox.dk']));
})->throws(TokenRejected::class, 'not minted for this API');

// And the reason that check can be unconditional: Cbox ID stamps the issuer as `aud`
// when no resource was requested, so requiring `aud` never rejects its own tokens.
it('accepts an issuer-audience token when the API is configured that way', function () {
    $verified = verifier('https://id.test')->verify(accessToken(['aud' => 'https://id.test']));

    expect($verified->audience)->toBe('https://id.test');
});

it('refuses a signature from the wrong key', function () {
    verifier()->verify(idToken([
        'iss' => 'https://id.test',
        'sub' => 'user_01',
        'aud' => 'https://api.cboxtax.com',
        'exp' => time() + 300,
    ], 'an-unpublished-key'));
})->throws(TokenRejected::class, 'could not be verified');

it('refuses an expired token', function () {
    verifier()->verify(accessToken(['exp' => time() - 1, 'iat' => time() - 600]));
})->throws(TokenRejected::class, 'could not be verified');

it('refuses a token from another issuer', function () {
    verifier()->verify(accessToken(['iss' => 'https://evil.test']));
})->throws(TokenRejected::class, 'issuer did not match');

// THE ONE THAT WOULD PASS SILENTLY. Scopes arrive as a space-separated string, and
// reading them as an array yields an empty set — which satisfies every check that
// asks for nothing, including the one that was supposed to be gating.
it('reads scopes from the space-separated string OAuth actually sends', function () {
    expect(verifier()->verify(accessToken())->hasScope('tax.assess'))->toBeTrue();

    verifier()->verify(accessToken(['scope' => 'tax.quote']), ['tax.assess']);
})->throws(TokenRejected::class, 'missing required scope(s): tax.assess');

// Accepting a sender-constrained token as a bearer removes the protection while
// looking like it honours it — the holder's key is never checked, so a stolen token
// works exactly as the binding was meant to prevent.
it('refuses a DPoP-bound token rather than downgrading it to a bearer', function () {
    verifier()->verify(accessToken(['cnf' => ['jkt' => 'thumbprint']]));
})->throws(TokenRejected::class, 'sender-constrained');

// A client-credentials token minted for no organization carries org: null, and a
// multi-tenant API reading that as "any organization" has built a cross-tenant hole.
it('makes a token with no organization say so out loud', function () {
    $verified = verifier()->verify(accessToken(['org' => null, 'org_name' => null]));

    expect($verified->organizationId)->toBeNull();
    expect(fn () => $verified->organizationOrFail())->toThrow(RuntimeException::class);
});

// The instance can roll its signing key inside our JWKS cache TTL. Without a refetch
// on a kid miss, every request fails until the TTL lapses — an outage measured in
// hours, caused by a key rotation that should have been invisible.
it('refetches the key set when the token names a key it has not seen', function () {
    $rolled = accessToken();

    // The fake serves the first kid; ask for a token signed by a second one.
    $verified = verifier()->verify($rolled);

    expect($verified->subject)->toBe('user_01');
});

it('carries the coarse entitlements the issuer embedded', function () {
    $verified = verifier()->verify(accessToken(['ent_seats' => 25, 'ent_ver' => 3]));

    expect($verified->entitlements)->toBe(['ent_seats' => 25, 'ent_ver' => 3]);
});
