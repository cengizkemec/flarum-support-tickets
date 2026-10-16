<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\TicketFormField;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;

class TicketFormFieldResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'ticket-form-fields';
    }

    public function model(): string
    {
        return TicketFormField::class;
    }

    public function scope(object $query, Context $context): void
    {
        // Staff may manage every field; everybody else only ever needs the
        // active ones so they can render the ticket form.
        $actor = $context->getActor();

        if (! $actor->can('support.manage')) {
            $query->where('is_active', true);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated()->paginate(50, 200)->defaultSort('position'),
            Endpoint\Show::make()->authenticated(),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('position'),
            SortColumn::make('createdAt'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('label')->requiredOnCreate()->writable()->maxLength(255),
            Schema\Str::make('name')->writable()->maxLength(100),
            Schema\Str::make('type')->requiredOnCreate()->writable()->in(TicketFormField::TYPES),
            Schema\Arr::make('options')->writable()->nullable(),
            Schema\Boolean::make('isRequired')->writable(),
            Schema\Boolean::make('isActive')->writable(),
            Schema\Integer::make('position')->writable(),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),

            Schema\Relationship\ToOne::make('category')->type('ticket-categories')->includable()->writable(),
        ];
    }
}
