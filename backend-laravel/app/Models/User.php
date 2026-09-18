<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Common Platform Layer — the authenticated student.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'program',
        'academic_year', 'dark_mode', 'daily_target_minutes', 'firebase_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'             => 'hashed',
        'dark_mode'            => 'boolean',
        'daily_target_minutes' => 'integer',
    ];

    /* ----- Relationships to every module ----- */
    public function modules(): HasMany           { return $this->hasMany(Module::class); }
    public function documents(): HasMany         { return $this->hasMany(Document::class); }         // Bethmi
    public function summaries(): HasMany         { return $this->hasMany(Summary::class); }          // Bethmi
    public function studySessions(): HasMany     { return $this->hasMany(StudySession::class); }     // Pasindu
    public function assignments(): HasMany       { return $this->hasMany(Assignment::class); }       // Jithmi
    public function academicDocuments(): HasMany { return $this->hasMany(AcademicDocument::class); } // Kavishka
    public function academicDates(): HasMany     { return $this->hasMany(AcademicDate::class); }     // Kavishka
    public function conversations(): HasMany     { return $this->hasMany(ChatConversation::class); } // Kavishka
    public function notifications(): HasMany     { return $this->hasMany(Notification::class); }      // Common
}
