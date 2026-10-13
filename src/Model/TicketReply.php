<?php

namespace Cengizkemec\SupportTickets\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends AbstractModel
{
    protected $table = 'ticket_replies';
    protected $guarded = [];
    protected $fillable = ['ticket_id', 'user_id', 'message', 'attachment', 'is_staff'];
    protected $dates = ['created_at', 'updated_at'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
