<?php

namespace Acme\SupportTickets\Api\Resource;

use Acme\SupportTickets\Model\TicketCategory;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;

class TicketCategoryResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'ticket-categories';
    }

    public function model(): string
    {
        return TicketCategory::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated(),
            Endpoint\Show::make()->authenticated(),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')->requiredOnCreate()->writable(),
            Schema\Str::make('slug')->requiredOnCreate()->writable()->unique('ticket_categories', 'slug', true),
            Schema\Str::make('description')->writable()->nullable(),
            Schema\Integer::make('position')->writable(),
            Schema\Boolean::make('isActive')->writable(),
        ];
    }
}
