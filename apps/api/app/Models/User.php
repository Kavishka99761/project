<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Common Platform Layer — the authenticated student and owner of every
 * record across the four feature modules.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name', 'email', 'password', 'student_id', 'university', 'program',
        'academic_year', 'phone', 'bio', 'avatar_path', 'last_login_at', 'last_login_ip',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @var list<string> */
    protected $appends = ['avatar_url', 'initials'];

    /** @var list<string> */
    public array $auditExclude = ['last_login_at', 'last_login_ip'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null);
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
            $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

            return implode('', $letters) ?: 'S';
        });
    }

    /** Settings row, created lazily with defaults on first access. */
    public function settingsOrDefault(): UserSetting
    {
        return $this->settings ?? $this->settings()->create(UserSetting::defaults());
    }

    /* ---------------- Common Platform Layer ---------------- */
    public function settings(): HasOne { return $this->hasOne(UserSetting::class); }
    public function modules(): HasMany { return $this->hasMany(Module::class); }
    public function userNotifications(): HasMany { return $this->hasMany(UserNotification::class); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }
    public function searchHistory(): HasMany { return $this->hasMany(SearchHistory::class); }
    public function exportLogs(): HasMany { return $this->hasMany(ExportLog::class); }
    public function calendarEvents(): HasMany { return $this->hasMany(CalendarEvent::class); }

    /* ---------------- Bethmi · Learning Materials ---------------- */
    public function documents(): HasMany { return $this->hasMany(Document::class); }
    public function summaries(): HasMany { return $this->hasMany(Summary::class); }
    public function studyAids(): HasMany { return $this->hasMany(StudyAid::class); }

    /* ---------------- Pasindu · Study & Engagement ---------------- */
    public function studySessions(): HasMany { return $this->hasMany(StudySession::class); }
    public function engagementLogs(): HasMany { return $this->hasMany(EngagementLog::class); }
    public function studyPlans(): HasMany { return $this->hasMany(StudyPlan::class); }
    public function studyReminders(): HasMany { return $this->hasMany(StudyReminder::class); }

    /* ---------------- Kavishka · Academic Assistant ---------------- */
    public function knowledgeDocuments(): HasMany { return $this->hasMany(KnowledgeDocument::class); }
    public function knowledgeChunks(): HasMany { return $this->hasMany(KnowledgeChunk::class); }
    public function conversations(): HasMany { return $this->hasMany(ChatConversation::class); }
    public function chatMessages(): HasMany { return $this->hasMany(ChatMessage::class); }
    public function academicDates(): HasMany { return $this->hasMany(AcademicDate::class); }

    /* ---------------- Jithmi · Assignments & Risk ---------------- */
    public function assignments(): HasMany { return $this->hasMany(Assignment::class); }
    public function riskAssessments(): HasMany { return $this->hasMany(RiskAssessment::class); }
}
