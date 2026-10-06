<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PASINDU — a study/focus session. Links optionally to a Jithmi assignment
 * (recommended task) and a Bethmi document (material being studied).
 */
class StudySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'module_id', 'document_id', 'assignment_id', 'activity',
        'planned_minutes', 'actual_minutes', 'status', 'started_at', 'ended_at',
    ];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function user(): BelongsTo            { return $this->belongsTo(User::class); }
    public function module(): BelongsTo          { return $this->belongsTo(Module::class); }
    public function document(): BelongsTo        { return $this->belongsTo(Document::class); }
    public function assignment(): BelongsTo      { return $this->belongsTo(Assignment::class); }
    public function engagementLogs(): HasMany    { return $this->hasMany(EngagementLog::class); }
}
