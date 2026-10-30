<?php

namespace Cengizkemec\SupportTickets\Support;

use Cengizkemec\SupportTickets\Model\TicketFormField;
use Flarum\Foundation\ValidationException;

/**
 * Validates the values submitted for the admin-defined ticket form fields.
 *
 * Only values whose field is currently active *and* applicable to the ticket's
 * category are kept, and the value is coerced to the shape the field type
 * expects. Missing required answers and invalid choices are rejected with a
 * 422 response instead of being silently dropped, so the user always knows
 * why a ticket was not created.
 */
class TicketCustomFields
{
    /**
     * @param array<string, mixed> $submitted  Raw values keyed by field name.
     * @param int|null             $categoryId The category the ticket belongs to.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function sanitize(array $submitted, ?int $categoryId): array
    {
        $fields = TicketFormField::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $categoryId))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $clean = [];
        $errors = [];

        foreach ($fields as $field) {
            $key = $field->name;

            if ($key === null || $key === '') {
                continue;
            }

            $submittedValue = array_key_exists($key, $submitted) ? $submitted[$key] : null;

            if (! array_key_exists($key, $submitted) || self::isEmpty($submittedValue)) {
                if ($field->is_required) {
                    $errors[$key] = self::requiredMessage($field);
                }

                continue;
            }

            [$value, $error] = self::coerce($field, $submittedValue);

            if ($error !== null) {
                $errors[$key] = $error;
                continue;
            }

            $clean[$key] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
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

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isEmpty($item)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return array{0: mixed, 1: string|null} [value, error message]
     */
    private static function coerce(TicketFormField $field, mixed $value): array
    {
        $label = (string) $field->label;

        switch ($field->type) {
            case 'checkbox':
                if (! is_array($value)) {
                    return [null, sprintf('"%s" must be a list of choices.', $label)];
                }

                $options = $field->optionsList();
                $selected = [];

                foreach ($value as $item) {
                    if (! is_scalar($item)) {
                        return [null, sprintf('The selected value for "%s" is not valid.', $label)];
                    }

                    $item = trim((string) $item);

                    if ($item === '') {
                        continue;
                    }

                    if (! in_array($item, $options, true)) {
                        return [null, sprintf('The selected value for "%s" is not valid.', $label)];
                    }

                    if (! in_array($item, $selected, true)) {
                        $selected[] = $item;
                    }
                }

                if ($selected === []) {
                    return [null, self::requiredMessage($field)];
                }

                return [$selected, null];

            case 'select':
            case 'radio':
                if (! is_scalar($value)) {
                    return [null, sprintf('The selected value for "%s" is not valid.', $label)];
                }

                $value = trim((string) $value);

                if (! in_array($value, $field->optionsList(), true)) {
                    return [null, sprintf('The selected value for "%s" is not valid.', $label)];
                }

                return [$value, null];

            case 'number':
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    return [null, sprintf('"%s" must be a number.', $label)];
                }

                return [$value + 0, null];

            case 'email':
                if (! is_scalar($value) || ! filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    return [null, sprintf('"%s" must be a valid email address.', $label)];
                }

                return [(string) $value, null];

            case 'url':
                if (! is_scalar($value)) {
                    return [null, sprintf('"%s" must be a valid URL.', $label)];
                }

                $value = (string) $value;
                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

                if (! filter_var($value, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                    return [null, sprintf('"%s" must be a valid http(s) URL.', $label)];
                }

                return [$value, null];

            case 'date':
                if (! is_scalar($value)) {
                    return [null, sprintf('"%s" must be a valid date (YYYY-MM-DD).', $label)];
                }

                $value = (string) $value;
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

                if (! $date || $date->format('Y-m-d') !== $value) {
                    return [null, sprintf('"%s" must be a valid date (YYYY-MM-DD).', $label)];
                }

                return [$value, null];

            case 'text':
            case 'textarea':
                if (! is_scalar($value)) {
                    return [null, sprintf('"%s" must be text.', $label)];
                }

                return [mb_substr((string) $value, 0, 5000), null];

            default:
                return [null, sprintf('The field "%s" has an unsupported type.', $label)];
        }
    }

    private static function requiredMessage(TicketFormField $field): string
    {
        return sprintf('"%s" is required.', (string) $field->label);
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
