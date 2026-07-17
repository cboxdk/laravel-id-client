<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Facades;

use Cbox\Id\Client\Webhooks\WebhookHandlers;
use Illuminate\Support\Facades\Facade;

/**
 * Register provisioning hooks for Cbox ID webhook events. In a service provider's
 * boot():
 *
 *     CboxIdWebhooks::on('organization.member_added', fn ($e) => Seat::allocate($e->string('user_id')));
 *     CboxIdWebhooks::on('role.assigned',             fn ($e) => …);
 *
 * @method static void on(string $eventType, callable $handler)
 * @method static int dispatch(\Cbox\Id\Client\Webhooks\WebhookEvent $event)
 * @method static list<string> subscribedTypes()
 *
 * @see WebhookHandlers
 */
class CboxIdWebhooks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebhookHandlers::class;
    }
}
