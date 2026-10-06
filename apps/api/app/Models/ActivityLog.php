<?php

namespace App\Models;

use App\Enums\ModuleKey;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail entry — one row per student action or system event.
 */
class ActivityLog extends Model
{
    use BelongsToUser;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'module' => ModuleKey::class,
            'properties' => 'array',
            'created_at' => 'datetime',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
