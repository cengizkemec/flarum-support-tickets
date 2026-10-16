<?php

namespace Cengizkemec\SupportTickets\Console;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Flarum\Console\AbstractCommand;
use Flarum\Foundation\Paths;
use Illuminate\Support\Carbon;

/**
 * Removes stored attachments that are no longer referenced by any ticket or
 * reply (for example, uploads that were never submitted, or files whose ticket
 * was deleted). Without this, abandoned uploads would accumulate forever.
 */
class PurgeOrphanAttachmentsCommand extends AbstractCommand
{
    /**
     * Files younger than this are left alone so an upload that is still being
     * submitted is never deleted out from under the user.
     */
    private const MIN_AGE_HOURS = 24;

    public function __construct(protected Paths $paths)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('support-tickets:purge-attachments')
            ->setDescription('Delete stored ticket attachments that are not referenced by any ticket or reply.');
    }

    protected function fire(): int
    {
        $directory = $this->paths->storage . '/support-tickets';

        if (!is_dir($directory)) {
            $this->info('No attachment directory yet; nothing to purge.');

            return 0;
        }

        $referenced = Ticket::query()
            ->whereNotNull('attachment')
            ->pluck('attachment')
            ->merge(TicketReply::query()->whereNotNull('attachment')->pluck('attachment'))
            ->filter()
            ->flip();

        $cutoff = Carbon::now()->subHours(self::MIN_AGE_HOURS)->getTimestamp();
        $deleted = 0;

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory . '/' . $name;

            if (!is_file($path) || $referenced->has($name)) {
                continue;
            }

            if (filemtime($path) > $cutoff) {
                continue;
            }

            if (@unlink($path)) {
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} orphaned attachment(s).");

        return 0;
    }
}
