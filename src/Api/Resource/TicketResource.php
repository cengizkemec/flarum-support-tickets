<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\Ticket;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;

class TicketResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'tickets';
    }

    public function model(): string
    {
        return Ticket::class;
    }

    public function scope(object $query, Context $context): void
    {
        $actor = $context->getActor();
        if (!$actor->can('support.viewAll')) {
            $query->where('user_id', $actor->id);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated()->paginate(20, 50)->defaultSort('-createdAt'),
            Endpoint\Show::make()->authenticated(),
            Endpoint\Create::make()->authenticated(),
            Endpoint\Update::make()->authenticated(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('subject')->requiredOnCreate()->writable()->maxLength(255),
            Schema\Str::make('message')->requiredOnCreate()->writable(),
            Schema\Str::make('status')->writable(),
            Schema\Str::make('priority')->writable(),
            Schema\Str::make('attachment')->writable()->nullable(),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),
            Schema\DateTime::make('closedAt')->nullable(),

            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToOne::make('category')->type('ticket-categories')->includable(),
            Schema\Relationship\ToOne::make('assignee')->type('users')->includable(),
            Schema\Relationship\ToMany::make('replies')->type('ticket-replies')->includable(),
        ];
    }
}
