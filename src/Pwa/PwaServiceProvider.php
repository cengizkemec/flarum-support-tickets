<?php

namespace Cengizkemec\SupportTickets\Pwa;

use FoF\PWA\NotificationBuilder;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Teaches FoF PWA how to link to a support ticket.
 *
 * The binding is guarded by class_exists so the extension keeps working on
 * forums where FoF PWA is not installed.
 */
class PwaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists(NotificationBuilder::class)) {
            return;
        }

        $this->app->singleton(NotificationBuilder::class, function (Container $app) {
            return new TicketNotificationBuilder(
                $app->make(\Flarum\Locale\TranslatorInterface::class),
                $app->make(\Flarum\Http\UrlGenerator::class),
            );
        });
    }
}
