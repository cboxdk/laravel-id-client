<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/** @internal Server-side authorization transaction, never a browser payload. */
final class PendingResourceAuthorization
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string,string>  $context
     */
    public function __construct(
        public string $resource,
        public string $organizationId,
        public string $expectedSubject,
        public string $redirectUri,
        public array $scopes,
        public array $context,
        #[\SensitiveParameter] public string $verifier,
        public int $expires,
        public ?DpopKey $holder = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['resource' => $this->resource, 'organizationId' => $this->organizationId, 'expectedSubject' => $this->expectedSubject, 'redirectUri' => $this->redirectUri, 'scopes' => $this->scopes, 'context' => $this->context, 'verifier' => $this->verifier, 'expires' => $this->expires, 'holder' => $this->holder?->seal()];
    }

    public static function fromStored(mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }
        foreach (['resource', 'organizationId', 'expectedSubject', 'redirectUri', 'verifier'] as $key) {
            if (! isset($value[$key]) || ! is_string($value[$key]) || $value[$key] === '') {
                return null;
            }
        }
        $scopes = $value['scopes'] ?? null;
        $context = $value['context'] ?? null;
        $expires = $value['expires'] ?? null;
        if (! is_array($scopes) || ! array_is_list($scopes) || ! is_array($context) || ! is_int($expires)) {
            return null;
        }
        $validScopes = [];
        foreach ($scopes as $scope) {
            if (! is_string($scope)) {
                return null;
            }
            $validScopes[] = $scope;
        }
        $validContext = [];
        foreach ($context as $key => $item) {
            if (! is_string($key) || ! is_string($item)) {
                return null;
            }
            $validContext[$key] = $item;
        }

        $sealed = $value['holder'] ?? null;
        $holder = is_string($sealed) ? DpopKey::open($sealed) : null;
        if ($sealed !== null && $holder === null) {
            return null;
        }

        return new self($value['resource'], $value['organizationId'], $value['expectedSubject'], $value['redirectUri'], $validScopes, $validContext, $value['verifier'], $expires, $holder);
    }

    /** @return array<string,mixed> */
    public function __debugInfo(): array
    {
        return ['resource' => $this->resource, 'expires' => $this->expires];
    }
}
