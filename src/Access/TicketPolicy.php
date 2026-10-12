<?php

namespace Cengizkemec\SupportTickets\Access;

use Cengizkemec\SupportTickets\Model\Ticket;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class TicketPolicy extends AbstractPolicy
{
    protected $model = Ticket::class;

    public function view(User $actor, Ticket $ticket): bool
    {
        return $actor->id === $ticket->user_id || $actor->can('support.viewAll') || $actor->can('support.manage');
    }

    public function reply(User $actor, Ticket $ticket): bool
    {
        if (!$this->view($actor, $ticket)) {
            return false;
        }

        // Owners can reply to their own non-closed tickets
        if ($actor->id === $ticket->user_id) {
            return !in_array($ticket->status, ['closed', 'resolved'], true);
        }

        // Staff with reply or viewAll/manage can reply
        return $actor->can('support.reply') || $actor->can('support.viewAll') || $actor->can('support.manage');
    }

    public function update(User $actor, Ticket $ticket): bool
    {
        // Owners can update limited fields (enforced in resource); staff can update more
        return $actor->id === $ticket->user_id || $actor->can('support.viewAll') || $actor->can('support.manage');
    }

    public function delete(User $actor, Ticket $ticket): bool
    {
        return $actor->can('support.manage');
    }
}
