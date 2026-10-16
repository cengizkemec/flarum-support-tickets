<?php

namespace Cengizkemec\SupportTickets\Model;

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
            if ($model->name === null || trim((string)$model->name) === '') {
                $label = (string) ($model->label ?? '');
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label), '_'));
                $model->name = $slug !== '' ? $slug : ('field_' . time());
            }
        });
    }

    protected $table = 'ticket_form_fields';
    protected $guarded = [];

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }
}
