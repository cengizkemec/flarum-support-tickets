<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
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

        // Only show replies for tickets the actor owns or can view
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
            Schema\Boolean::make('isStaff')->get(fn (TicketReply $reply) => $reply->user?->isAdmin() || $reply->user?->can('support.viewAll')),
            Schema\DateTime::make('createdAt'),

            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToOne::make('ticket')->type('tickets')->includable()->requiredOnCreate(),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        /** @var TicketReply $model */
        $actor = $context->getActor();

        // Explicit permission check
        if (!$actor->can('support.reply') && !$actor->can('support.viewAll')) {
            throw new \Flarum\User\Exception\PermissionDeniedException();
        }

        // Verify access to the parent ticket
        $ticket = $model->ticket;
        if (!$ticket || !$actor->can('reply', $ticket)) {
            throw new \Flarum\User\Exception\PermissionDeniedException();
        }

        $model->user_id = $actor->id;
        $model->is_staff = $actor->can('support.viewAll');

        return parent::creating($model, $context);
    }
}
