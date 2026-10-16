<?php

namespace Cengizkemec\SupportTickets\Support;

use Cengizkemec\SupportTickets\Model\Ticket;
use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Resolves the "support team" and picks who a brand new ticket is handed to.
 *
 * The team is whatever group the administrator selected on the extension's
 * settings page. When no group is configured we fall back to the people who
 * hold the support.viewAll permission (administrators included), which is how
 * the extension behaved before the setting existed.
 */
class TicketStaff
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ConnectionInterface $db
    ) {
    }

    public static function staffGroupId(SettingsRepositoryInterface $settings): ?int
    {
        $id = $settings->get('cengizkemec-support.staff_group_id');

        return $id ? (int) $id : null;
    }

    public function autoAssignEnabled(): bool
    {
        return (bool) $this->settings->get('cengizkemec-support.auto_assign', true);
    }

    /**
     * Group ids whose members count as support staff.
     *
     * @return int[]
     */
    public function staffGroupIds(): array
    {
        $configured = self::staffGroupId($this->settings);

        if ($configured) {
            return [$configured];
        }

        $groupIds = [];
        try {
            $groupIds = $this->db->table('group_permission')
                ->where('permission', 'support.viewAll')
                ->pluck('group_id')
                ->all();
        } catch (\Throwable $e) {
            // Ignore: fall back to administrators only.
        }

        // Administrators always have access.
        $groupIds[] = Group::ADMINISTRATOR_ID;

        return array_values(array_unique(array_map('intval', $groupIds)));
    }

    /**
     * Department-aware member list. When a department has explicit user ids, only
     * those users are returned (broadcast is targeted instead of every staff).
     */
    public function members(?int $departmentId = null)
    {
        if ($departmentId) {
            $department = $this->findDepartment($departmentId);
            $ids = $department ? $department->members() : [];

            if (! empty($ids)) {
                $users = User::query()->whereIn('id', $ids)->get();
                if ($users->isNotEmpty()) {
                    return $users;
                }
            }
        }

        $groupIds = $this->staffGroupIds();

        return User::query()
            ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $groupIds))
            ->get();
    }

    /**
     * The department a category routes its tickets to, if any.
     */
    public function departmentForCategory(?int $categoryId): ?int
    {
        if (! $categoryId) {
            return null;
        }

        try {
            $category = \Cengizkemec\SupportTickets\Model\TicketCategory::find($categoryId);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $category || ! $category->department_id) {
            return null;
        }

        $department = $this->findDepartment((int) $category->department_id);

        // A disabled department must not receive tickets: fall back to the
        // general support team instead.
        if (! $department || ! $department->is_active) {
            return null;
        }

        return (int) $department->id;
    }

    protected function findDepartment(int $id): ?\Cengizkemec\SupportTickets\Model\SupportDepartment
    {
        try {
            return \Cengizkemec\SupportTickets\Model\SupportDepartment::find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }


    /**
     * Pick the team member with the fewest still-open tickets so the load is
     * spread evenly. When a department is given, only its members are
     * considered (falling back to the whole team when it has none).
     * Returns null when the team is empty.
     */
    public function pickAssignee(?int $departmentId = null): ?User
    {
        if (! $this->autoAssignEnabled()) {
            return null;
        }

        $members = $this->members($departmentId);

        if ($members->isEmpty()) {
            return null;
        }

        $openCounts = Ticket::query()
            ->whereIn('assigned_to', $members->pluck('id')->all())
            ->whereNotIn('status', ['closed', 'resolved'])
            ->selectRaw('assigned_to, COUNT(*) as open_count')
            ->groupBy('assigned_to')
            ->pluck('open_count', 'assigned_to');

        return $members
            ->sortBy(fn (User $user) => (int) ($openCounts[$user->id] ?? 0))
            ->first();
    }
}
