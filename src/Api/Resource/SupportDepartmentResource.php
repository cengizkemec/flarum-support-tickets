<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\SupportDepartment;
use Cengizkemec\SupportTickets\Model\TicketCategory;
use Flarum\User\User;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;

class SupportDepartmentResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'support-departments';
    }

    public function model(): string
    {
        return SupportDepartment::class;
    }

    public function scope(object $query, Context $context): void
    {
        // People who cannot manage support only ever see the departments that
        // are actually routeable, so a disabled one never shows up as a hint
        // on the ticket form.
        $actor = $context->getActor();

        if (! $actor->can('support.manage') && ! $actor->can('support.viewAll')) {
            $query->where('is_active', true);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated()->paginate(50, 200)->defaultSort('createdAt'),
            Endpoint\Show::make()->authenticated(),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('name'),
            SortColumn::make('createdAt'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')->requiredOnCreate()->writable()->maxLength(100),
            Schema\Str::make('slug')->writable()->maxLength(100)->unique('support_departments', 'slug', true),
            Schema\Str::make('description')->writable()->nullable()->maxLength(500),
            Schema\Arr::make('userIds')
                // Department members are an administrative detail: regular
                // users may see the department, never who is in it.
                ->get(fn (SupportDepartment $department, Context $context) => $context->getActor()->isAdmin()
                    ? $department->members()
                    : [])
                ->writable()
                ->nullable()
                // A department never needs more members than the list can be
                // reasonably allowed to carry.
                ->rule('max:500'),
            Schema\Boolean::make('isActive')->writable(),
            Schema\Integer::make('memberCount')
                ->get(fn (SupportDepartment $department) => $department->memberCount()),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        /** @var SupportDepartment $model */
        $model->user_ids = $this->existingUserIds($model->user_ids ?? []);

        return parent::creating($model, $context);
    }

    public function updating(object $model, Context $context): ?object
    {
        /** @var SupportDepartment $model */
        if ($model->isDirty('user_ids')) {
            $model->user_ids = $this->existingUserIds($model->user_ids ?? []);
        }

        return parent::updating($model, $context);
    }

    public function deleting(object $model, Context $context): void
    {
        /** @var SupportDepartment $model */
        // Unlink categories first so no category points at a departed
        // department. Tickets keep their category, so nothing is lost.
        TicketCategory::query()
            ->where('department_id', $model->id)
            ->update(['department_id' => null]);

        parent::deleting($model, $context);
    }

    /**
     * Keep only ids that really are members of this forum.
     *
     * @param  mixed  $ids
     * @return int[]
     */
    protected function existingUserIds($ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));

        if (empty($ids)) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
