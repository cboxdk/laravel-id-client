<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Migration;

/**
 * A person as your old system knows them, on the way into Cbox ID.
 *
 * Returned from the closure you hand {@see LegacyLogin::using()}. Everything but the email
 * is optional, because a legacy store that can only answer "yes, and here is the address"
 * is still enough to migrate somebody.
 */
final readonly class LegacyUser
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public bool $emailVerified = false,
        /**
         * Your stored hash, when you can expose it.
         *
         * Passing it lets the person keep their password in Cbox ID verbatim — stored as
         * is, and upgraded to the platform hasher on their next login, exactly like a
         * bulk-imported user. Omit it and Cbox ID hashes the password they just proved
         * they know, which is the honest option when your store cannot hand one over.
         */
        public ?string $passwordHash = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'name' => $this->name,
            'email_verified' => $this->emailVerified,
            'password_hash' => $this->passwordHash,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
