<?php

namespace App\Models;

use App\Enums\EventType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Unified academic calendar entry (manual or created from an extracted date).
 * reminder_at is derived from starts_at - reminder_minutes on every save.
 */
class CalendarEvent extends Model
{
    use Auditable, BelongsToUser, SoftDeletes;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'reminder_minutes' => 'integer',
            'reminder_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CalendarEvent $event) {
            if ($event->isDirty(['starts_at', 'reminder_minutes'])) {
                $event->reminder_at = $event->reminder_minutes !== null && $event->starts_at
                    ? $event->starts_at->copy()->subMinutes($event->reminder_minutes)
                    : null;
                $event->reminder_sent_at = null;
            }
        });

        static::forceDeleting(function (CalendarEvent $event) {
            DB::table('academic_dates')->where('calendar_event_id', $event->id)
                ->update(['calendar_event_id' => null, 'status' => 'pending']);
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function academicDate(): HasOne
    {
        return $this->hasOne(AcademicDate::class);
    }
}
