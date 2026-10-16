<?php

namespace Cengizkemec\SupportTickets\Support;

use Flarum\Formatter\Formatter;
use Flarum\User\User;

/**
 * Renders stored ticket messages for display.
 *
 * Messages are stored as the raw text the user typed; the rich editor writes
 * markdown (or whatever the active formatting extension writes). Rendering
 * goes through the very same formatter the forum uses for posts, so the ticket
 * view understands exactly the same syntax as the composer.
 */
class TicketFormat
{
    public static function html(?string $text, ?User $actor = null): string
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        try {
            $formatter = resolve(Formatter::class);

            return $formatter->render($formatter->parse($text, null, $actor));
        } catch (\Throwable $e) {
            // Formatting is a nicety: never let it break the ticket. Fall back
            // to escaped plain text with the line breaks preserved.
            return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
    }
}
