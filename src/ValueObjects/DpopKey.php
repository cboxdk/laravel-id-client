<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/** A server-side holder key for one delegated OAuth grant. */
final readonly class DpopKey
{
    private function __construct(#[\SensitiveParameter] private string $pem) {}

    public static function generate(): self
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false || ! openssl_pkey_export($key, $pem) || ! is_string($pem)) {
            throw AuthenticationFailed::because('Could not generate a DPoP key.');
        }

        return new self($pem);
    }

    /** Keep key material encrypted in server sessions, including JSON-backed sessions. */
    public function seal(): string
    {
        return Crypt::encryptString($this->pem);
    }

    public static function open(#[\SensitiveParameter] string $sealed): ?self
    {
        try {
            return new self(Crypt::decryptString($sealed));
        } catch (DecryptException) {
            return null;
        }
    }

    /** @return array{crv:string,kty:string,x:string,y:string} */
    private function jwk(): array
    {
        $private = openssl_pkey_get_private($this->pem);
        $details = $private === false ? false : openssl_pkey_get_details($private);
        $point = is_array($details) ? ($details['ec'] ?? null) : null;
        if (! is_array($point) || ! is_string($point['x'] ?? null) || ! is_string($point['y'] ?? null)) {
            throw AuthenticationFailed::because('The stored DPoP key is unavailable.');
        }

        return ['crv' => 'P-256', 'kty' => 'EC', 'x' => self::encode(str_pad($point['x'], 32, "\0", STR_PAD_LEFT)), 'y' => self::encode(str_pad($point['y'], 32, "\0", STR_PAD_LEFT))];
    }

    public function thumbprint(): string
    {
        return self::encode(hash('sha256', json_encode($this->jwk(), JSON_THROW_ON_ERROR), true));
    }

    public function proof(string $method, string $url, #[\SensitiveParameter] ?string $token = null): string
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! parse_url($url, PHP_URL_HOST) || parse_url($url, PHP_URL_USER) !== null) {
            throw AuthenticationFailed::because('DPoP requires a secure endpoint.');
        }
        $claims = ['htm' => strtoupper($method), 'htu' => explode('?', explode('#', $url, 2)[0], 2)[0], 'iat' => time(), 'jti' => bin2hex(random_bytes(24))];
        if ($token !== null) {
            $claims['ath'] = self::encode(hash('sha256', $token, true));
        }

        return JWT::encode($claims, $this->pem, 'ES256', null, ['typ' => 'dpop+jwt', 'jwk' => $this->jwk()]);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** @return array<string,string> */
    public function __debugInfo(): array
    {
        return ['thumbprint' => $this->thumbprint()];
    }
}
