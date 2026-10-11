<?php

namespace Cengizkemec\SupportTickets\Listener;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Cengizkemec\SupportTickets\Notification\NewTicketBlueprint;
use Cengizkemec\SupportTickets\Notification\TicketReplyBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;

class SendTicketNotifications
{
    public function __construct(protected NotificationSyncer $notifications)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('eloquent.created: ' . Ticket::class, [$this, 'whenTicketCreated']);
        $events->listen('eloquent.created: ' . TicketReply::class, [$this, 'whenReplyCreated']);
    }

    public function whenTicketCreated(Ticket $ticket): void
    {
        // Notify staff (moderators/admins)
        $recipients = User::whereHas('groups', function ($q) {
            $q->whereIn('id', [1, 4]); // Admin + Moderator example
        })->get();

        $this->notifications->sync(new NewTicketBlueprint($ticket), $recipients->all());
    }

    public function whenReplyCreated(TicketReply $reply): void
    {
        $ticket = $reply->ticket;
        $recipients = collect();

        // Notify ticket owner if reply is from staff
        if ($reply->is_staff && $ticket->user_id !== $reply->user_id) {
            $recipients->push($ticket->user);
        }

        // Notify assignee if exists and different
        if ($ticket->assigned_to && $ticket->assigned_to !== $reply->user_id) {
            $assignee = User::find($ticket->assigned_to);
            if ($assignee) {
                $recipients->push($assignee);
            }
        }

        if ($recipients->isNotEmpty()) {
            $this->notifications->sync(new TicketReplyBlueprint($reply), $recipients->all());
        }
    }
}
