<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Fga;

use InvalidArgumentException;

/**
 * The fine-grained authorization tuple notation: `document:readme#viewer@user:alice`, or
 * `folder:policies#viewer@group:eng#member` for a userset.
 *
 * The batch check (`CboxIdApi::environment()->fga->checkBatch(['checks' => […]])`) takes
 * each check in this notation. {@see format()} writes it from the same array
 * `fga->tuples->write()` takes, so code that writes tuples and checks them speaks one shape.
 */
final class FgaTuple
{
    /**
     * @param  array{resource_type: string, resource_id: string, relation: string, subject: array{type: string, id: string, relation?: string|null}}  $tuple
     *
     * @throws InvalidArgumentException for a part that is empty or contains whitespace, `#`,
     *                                  `@`, or `:` in a type or relation — the notation has no
     *                                  escaping, so it would be read back as another tuple.
     *                                  Ids may contain `:`.
     */
    public static function format(array $tuple): string
    {
        $subject = $tuple['subject'];
        $userset = isset($subject['relation']) && $subject['relation'] !== ''
            ? '#'.self::name($subject['relation'], 'subject relation')
            : '';

        return self::name($tuple['resource_type'], 'resource type').':'
            .self::id($tuple['resource_id'], 'resource id').'#'
            .self::name($tuple['relation'], 'relation').'@'
            .self::name($subject['type'], 'subject type').':'
            .self::id($subject['id'], 'subject id')
            .$userset;
    }

    /**
     * One check in the notation, from its parts — what `fga->check()` takes as a query.
     */
    public static function check(string $resourceType, string $resourceId, string $relation, string $subjectType, string $subjectId, ?string $subjectRelation = null): string
    {
        return self::format([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'relation' => $relation,
            'subject' => ['type' => $subjectType, 'id' => $subjectId, 'relation' => $subjectRelation],
        ]);
    }

    private static function name(string $value, string $what): string
    {
        if ($value === '' || preg_match('/[\s#@:]/', $value) === 1) {
            throw new InvalidArgumentException("An FGA {$what} cannot be empty or contain whitespace, '#', '@' or ':': ".json_encode($value));
        }

        return $value;
    }

    private static function id(string $value, string $what): string
    {
        if ($value === '' || preg_match('/[\s#@]/', $value) === 1) {
            throw new InvalidArgumentException("An FGA {$what} cannot be empty or contain whitespace, '#' or '@': ".json_encode($value));
        }

        return $value;
    }
}
