<?php

namespace App\Models;

use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * In-app notification (mirrored to Firestore for realtime delivery).
 */
class UserNotification extends Model
{
    use BelongsToUser;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'module' => ModuleKey::class,
            'type' => NotificationType::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
