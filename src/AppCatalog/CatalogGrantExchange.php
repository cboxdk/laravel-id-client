<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Support\HttpTimeouts;
use Cbox\Id\Client\ValueObjects\DpopKey;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Throwable;

/**
 * Trade the person's own access token for a catalog grant (RFC 8693 token exchange),
 * without a browser redirect: `account:apps:read` on Cbox ID's `/mcp`, bound to a fresh
 * DPoP key.
 *
 * The answer is verified before it is kept: signed by the issuer, for exactly this
 * person, organization and client, no actor, exactly the one scope, the audience is the
 * resource, and bound to our key. Anything else is refused.
 */
final readonly class CatalogGrantExchange
{
    private const TOKEN_TYPE = 'urn:ietf:params:oauth:token-type:access_token';

    public function __construct(
        private Factory $http,
        private Discovery $discovery,
        private string $issuer,
        private string $clientId,
        private string $clientSecret,
    ) {}

    /**
     * @throws SignInRequired when Cbox ID refused, or the grant does not match
     * @throws CatalogUnavailable when Cbox ID could not answer
     */
    public function exchange(#[\SensitiveParameter] string $subjectToken, AppCatalogContext $context): ResourceAuthorizationResult
    {
        if ($this->clientId === '' || $this->clientSecret === '' || parse_url($this->issuer, PHP_URL_SCHEME) !== 'https') {
            throw SignInRequired::because('The app catalog needs an HTTPS issuer and a confidential client.');
        }

        $resource = rtrim($this->issuer, '/').'/mcp';

        try {
            $endpoint = $this->endpoint('token_endpoint');
            $holder = DpopKey::generate();
            $response = $this->http->asForm()->withoutRedirecting()
                ->connectTimeout(HttpTimeouts::connect())->timeout(5)
                ->withHeaders(['DPoP' => $holder->proof('POST', $endpoint)])
                ->post($endpoint, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
                    'subject_token_type' => self::TOKEN_TYPE, 'requested_token_type' => self::TOKEN_TYPE,
                    'subject_token' => $subjectToken, 'scope' => AppCatalogStore::SCOPE, 'resource' => $resource,
                    'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
                ]);
        } catch (ConnectionException) {
            throw CatalogUnavailable::because('Cbox ID could not be reached for the catalog grant.');
        } catch (SignInRequired $e) {
            throw $e;
        } catch (Throwable) {
            // Transport errors can carry the request, and the request carries credentials.
            throw CatalogUnavailable::because('The catalog grant exchange failed.');
        }

        if ($response->serverError() || $response->status() === 429) {
            throw CatalogUnavailable::because('Cbox ID could not issue a catalog grant right now.');
        }

        $token = $response->json('access_token');
        $type = $response->json('token_type');

        if (! $response->successful() || ! is_string($token) || ! is_string($type)
            || strcasecmp($type, 'DPoP') !== 0 || $response->json('issued_token_type') !== self::TOKEN_TYPE) {
            throw SignInRequired::because('Cbox ID refused the catalog grant exchange.');
        }

        try {
            $this->endpoint('jwks_uri');
            $verified = (new AccessTokenVerifier($this->discovery, $this->issuer, $resource))
                ->verifyForHolder($token, $holder, [AppCatalogStore::SCOPE]);
        } catch (Throwable) {
            throw SignInRequired::because('The catalog grant did not verify.');
        }

        if ($verified->subject !== $context->subject || $verified->organizationId !== $context->organizationId
            || $verified->clientId !== $this->clientId || $verified->subject === $this->clientId
            || array_key_exists('act', $verified->claims) || ($verified->claims['aud'] ?? null) !== $resource
            || $verified->scopes !== [AppCatalogStore::SCOPE] || $verified->expiresAt <= time()) {
            throw SignInRequired::because('The catalog grant is not bound to this person and organization.');
        }

        return new ResourceAuthorizationResult(
            $token, $verified->subject, $context->organizationId, $resource,
            $verified->scopes, $verified->expiresAt, [], $holder,
        );
    }

    private function endpoint(string $name): string
    {
        $endpoint = $this->discovery->endpoint($name);

        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || parse_url($endpoint, PHP_URL_USER) !== null
            || parse_url($endpoint, PHP_URL_FRAGMENT) !== null) {
            throw SignInRequired::because('The app catalog requires HTTPS Cbox ID endpoints.');
        }

        return $endpoint;
    }
}
