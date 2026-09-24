<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The management API rejected the input (422). `$errors` is the field-keyed list of
 * messages, e.g. `['slug' => ['That slug is already in use.']]`.
 */
class ValidationFailed extends ManagementApiException
{
    /** @var array<string, list<string>> */
    public array $errors = [];

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, list<string>>
     */
    public static function parseErrors(array $raw): array
    {
        $out = [];

        foreach ($raw as $field => $messages) {
            $list = is_string($messages) ? [$messages] : (is_array($messages) ? array_values(array_filter($messages, 'is_string')) : []);

            if ($list !== []) {
                $out[$field] = $list;
            }
        }

        return $out;
    }
}
