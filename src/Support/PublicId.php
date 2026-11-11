<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

/**
 * Cbox ID's object ids, in either spelling.
 *
 * Cbox ID is moving its API, webhooks and console to prefixed public ids
 * (`org_01J9Z…`, `usr_01J9Z…`: a type prefix and the object's ULID), while token claims
 * (`sub`, `org_id`) keep the raw ULID. For one release both spellings arrive, often for the
 * same object in the same request, so the SDK compares ids through this class and never
 * with `===`.
 *
 * Only `<prefix>_<ULID>` is unwrapped. Anything else — `cid_…` client ids that are not a
 * ULID, test ids such as `org_1`, keys — is compared exactly as given.
 */
final class PublicId
{
    /** A ULID is 26 Crockford base32 characters. */
    private const string PATTERN = '/\A[a-z][a-z0-9]{0,15}_([0-9A-HJKMNP-TV-Z]{26})\z/i';

    /**
     * The id without its type prefix: the raw ULID for `org_01J9Z…`, the id itself
     * otherwise. The ULID comes back lowercase, the way Cbox ID stores it.
     */
    public static function raw(string $id): string
    {
        return preg_match(self::PATTERN, $id, $match) === 1 ? strtolower($match[1]) : $id;
    }

    /** Whether `$id` is a prefixed public id (`org_01J9Z…`). */
    public static function isPrefixed(string $id): bool
    {
        return preg_match(self::PATTERN, $id) === 1;
    }

    /**
     * Whether two ids name the same object: `org_01J9Z…` and `01j9z…` do. Null and the
     * empty string never match anything.
     */
    public static function same(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        $rawA = self::raw($a);
        $rawB = self::raw($b);

        // Unwrapped on either side: compare the ULIDs regardless of case. Neither side a
        // public id: they were already compared exactly.
        return ($rawA !== $a || $rawB !== $b) && strtolower($rawA) === strtolower($rawB);
    }
}
