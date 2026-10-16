<?php

namespace Cengizkemec\SupportTickets\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends AbstractModel
{
    protected $table = 'tickets';
    protected $guarded = [];
    public $timestamps = true;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'closed_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    /**
     * URL slug derived from the subject, used to build readable ticket links.
     */
    public function slug(): string
    {
        return \Cengizkemec\SupportTickets\Support\TicketSlug::make($this->subject);
    }

    public function urlPath(): string
    {
        return \Cengizkemec\SupportTickets\Support\TicketSlug::path($this->id, $this->subject);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }
}
