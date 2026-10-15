<?php

namespace Cengizkemec\SupportTickets\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends AbstractModel
{
    protected $table = 'ticket_categories';
    protected $guarded = [];
    public $timestamps = true;

    protected $fillable = ['name', 'slug', 'description', 'position', 'is_active'];

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
}
