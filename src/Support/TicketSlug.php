<?php

namespace Cengizkemec\SupportTickets\Support;

/**
 * Builds the URL-friendly slug that is appended to a ticket URL
 * (for example /support/18/ses-ve-mikrofon-sorunu).
 *
 * The same algorithm runs in the admin/forum JavaScript (see
 * js/src/common/utils/slug.ts) so a slug generated on either side matches.
 */
class TicketSlug
{
    private const TURKISH = [
        'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g',
        'ı' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's',
        'ü' => 'u', 'Ü' => 'u',
    ];

    /**
     * Maximum length of the slug segment. Kept short enough to stay readable
     * in a URL bar while still describing the ticket.
     */
    public const MAX_LENGTH = 80;

    public static function make(?string $value, string $fallback = ''): string
    {
        $value = strtr((string) $value, self::TURKISH);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if ($normalized !== false) {
                $value = $normalized;
            }
            // Drop the combining marks left behind by the decomposition
            // (é -> e + ́ becomes just e).
            $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            return $fallback;
        }

        if (strlen($value) > self::MAX_LENGTH) {
            $value = substr($value, 0, self::MAX_LENGTH);
            // Never leave a dangling dash after cutting the slug short.
            $value = rtrim($value, '-');
        }

        return $value;
    }

    /**
     * Full, canonical path for a ticket ("/support/18/ses-sorunu").
     */
    public static function path(int $id, ?string $subject): string
    {
        $slug = self::make($subject);

        return $slug === '' ? "/support/{$id}" : "/support/{$id}/{$slug}";
    }
}
