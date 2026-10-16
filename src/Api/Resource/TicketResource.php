<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Carbon\Carbon;
use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Support\TicketCustomFields;
use Cengizkemec\SupportTickets\Support\TicketFormat;
use Cengizkemec\SupportTickets\Support\TicketStaff;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
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

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
            SortColumn::make('updatedAt'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('subject')->requiredOnCreate()->writable()->maxLength(255),
            // Bounded so a crafted request cannot write more than the column
            // holds (TEXT) or burn memory formatting an enormous body.
            Schema\Str::make('message')->requiredOnCreate()->writable()->maxLength(15000),
            // The message rendered by the forum formatter, so the ticket view
            // shows the same formatting (bold, links, emoji, code...) the rich
            // editor produces.
            Schema\Str::make('messageHtml')
                ->get(fn (Ticket $ticket, Context $context) => TicketFormat::html($ticket->message, $context->getActor())),
            Schema\Str::make('status')
                ->writable(fn (Ticket $ticket, Context $context) => $context->getActor()->can('support.viewAll') || $context->getActor()->can('support.manage'))
                ->in(self::ALLOWED_STATUSES),
            Schema\Str::make('priority')
                ->writable(fn (Ticket $ticket, Context $context) => $context->creating() || $context->getActor()->can('support.viewAll') || $context->getActor()->can('support.manage'))
                ->in(self::ALLOWED_PRIORITIES),
            Schema\Str::make('attachment')->writable()->nullable(),
            Schema\Arr::make('customFields')->writable(fn (Ticket $ticket, Context $context) => $context->creating()),
            Schema\Str::make('slug')->get(fn (Ticket $ticket) => $ticket->slug()),
            Schema\Arr::make('customFieldValues')
                ->get(fn (Ticket $ticket) => TicketCustomFields::present((array) $ticket->custom_fields, $ticket->category_id)),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),
            Schema\DateTime::make('closedAt')->nullable(),

            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToOne::make('category')->type('ticket-categories')->includable()->writable(),
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

        // Keep only the values that belong to an active field for this category.
        $submitted = is_array($model->custom_fields) ? $model->custom_fields : [];
        $model->custom_fields = TicketCustomFields::sanitize($submitted, $model->category_id ? (int) $model->category_id : null);

        // Hand brand new tickets straight to the support team so nothing sits
        // unclaimed. When the category is linked to a department, the ticket is
        // assigned inside that department. Only when it was not already
        // assigned by a staff member.
        if (! $model->assigned_to) {
            try {
                $staff = resolve(TicketStaff::class);

                $departmentId = $staff->departmentForCategory(
                    $model->category_id ? (int) $model->category_id : null
                );

                $assignee = $staff->pickAssignee($departmentId);
                if ($assignee) {
                    $model->assigned_to = $assignee->id;
                }
            } catch (\Throwable $e) {
                // Assignment is best-effort: never block ticket creation.
            }
        }

        return parent::creating($model, $context);
    }

    public function updating(object $model, Context $context): ?object
    {
        /** @var Ticket $model */
        $actor = $context->getActor();

        // Attribute values and belongs-to keys are applied before this hook
        // runs, so restoring stored values here is authoritative. Custom field
        // answers are fixed once the ticket exists; only staff-facing
        // status/priority/assignee may change later.
        $original = Ticket::find($model->id);

        if ($original) {
            $model->custom_fields = $original->custom_fields;

            if (! $actor->can('support.viewAll') && ! $actor->can('support.manage')) {
                $model->subject = $original->subject;
                $model->message = $original->message;
                $model->attachment = $original->attachment;
                $model->status = $original->status;
                $model->priority = $original->priority;
                $model->assigned_to = $original->assigned_to;
                $model->category_id = $original->category_id;
                $model->user_id = $original->user_id;
            }

            // Attachment names are always server-generated; a hand-crafted
            // value is refused even from staff, and the stored one kept.
            if ($model->attachment && ! preg_match('/^[a-zA-Z0-9]{40}\.[a-z0-9]+$/', $model->attachment)) {
                $model->attachment = $original->attachment;
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
            $model->closed_at = Carbon::now();
        }

        return parent::updating($model, $context);
    }
}
