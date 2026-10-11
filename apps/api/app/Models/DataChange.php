<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row-level change recorded by the SQL Server audit triggers
 * (dbo.data_change_log). Read-only from the application's side.
 */
class DataChange extends Model
{
    public const OPERATIONS = ['I' => 'insert', 'U' => 'update', 'D' => 'delete'];

    protected $table = 'data_change_log';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'old_values' => 'array',
            'new_values' => 'array',
            'owner_user_id' => 'integer',
            'actor_user_id' => 'integer',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class, 'request_id', 'request_id');
    }

    /** Changes the student made, or made to the student's own records. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('actor_user_id', $user->id)
            ->orWhere(fn ($q) => $q->whereNull('actor_user_id')->where('owner_user_id', $user->id)));
    }

    public function operationName(): string
    {
        return self::OPERATIONS[$this->operation] ?? $this->operation;
    }

    /** @return array<int, string> */
    public function changedColumns(): array
    {
        return $this->changed_columns ? explode(',', $this->changed_columns) : [];
    }

    /**
     * Field-by-field before/after for display.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed, changed: bool}>
     */
    public function diff(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $changed = $this->changedColumns();
        $fields = array_unique([...array_keys($old), ...array_keys($new)]);

        return array_map(fn ($field) => [
            'field' => $field,
            'old' => $old[$field] ?? null,
            'new' => $new[$field] ?? null,
            'changed' => $this->operation !== 'U' || in_array($field, $changed, true),
        ], $fields);
    }

    /** A short human label: "assignments #3 — title, status". */
    public function summary(): string
    {
        $values = $this->new_values ?? $this->old_values ?? [];
        $label = $values['title'] ?? $values['name'] ?? $values['code'] ?? $values['email'] ?? null;

        return trim(($label ? '“'.mb_strimwidth((string) $label, 0, 60, '…').'”' : '#'.$this->record_id)
            .($this->operation === 'U' && $this->changed_columns ? ' — '.str_replace(',', ', ', $this->changed_columns) : ''));
    }
}
