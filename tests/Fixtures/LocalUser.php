<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * An application's own user model, never persisted: the tests need something that is
 * `Authenticatable` and `Authorizable`, the way a real app's `App\Models\User` is.
 */
class LocalUser extends User
{
    protected $guarded = [];

    public static function withId(int|string $id): self
    {
        return (new self)->forceFill(['id' => $id]);
    }
}
