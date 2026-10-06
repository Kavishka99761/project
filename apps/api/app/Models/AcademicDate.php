<?php

namespace App\Models;

use App\Enums\AcademicDateStatus;
use App\Enums\AcademicDateType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * KAVISHKA — an important date found in an academic document (deadline,
 * exam, project milestone or event), with the sentence it came from.
 */
class AcademicDate extends Model
{
    use Auditable, BelongsToUser;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'type' => AcademicDateType::class,
            'status' => AcademicDateStatus::class,
            'date' => 'date:Y-m-d',
            'confidence' => 'float',
            'page_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (AcademicDate $date) {
            DB::table('assignments')->where('academic_date_id', $date->id)->update(['academic_date_id' => null]);
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id')->withTrashed();
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class)->withTrashed();
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }
}
