<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\Ticket;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\User\Exception\PermissionDeniedException;

class TicketResource extends AbstractDatabaseResource
{
    private const ALLOWED_STATUSES = ['new', 'answered', 'pending', 'resolved', 'closed'];
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high', 'critical'];

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
            Schema\Str::make('status')
                ->writable(fn (Ticket $ticket, Context $context) => $context->getActor()->can('support.viewAll') || $context->getActor()->can('support.manage'))
                ->in(self::ALLOWED_STATUSES),
            Schema\Str::make('priority')
                ->writable(fn (Ticket $ticket, Context $context) => $context->creating() || $context->getActor()->can('support.viewAll') || $context->getActor()->can('support.manage'))
                ->in(self::ALLOWED_PRIORITIES),
            Schema\Str::make('attachment')->writable()->nullable(),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),
            Schema\DateTime::make('closedAt')->nullable(),

            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToOne::make('category')->type('ticket-categories')->includable(),
            Schema\Relationship\ToOne::make('assignee')
                ->type('users')
                ->includable()
                ->writable(fn (Ticket $ticket, Context $context) => $context->getActor()->can('support.viewAll') || $context->getActor()->can('support.manage')),
            Schema\Relationship\ToMany::make('replies')->type('ticket-replies')->includable(),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        /** @var Ticket $model */
        $actor = $context->getActor();
        $model->user_id = $actor->id;

        // Force safe defaults; do not trust client for status
        $model->status = 'new';
        if (!in_array($model->priority, self::ALLOWED_PRIORITIES, true)) {
            $model->priority = 'medium';
        }

        // Only allow attachment filename that looks like our generated names
        if ($model->attachment && !preg_match('/^[a-zA-Z0-9]{40}\.[a-z0-9]+$/', $model->attachment)) {
            $model->attachment = null;
        }

        return parent::creating($model, $context);
    }

    public function updating(object $model, Context $context): ?object
    {
        /** @var Ticket $model */
        $actor = $context->getActor();

        // Non-staff cannot change protected fields
        if (!$actor->can('support.viewAll') && !$actor->can('support.manage')) {
            $original = Ticket::find($model->id);
            if ($original) {
                $model->status = $original->status;
                $model->priority = $original->priority;
                $model->assigned_to = $original->assigned_to;
            }
        }

        // Validate status/priority if changed
        if (isset($model->status) && !in_array($model->status, self::ALLOWED_STATUSES, true)) {
            throw new PermissionDeniedException();
        }
        if (isset($model->priority) && !in_array($model->priority, self::ALLOWED_PRIORITIES, true)) {
            throw new PermissionDeniedException();
        }

        if ($model->status === 'closed' && !$model->closed_at) {
            $model->closed_at = now();
        }

        return parent::updating($model, $context);
    }
}
