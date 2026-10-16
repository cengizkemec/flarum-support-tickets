<?php

namespace Cengizkemec\SupportTickets\Listener;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Cengizkemec\SupportTickets\Notification\NewTicketBlueprint;
use Cengizkemec\SupportTickets\Notification\TicketReplyBlueprint;
use Cengizkemec\SupportTickets\Support\TicketStaff;
use Flarum\Notification\NotificationSyncer;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;

class SendTicketNotifications
{
    public function __construct(
        protected NotificationSyncer $notifications,
        protected SettingsRepositoryInterface $settings,
        protected TicketStaff $staff
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('eloquent.created: ' . Ticket::class, [$this, 'whenTicketCreated']);
        $events->listen('eloquent.created: ' . TicketReply::class, [$this, 'whenReplyCreated']);
    }

    public function whenTicketCreated(Ticket $ticket): void
    {
        if (!$this->notificationsEnabled()) {
            return;
        }

        // Notify all staff so someone can pick the ticket up, but never the creator.
        $recipients = $this->staffRecipients()
            ->reject(fn (User $user) => $user->id === $ticket->user_id);

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
        if (!$this->notificationsEnabled()) {
            return;
        }

        $ticket = $reply->ticket;
        if (!$ticket) {
            return;
        }

        $recipients = collect();

        if ($reply->is_staff) {
            // A staff member replied: let the requester know.
            if ($ticket->user && $ticket->user_id !== $reply->user_id) {
                $recipients->push($ticket->user);
            }
        } else {
            // The requester replied: notify staff so the ticket does not stall.
            $recipients = $recipients->merge($this->staffRecipients());
        }

        // Always notify the assignee (when someone other than the author is assigned).
        if ($ticket->assigned_to && $ticket->assigned_to !== $reply->user_id) {
            $assignee = User::find($ticket->assigned_to);
            if ($assignee) {
                $recipients->push($assignee);
            }
        }

        $recipients = $recipients
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $reply->user_id);

        if ($recipients->isNotEmpty()) {
            try {
                $this->notifications->sync(new TicketReplyBlueprint($reply), $recipients->all());
            } catch (\Throwable $e) {
                app('log')->error('Failed to send ticket reply notification: ' . $e->getMessage());
            }
        }
    }

    protected function notificationsEnabled(): bool
    {
        return (bool) $this->settings->get('cengizkemec-support.email_notifications', true);
    }

    /**
     * All users that belong to the support team.
     *
     * @return Collection<int, User>
     */
    protected function staffRecipients(): Collection
    {
        return $this->staff->members();
    }
}
