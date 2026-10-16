<?php

namespace Cengizkemec\SupportTickets\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportDepartment extends AbstractModel
{
    protected $table = 'support_departments';
    protected $guarded = [];
    protected $fillable = ['name','slug','description','user_ids','is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'user_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function members(): array
    {
        $ids = $this->user_ids ?: [];
        return array_map('intval', $ids);
    }
}
