<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PASINDU — engagement / concentration reading for a study session.
 */
class EngagementLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id', 'study_session_id', 'level', 'percent', 'source', 'logged_at'];

    protected $casts = ['logged_at' => 'datetime', 'percent' => 'integer'];

    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function studySession(): BelongsTo { return $this->belongsTo(StudySession::class); }
}
