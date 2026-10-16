<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\Eloquent\Builder;

class TicketReplyResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'ticket-replies';
    }

    public function model(): string
    {
        return TicketReply::class;
    }

    public function scope(object $query, Context $context): void
    {
        /** @var Builder $query */
        $actor = $context->getActor();

        if ($actor->can('support.viewAll')) {
            return;
        }

        $query->whereHas('ticket', function ($q) use ($actor) {
            $q->where('user_id', $actor->id);
        });
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated(),
            Endpoint\Create::make()->authenticated(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('message')->requiredOnCreate()->writable(),
            Schema\Str::make('attachment')->writable()->nullable(),
            Schema\Boolean::make('isStaff')->get(fn (TicketReply $reply) => (bool) $reply->is_staff),
            Schema\DateTime::make('createdAt'),

            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToOne::make('ticket')->type('tickets')->includable()->requiredOnCreate()->writableOnCreate(),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        /** @var TicketReply $model */
        $actor = $context->getActor();

        $ticket = $model->ticket;
        if (!$ticket) {
            throw new PermissionDeniedException();
        }

        // Policy check: owner of open ticket, or staff with reply/viewAll/manage
        if (!$actor->can('reply', $ticket)) {
            throw new PermissionDeniedException();
        }

        // Reject replies on closed tickets for non-staff
        if (in_array($ticket->status, ['closed', 'resolved'], true) && !$actor->can('support.viewAll')) {
            throw new PermissionDeniedException('Cannot reply to a closed ticket');
        }

        $model->user_id = $actor->id;
        $model->is_staff = $actor->can('support.viewAll') || $actor->can('support.manage');

        // Validate attachment filename
        if ($model->attachment && !preg_match('/^[a-zA-Z0-9]{40}\.[a-z0-9]+$/', $model->attachment)) {
            $model->attachment = null;
        }

        return parent::creating($model, $context);
    }
}
