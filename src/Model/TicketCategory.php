<?php

namespace Cengizkemec\SupportTickets\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends AbstractModel
{
    protected $table = 'ticket_categories';
    protected $guarded = [];
    protected $fillable = ['name', 'slug', 'description', 'position', 'is_active'];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }
}
