<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * An academic module (subject) the student is registered for. Every learning
 * document, study session, knowledge document and assignment can be filed
 * under a module, which powers module filtering across the whole platform.
 */
class Module extends Model
{
    use Auditable, BelongsToUser, HasFactory;

    protected $guarded = ['id', 'user_id'];

    /** Tables that reference modules with a NO ACTION foreign key. */
    private const REFERENCING_TABLES = [
        'documents', 'summaries', 'study_sessions', 'study_plans', 'study_reminders',
        'knowledge_documents', 'assignments', 'calendar_events',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credits' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the student's material when a module is removed — just unfile it.
        static::deleting(function (Module $module) {
            foreach (self::REFERENCING_TABLES as $table) {
                DB::table($table)->where('module_id', $module->id)->update(['module_id' => null]);
            }
        });
    }

    public function documents(): HasMany { return $this->hasMany(Document::class); }
    public function summaries(): HasMany { return $this->hasMany(Summary::class); }
    public function studySessions(): HasMany { return $this->hasMany(StudySession::class); }
    public function knowledgeDocuments(): HasMany { return $this->hasMany(KnowledgeDocument::class); }
    public function assignments(): HasMany { return $this->hasMany(Assignment::class); }
    public function calendarEvents(): HasMany { return $this->hasMany(CalendarEvent::class); }
}
