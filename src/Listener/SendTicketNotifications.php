<?php

namespace Cengizkemec\SupportTickets\Listener;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Cengizkemec\SupportTickets\Notification\NewTicketBlueprint;
use Cengizkemec\SupportTickets\Notification\TicketReplyBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;

class SendTicketNotifications
{
    public function __construct(
        protected NotificationSyncer $notifications,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('eloquent.created: ' . Ticket::class, [$this, 'whenTicketCreated']);
        $events->listen('eloquent.created: ' . TicketReply::class, [$this, 'whenReplyCreated']);
    }

    public function whenTicketCreated(Ticket $ticket): void
    {
        if (!$this->settings->get('cengizkemec-support.email_notifications', true)) {
            return;
        }

        // Notify staff who can view all tickets. Prefer groups with the permission.
        $recipients = User::query()
            ->where(function ($query) {
                $query->whereHas('groups', function ($q) {
                    $q->whereHas('permissions', function ($p) {
                        $p->where('permission', 'support.viewAll');
                    });
                })->orWhere('is_admin', true);
            })
            ->get()
            ->filter(fn (User $user) => $user->can('support.viewAll'));

        if ($recipients->isNotEmpty()) {
            try {
                $this->notifications->sync(new NewTicketBlueprint($ticket), $recipients->all());
            } catch (\Throwable $e) {
                // Do not fail ticket creation if notification fails
                app('log')->error('Failed to send new ticket notification: ' . $e->getMessage());
            }
        }
    }

    public function whenReplyCreated(TicketReply $reply): void
    {
        if (!$this->settings->get('cengizkemec-support.email_notifications', true)) {
            return;
        }

        $ticket = $reply->ticket;
        if (!$ticket) {
            return;
        }

        $recipients = collect();

        // Notify ticket owner if reply is from staff and owner exists
        if ($reply->is_staff && $ticket->user_id !== $reply->user_id && $ticket->user) {
            $recipients->push($ticket->user);
        }

        // Notify assignee if exists and different
        if ($ticket->assigned_to && $ticket->assigned_to !== $reply->user_id) {
            $assignee = User::find($ticket->assigned_to);
            if ($assignee && $assignee->id !== $reply->user_id) {
                $recipients->push($assignee);
            }
        }

        $recipients = $recipients->unique('id')->filter();

        if ($recipients->isNotEmpty()) {
            try {
                $this->notifications->sync(new TicketReplyBlueprint($reply), $recipients->all());
            } catch (\Throwable $e) {
                app('log')->error('Failed to send ticket reply notification: ' . $e->getMessage());
            }
        }
    }
}
