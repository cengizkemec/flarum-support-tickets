<?php

namespace Cengizkemec\SupportTickets\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportDepartment extends AbstractModel
{
    protected static function booted(): void
    {
        parent::booted();
        static::saving(function (self $model) {
            $model->user_ids = $model->normalizedMemberIds();

            if ($model->slug === null || trim((string) $model->slug) === '') {
                $model->slug = self::slugFor($model->name);
            }
        });
    }

    public static function slugFor(?string $name, ?int $fallbackId = null): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $name), '-'));

        if ($slug !== '') {
            return $slug;
        }

        return 'department-' . ($fallbackId ?: time());
    }

    /**
     * Keep only unique, positive integers, in the order they were given.
     *
     * @return int[]
     */
    public function normalizedMemberIds(): array
    {
        $ids = $this->user_ids;

        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($ids)) {
            return [];
        }

        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    protected $table = 'support_departments';
    protected $guarded = [];
    protected $fillable = ['name','slug','description','user_ids','is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'user_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return int[]
     */
    public function members(): array
    {
        return array_map('intval', $this->normalizedMemberIds());
    }

    public function memberCount(): int
    {
        return count($this->members());
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TicketCategory::class, 'department_id');
    }
}
