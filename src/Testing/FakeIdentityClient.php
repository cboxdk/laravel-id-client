<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\RefreshedTokens;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;

/**
 * The real client with the network taken out.
 *
 * `redirect()`, `switchOrganization()`, `logoutUrl()` and friends are the production
 * code, running against {@see FakeDiscovery}, so the URLs a test sees are the URLs your
 * users would. The calls that would reach Cbox ID's back channel answer from memory:
 *
 * - `authenticate()` returns the next sign-in queued with `CboxId::fake()->signIn(…)`
 *   (or throws the queued failure), and remembers it in the session exactly as the real
 *   callback does — so your callback route is tested end to end without a fake issuer.
 * - `refresh()`, `machineToken()` mint real signed tokens; `revoke()` is recorded.
 */
class FakeIdentityClient extends IdentityClient
{
    /** @var list<CboxUser|AuthenticationFailed> */
    private array $signIns = [];

    /** @var list<string> */
    private array $revoked = [];

    /** @var list<string> */
    private array $refreshed = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        array $config,
        FakeDiscovery $discovery,
        SessionIdentityStore $sessions,
        private readonly TokenFactory $tokens,
    ) {
        parent::__construct($config, $discovery, $sessions);
    }

    public function queue(CboxUser|AuthenticationFailed $outcome): void
    {
        $this->signIns[] = $outcome;
    }

    public function authenticate(Request $request): CboxUser
    {
        $outcome = array_shift($this->signIns)
            ?? throw AuthenticationFailed::because('CboxId::fake(): no sign-in is queued. Call CboxId::fake()->signIn(…) first.');

        if ($outcome instanceof AuthenticationFailed) {
            throw $outcome;
        }

        if (config('cbox-id-client.session.remember', true) !== false) {
            $this->rememberIdentity($outcome);
        }

        return $outcome;
    }

    public function refresh(string $refreshToken, bool $remember = false): RefreshedTokens
    {
        $this->refreshed[] = $refreshToken;

        return new RefreshedTokens($this->tokens->mint(), 'rt_fake_'.bin2hex(random_bytes(6)), null, 300);
    }

    public function machineToken(array $scopes = [], ?string $resource = null): string
    {
        $token = $this->tokens->withoutOrganization()->scopes($scopes);

        return ($resource !== null ? $token->audience($resource) : $token)->mint();
    }

    public function revoke(string $token, ?string $tokenTypeHint = null): void
    {
        $this->revoked[] = $token;
    }

    public function assertRevoked(string $token): void
    {
        Assert::assertContains($token, $this->revoked, 'The token was never revoked.');
    }

    public function assertRefreshed(string $refreshToken): void
    {
        Assert::assertContains($refreshToken, $this->refreshed, 'That refresh token was never exchanged.');
    }
}
