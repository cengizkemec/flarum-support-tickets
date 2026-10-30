<?php

namespace Cengizkemec\SupportTickets\Model;

use Cengizkemec\SupportTickets\Support\TicketSlug;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-defined extra field shown on the "new ticket" form, HESK style.
 */
class TicketFormField extends AbstractModel
{
    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $model) {
            if ($model->name === null || trim((string) $model->name) === '') {
                $model->name = self::nameFor($model->label, $model->id);
            }

            if ($model->type === null || trim((string) $model->type) === '') {
                $model->type = 'text';
            }

            if ($model->position === null) {
                $model->position = ((int) self::query()->max('position')) + 1;
            }
        });
    }

    /**
     * Turn a human readable label into the machine name used as the key in
     * tickets.custom_fields. The JavaScript admin form uses the same rules
     * (see js/src/common/utils/slug.ts), so a key generated on either side
     * matches. Turkish characters are transliterated.
     */
    public static function nameFor(?string $value, ?int $fallbackId = null): string
    {
        $name = str_replace('-', '_', TicketSlug::make($value));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name) ?? '';
        $name = trim($name, '_');

        if ($name === '') {
            $name = 'field_'.($fallbackId ?: substr(md5(uniqid('', true)), 0, 8));
        }

        return substr($name, 0, 100);
    }

    protected $table = 'ticket_form_fields';
    protected $guarded = [];
    public $timestamps = true;

    public const TYPES = [
        'text',
        'textarea',
        'number',
        'email',
        'url',
        'date',
        'select',
        'radio',
        'checkbox',
    ];

    /**
     * Types that carry a list of choices.
     */
    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * @return string[]
     */
    public function optionsList(): array
    {
        $options = $this->options;

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($options)) {
            return [];
        }

        $clean = [];
        foreach ($options as $option) {
            if (! is_scalar($option)) {
                continue;
            }

            $option = trim((string) $option);
            if ($option !== '' && ! in_array($option, $clean, true)) {
                $clean[] = $option;
            }
        }

        return $clean;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }
}
