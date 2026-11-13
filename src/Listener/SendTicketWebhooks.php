<?php

namespace Cengizkemec\SupportTickets\Listener;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Cengizkemec\SupportTickets\Webhook\WebhookSender;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Fan ticket events out to the configured webhooks.
 *
 * This listener is intentionally separate from SendTicketNotifications: an
 * administrator may want Discord notifications while in-forum alerts and
 * e-mails are disabled (or the other way around).
 */
class SendTicketWebhooks
{
    public function __construct(protected WebhookSender $webhooks)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('eloquent.created: ' . Ticket::class, [$this, 'whenTicketCreated']);
        $events->listen('eloquent.created: ' . TicketReply::class, [$this, 'whenReplyCreated']);
    }

    public function whenTicketCreated(Ticket $ticket): void
    {
        try {
            $this->webhooks->ticketCreated($ticket);
        } catch (Throwable $e) {
            // Notification delivery must never block ticket creation.
            app('log')->error('Failed to dispatch the new-ticket webhook: ' . $e->getMessage());
        }
    }

    public function whenReplyCreated(TicketReply $reply): void
    {
        try {
            $this->webhooks->ticketReplied($reply);
        } catch (Throwable $e) {
            app('log')->error('Failed to dispatch the ticket-reply webhook: ' . $e->getMessage());
        }
    }
}
