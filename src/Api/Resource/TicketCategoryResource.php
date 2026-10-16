<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\TicketCategory;
use Tobyz\JsonApiServer\Context;
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
            Schema\Str::make('name')->requiredOnCreate()->writable()->maxLength(255),
            Schema\Str::make('slug')->writable()->maxLength(255)->unique('ticket_categories', 'slug', true),
            Schema\Str::make('description')->writable()->nullable()->maxLength(15000),
            // Nullable so the administrator can unlink a category from its
            // department again (the panel sends null). Must point at a real
            // department when set.
            Schema\Integer::make('departmentId')
                ->writable()
                ->nullable()
                ->rule('exists:support_departments,id'),
            Schema\Integer::make('position')->writable(),
            Schema\Boolean::make('isActive')->writable(),

            Schema\Relationship\ToOne::make('department')->type('support-departments')->includable(),
        ];
    }
}
