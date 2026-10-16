<?php

namespace Cengizkemec\SupportTickets\Model;

use Cengizkemec\SupportTickets\Model\SupportDepartment;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends AbstractModel
{
    protected static function booted(): void
    {
        parent::booted();
        static::saving(function (self $model) {
            if ($model->slug === null || trim((string)$model->slug) === '') {
                $name = (string) ($model->name ?? '');
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
                $model->slug = $slug !== '' ? $slug : ('category-' . time());
            }
        });
    }

    protected $table = 'ticket_categories';
    protected $guarded = [];
    public $timestamps = true;

    protected $fillable = ['name', 'slug', 'description', 'position', 'is_active', 'department_id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_active' => 'boolean',
        'position' => 'integer',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department_id');
    }
}
