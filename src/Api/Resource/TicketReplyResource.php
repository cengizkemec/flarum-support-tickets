<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

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

    public function scope(Builder $query, Context $context): void
    {
        // Visibility handled via ticket policy
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
            Schema\Relationship\ToOne::make('ticket')->type('tickets')->includable(),
        ];
    }

    public function creating(TicketReply $reply, Context $context): void
    {
        $reply->user_id = $context->getActor()->id;
        $reply->is_staff = $context->getActor()->can('support.viewAll');
    }
}
