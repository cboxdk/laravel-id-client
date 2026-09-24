<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Facades;

use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Testing\CboxIdFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\RedirectResponse redirect(list<string>|null $scopes = null, ?string $state = null, string|\Cbox\Id\Client\Enums\Prompt|null $prompt = null, ?int $maxAge = null, ?string $loginHint = null, ?string $organization = null, ?string $organizationHint = null)
 * @method static \Illuminate\Http\RedirectResponse switchOrganization(string $organizationId, list<string>|null $scopes = null, string|\Cbox\Id\Client\Enums\Prompt|null $prompt = null)
 * @method static \Illuminate\Http\RedirectResponse selectOrganization(list<string>|null $scopes = null, ?string $organizationHint = null)
 * @method static \Illuminate\Http\RedirectResponse createOrganization(list<string>|null $scopes = null)
 * @method static \Illuminate\Http\RedirectResponse addAccount(list<string>|null $scopes = null, ?string $state = null)
 * @method static \Cbox\Id\Client\ValueObjects\CboxUser authenticate(\Illuminate\Http\Request $request)
 * @method static \Cbox\Id\Client\ValueObjects\RefreshedTokens refresh(string $refreshToken, bool $remember = false)
 * @method static \Cbox\Id\Client\Contracts\Principal|null principal()
 * @method static \Cbox\Id\Client\ValueObjects\Organization|null currentOrganization()
 * @method static void rememberIdentity(\Cbox\Id\Client\Contracts\Principal $principal)
 * @method static void forgetIdentity()
 * @method static \Cbox\Id\Client\ValueObjects\VerifiedApiKey verifyApiKey(string $key, list<string> $requiredPermissions = [])
 * @method static string profileUrl(?string $returnTo = null)
 * @method static string apiKeysUrl(?string $clientId = null, ?string $returnTo = null, ?string $organization = null)
 * @method static \Illuminate\Http\RedirectResponse redirectToApiKeys(?string $clientId = null, ?string $returnTo = null, ?string $organization = null)
 * @method static \Illuminate\Http\RedirectResponse redirectToProfile(?string $returnTo = null)
 * @method static string|null logoutUrl(?string $returnTo = null, ?string $idTokenHint = null)
 * @method static string machineToken(list<string> $scopes = [], ?string $resource = null)
 * @method static array<string, mixed> userinfo(string $accessToken)
 * @method static array<string, mixed> introspect(string $token)
 * @method static void revoke(string $token, ?string $tokenTypeHint = null)
 * @method static bool verifyWebhook(string $payload, ?string $signatureHeader, string $secret, int $toleranceSeconds = 300)
 *
 * @see IdentityClient
 */
class CboxId extends Facade
{
    /**
     * Replace Cbox ID with in-memory fakes for the rest of this test. See {@see CboxIdFake}.
     */
    public static function fake(): CboxIdFake
    {
        return new CboxIdFake(static::getFacadeApplication() ?? app());
    }

    protected static function getFacadeAccessor(): string
    {
        return IdentityClient::class;
    }
}
