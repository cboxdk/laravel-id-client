<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Grants;

use Cbox\Id\Client\Enums\ClientAuthMethod;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;

/**
 * The credentials a client presents at Cbox ID's token, device and backchannel endpoints.
 *
 *     ClientAuthentication::clientSecretBasic('cid_…', $secret);
 *     ClientAuthentication::privateKeyJwt('cid_…', file_get_contents('key.pem'), kid: 'key-1');
 *     ClientAuthentication::none('cid_…');            // a public client
 *
 * Exactly one mechanism goes on each request (RFC 6749 §2.3): Basic never also puts the
 * `client_id` in the body, and an assertion never travels with a secret. A
 * `private_key_jwt` assertion (RFC 7523) is minted fresh for every request, because Cbox
 * ID accepts each `jti` once.
 */
readonly class ClientAuthentication
{
    /** Signing algorithms a client assertion may use. */
    public const array ALGORITHMS = ['RS256', 'ES256'];

    /** Lifetime of a client assertion, in seconds. */
    public const int ASSERTION_LIFETIME = 60;

    public const string ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    private function __construct(
        public ClientAuthMethod $method,
        public string $clientId,
        #[\SensitiveParameter] private string $secret = '',
        #[\SensitiveParameter] private string $privateKey = '',
        public ?string $keyId = null,
        public string $algorithm = 'RS256',
    ) {
        if (trim($clientId) === '') {
            throw NotConfigured::key('client_id', 'authenticate this client to Cbox ID');
        }
    }

    public static function none(string $clientId): self
    {
        return new self(ClientAuthMethod::None, $clientId);
    }

    public static function clientSecretBasic(string $clientId, #[\SensitiveParameter] string $secret): self
    {
        return new self(ClientAuthMethod::ClientSecretBasic, $clientId, self::requireSecret($secret));
    }

    public static function clientSecretPost(string $clientId, #[\SensitiveParameter] string $secret): self
    {
        return new self(ClientAuthMethod::ClientSecretPost, $clientId, self::requireSecret($secret));
    }

    /**
     * Sign a client assertion with a PEM private key: an RSA key for RS256, a P-256 EC key
     * for ES256. `$keyId` is the `kid` of the matching public key registered with Cbox ID.
     */
    public static function privateKeyJwt(
        string $clientId,
        #[\SensitiveParameter] string $privateKeyPem,
        ?string $keyId = null,
        string $algorithm = 'RS256',
    ): self {
        if (! in_array($algorithm, self::ALGORITHMS, true)) {
            throw ClientConfigurationException::because("Client assertions are signed with RS256 or ES256, not '{$algorithm}'.");
        }

        $key = openssl_pkey_get_private($privateKeyPem);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if ($details === false) {
            throw ClientConfigurationException::because('The client assertion key is not a readable PEM private key.');
        }

        $curve = is_array($details['ec'] ?? null) ? ($details['ec']['curve_name'] ?? null) : null;
        $matches = match ($algorithm) {
            'RS256' => $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048,
            default => $details['type'] === OPENSSL_KEYTYPE_EC && $curve === 'prime256v1',
        };

        if (! $matches) {
            throw ClientConfigurationException::because(
                $algorithm === 'RS256'
                    ? 'RS256 client assertions need an RSA private key of at least 2048 bits.'
                    : 'ES256 client assertions need a P-256 (prime256v1) EC private key.',
            );
        }

        return new self(ClientAuthMethod::PrivateKeyJwt, $clientId, '', $privateKeyPem, $keyId !== '' ? $keyId : null, $algorithm);
    }

    /**
     * Build from the `cbox-id-client` config: `client_id`, `client_secret` and the
     * `client_authentication` block. With no method configured, a client with a secret
     * uses `client_secret_basic`, one with a private key `private_key_jwt`, and one with
     * neither is public.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $clientId = is_string($config['client_id'] ?? null) ? $config['client_id'] : '';
        $secret = is_string($config['client_secret'] ?? null) ? $config['client_secret'] : '';
        $auth = is_array($config['client_authentication'] ?? null) ? $config['client_authentication'] : [];

        $key = is_string($auth['private_key'] ?? null) ? trim($auth['private_key']) : '';

        if ($key !== '' && ! str_starts_with($key, '-----')) {
            $contents = is_file($key) ? file_get_contents($key) : false;

            if ($contents === false) {
                throw ClientConfigurationException::because('The client assertion key file could not be read.');
            }

            $key = $contents;
        }

        $configured = is_string($auth['method'] ?? null) ? $auth['method'] : '';
        $method = $configured !== ''
            ? ClientAuthMethod::tryFrom($configured)
            : ($key !== '' ? ClientAuthMethod::PrivateKeyJwt : ($secret !== '' ? ClientAuthMethod::ClientSecretBasic : ClientAuthMethod::None));

        if ($method === null) {
            throw ClientConfigurationException::because("Unknown client authentication method '{$configured}'.");
        }

        $keyId = is_string($auth['key_id'] ?? null) ? $auth['key_id'] : null;
        $algorithm = is_string($auth['algorithm'] ?? null) && $auth['algorithm'] !== '' ? $auth['algorithm'] : 'RS256';

        return match ($method) {
            ClientAuthMethod::None => self::none($clientId),
            ClientAuthMethod::ClientSecretBasic => self::clientSecretBasic($clientId, $secret),
            ClientAuthMethod::ClientSecretPost => self::clientSecretPost($clientId, $secret),
            ClientAuthMethod::PrivateKeyJwt => $key === ''
                ? throw ClientConfigurationException::because('private_key_jwt needs `cbox-id-client.client_authentication.private_key` (CBOX_ID_PRIVATE_KEY).')
                : self::privateKeyJwt($clientId, $key, $keyId, $algorithm),
        };
    }

    /**
     * Put HTTP Basic credentials on the request when this client uses them. Sent as-is,
     * the way Cbox ID reads them and the rest of this SDK sends them.
     */
    public function authenticate(PendingRequest $request): PendingRequest
    {
        return $this->method === ClientAuthMethod::ClientSecretBasic
            ? $request->withBasicAuth($this->clientId, $this->secret)
            : $request;
    }

    /**
     * The body parameters that authenticate this client. `$audience` is the token
     * endpoint URL, which a client assertion names as its `aud`.
     *
     * @return array<string, string>
     */
    public function parameters(string $audience): array
    {
        return match ($this->method) {
            ClientAuthMethod::None => ['client_id' => $this->clientId],
            ClientAuthMethod::ClientSecretBasic => [],
            ClientAuthMethod::ClientSecretPost => ['client_id' => $this->clientId, 'client_secret' => $this->secret],
            ClientAuthMethod::PrivateKeyJwt => [
                'client_id' => $this->clientId,
                'client_assertion_type' => self::ASSERTION_TYPE,
                'client_assertion' => $this->assertion($audience),
            ],
        };
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['method' => $this->method, 'clientId' => $this->clientId, 'keyId' => $this->keyId, 'algorithm' => $this->algorithm];
    }

    private function assertion(string $audience): string
    {
        $now = time();

        return JWT::encode([
            'iss' => $this->clientId,
            'sub' => $this->clientId,
            'aud' => $audience,
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + self::ASSERTION_LIFETIME,
        ], $this->privateKey, $this->algorithm, $this->keyId);
    }

    private static function requireSecret(#[\SensitiveParameter] string $secret): string
    {
        if ($secret === '') {
            throw NotConfigured::key('client_secret', 'authenticate this client to Cbox ID');
        }

        return $secret;
    }
}
