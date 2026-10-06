<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PASINDU — a recurring study-session reminder ("Weekdays at 19:00").
 */
class StudyReminder extends Model
{
    use Auditable, BelongsToUser;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
