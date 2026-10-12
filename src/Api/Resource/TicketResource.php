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

    public function creating(object $model, Context $context): ?object
    {
        /** @var Ticket $model */
        $actor = $context->getActor();
        $model->user_id = $actor->id;
        $model->status = $model->status ?? 'new';

        return parent::creating($model, $context);
    }

    public function updating(object $model, Context $context): ?object
    {
        /** @var Ticket $model */
        $actor = $context->getActor();

        // Non-staff can only update message/subject (if allowed by policy), not status/priority/assignee
        if (!$actor->can('support.viewAll')) {
            // Reset protected fields if they were changed
            $original = Ticket::find($model->id);
            if ($original) {
                $model->status = $original->status;
                $model->priority = $original->priority;
                $model->assigned_to = $original->assigned_to;
            }
        }

        return parent::updating($model, $context);
    }
}
