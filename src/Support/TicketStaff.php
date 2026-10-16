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
     * Every user that belongs to the support team.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function members()
    {
        $groupIds = $this->staffGroupIds();

        return User::query()
            ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $groupIds))
            ->get();
    }

    /**
     * Pick the team member with the fewest still-open tickets so the load is
     * spread evenly. Returns null when the team is empty.
     */
    public function pickAssignee(): ?User
    {
        if (! $this->autoAssignEnabled()) {
            return null;
        }

        $members = $this->members();

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
