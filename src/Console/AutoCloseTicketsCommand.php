<?php

namespace Cengizkemec\SupportTickets\Console;

use Cengizkemec\SupportTickets\Model\Ticket;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Carbon;

class AutoCloseTicketsCommand extends AbstractCommand
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('support-tickets:auto-close')
            ->setDescription('Automatically close tickets that have been inactive for the configured number of days.');
    }

    protected function fire(): int
    {
        $days = (int) $this->settings->get('cengizkemec-support.auto_close_days', 0);

        if ($days <= 0) {
            $this->info('Auto-close is disabled (auto_close_days <= 0).');
            return 0;
        }

        $cutoff = Carbon::now()->subDays($days);

        // Only close open-ish tickets that have not been updated recently
        // and are not already closed/resolved
        $tickets = Ticket::query()
            ->whereNotIn('status', ['closed', 'resolved'])
            ->where('updated_at', '<', $cutoff)
            ->whereNull('closed_at')
            ->get();

        $closed = 0;
        foreach ($tickets as $ticket) {
            $ticket->status = 'closed';
            $ticket->closed_at = Carbon::now();
            $ticket->save();
            $closed++;
        }

        $this->info("Auto-closed {$closed} ticket(s) inactive for more than {$days} days.");

        return 0;
    }
}
