<?php

namespace Cengizkemec\SupportTickets\Support;

use Cengizkemec\SupportTickets\Model\TicketFormField;

/**
 * Validates the values submitted for the admin-defined ticket form fields.
 *
 * Only values whose field is currently active *and* applicable to the ticket's
 * category are kept, and the value is coerced to the shape the field type
 * expects. This keeps garbage out of tickets.custom_fields even when the
 * request is crafted by hand.
 */
class TicketCustomFields
{
    /**
     * @param array<string, mixed> $submitted  Raw values keyed by field name.
     * @param int|null             $categoryId The category the ticket belongs to.
     * @return array<string, mixed>
     */
    public static function sanitize(array $submitted, ?int $categoryId): array
    {
        if (empty($submitted)) {
            return [];
        }

        $fields = TicketFormField::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $categoryId))
            ->get();

        $clean = [];

        foreach ($fields as $field) {
            $key = $field->name;

            if (! array_key_exists($key, $submitted)) {
                continue;
            }

            $value = $submitted[$key];

            if ($value === null || $value === '') {
                continue;
            }

            switch ($field->type) {
                case 'checkbox':
                    if (is_array($value)) {
                        $value = array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== ''));
                    } else {
                        $value = (bool) $value;
                    }
                    break;

                case 'number':
                    if (! is_numeric($value)) {
                        continue 2;
                    }
                    $value = $value + 0;
                    break;

                case 'email':
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        continue 2;
                    }
                    break;

                case 'url':
                    if (! filter_var($value, FILTER_VALIDATE_URL)) {
                        continue 2;
                    }
                    break;

                case 'date':
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                        continue 2;
                    }
                    break;

                case 'select':
                case 'radio':
                    $options = $field->options ?: [];
                    if (! in_array((string) $value, array_map('strval', $options), true)) {
                        continue 2;
                    }
                    break;

                default:
                    // text / textarea
                    $value = mb_substr((string) $value, 0, 5000);
                    break;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Human readable values for display: field label + formatted value.
     *
     * @param array<string, mixed> $stored
     * @return array<int, array{label: string, value: string, type: string}>
     */
    public static function present(array $stored, ?int $categoryId): array
    {
        if (empty($stored)) {
            return [];
        }

        $fields = TicketFormField::query()
            ->whereIn('name', array_keys($stored))
            ->get()
            ->keyBy('name');

        $out = [];

        foreach ($stored as $name => $value) {
            $field = $fields->get($name);

            // A field that has since been deleted still shows its stored value,
            // just with a generic label, so no ticket history is lost.
            $label = $field ? $field->label : $name;

            $out[] = [
                'label' => $label,
                'value' => self::formatValue($value),
                'type' => $field ? $field->type : 'text',
            ];
        }

        return $out;
    }

    private static function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        if (is_bool($value)) {
            return $value ? '✓' : '✗';
        }

        return (string) $value;
    }
}
