<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Facades;

use Cbox\Id\Client\IdentityClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\RedirectResponse redirect(list<string>|null $scopes = null, ?string $state = null, ?string $prompt = null, ?int $maxAge = null, ?string $loginHint = null)
 * @method static \Illuminate\Http\RedirectResponse addAccount(list<string>|null $scopes = null, ?string $state = null)
 * @method static \Cbox\Id\Client\ValueObjects\CboxUser authenticate(\Illuminate\Http\Request $request)
 * @method static string profileUrl(?string $returnTo = null)
 * @method static \Illuminate\Http\RedirectResponse redirectToProfile(?string $returnTo = null)
 * @method static string|null logoutUrl(?string $returnTo = null)
 * @method static string machineToken(list<string> $scopes = [], ?string $resource = null)
 * @method static array<string, mixed> userinfo(string $accessToken)
 * @method static array<string, mixed> introspect(string $token)
 * @method static bool verifyWebhook(string $payload, ?string $signatureHeader, string $secret, int $toleranceSeconds = 300)
 *
 * @see IdentityClient
 */
class CboxId extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IdentityClient::class;
    }
}
