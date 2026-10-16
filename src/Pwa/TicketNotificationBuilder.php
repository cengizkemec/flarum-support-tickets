<?php

namespace Cengizkemec\SupportTickets\Pwa;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Support\TicketSlug;
use Flarum\Notification\Blueprint\BlueprintInterface;
use FoF\PWA\NotificationBuilder;
/**
 * FoF PWA builds the URL a push notification opens with a hard coded switch on
 * the notification subject model, and falls back to the forum home page for any
 * model it does not know about. Support tickets are one of those, which meant a
 * push notification about a ticket opened the board index instead of the ticket.
 *
 * This subclass adds the missing case. It is bound in place of the original
 * builder from PwaServiceProvider, so every FoF PWA class that asks the
 * container for a NotificationBuilder gets this one.
 */
class TicketNotificationBuilder extends NotificationBuilder
{
    protected function getUrl(BlueprintInterface $blueprint): string
    {
        if ($blueprint::getSubjectModel() === Ticket::class) {
            $subject = $blueprint->getSubject();

            if ($subject instanceof Ticket) {
                return $this->url->to('forum')->base()
                    .TicketSlug::path($subject->id, $subject->subject);
            }
        }

        return parent::getUrl($blueprint);
    }

    protected function getBody(BlueprintInterface $blueprint): string
    {
        if ($blueprint::getSubjectModel() === Ticket::class) {
            $subject = $blueprint->getSubject();

            if ($subject instanceof Ticket) {
                // A short, plain-text excerpt so the notification is useful
                // even before it is tapped.
                return mb_substr(strip_tags((string) $subject->message), 0, 140);
            }
        }

        return parent::getBody($blueprint);
    }
}
