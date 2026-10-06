<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ownership for every student-owned record.
 *
 * Route-model binding is scoped to the authenticated user, so a request for
 * another student's record resolves to "404 Not Found" — the API never even
 * confirms that the record exists.
 *
 * @mixin Model
 */
trait BelongsToUser
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where($this->qualifyColumn('user_id'), $user instanceof User ? $user->getKey() : $user);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->scopedBindingQuery($this->newQuery(), $value, $field)->first();
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null): ?Model
    {
        return $this->scopedBindingQuery($this->newQuery()->withTrashed(), $value, $field)->first();
    }

    private function scopedBindingQuery(Builder $query, mixed $value, ?string $field): Builder
    {
        $query->where($field ?? $this->getRouteKeyName(), $value);

        if ($userId = auth()->id()) {
            $query->where($this->qualifyColumn('user_id'), $userId);
        }

        return $query;
    }
}
