<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Common Platform Layer — the authenticated student.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'program', 'academic_year',
        'phone', 'bio', 'avatar_path', 'dark_mode', 'daily_target_minutes',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['avatar_url'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'             => 'hashed',
        'dark_mode'            => 'boolean',
        'daily_target_minutes' => 'integer',
    ];

    /** Public URL for the stored avatar, or null when none has been uploaded. */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null
        );
    }

    /* ----- Relationships to every module ----- */
    public function modules(): HasMany           { return $this->hasMany(Module::class); }
    public function documents(): HasMany         { return $this->hasMany(Document::class); }         // Bethmi
    public function summaries(): HasMany         { return $this->hasMany(Summary::class); }          // Bethmi
    public function studySessions(): HasMany     { return $this->hasMany(StudySession::class); }     // Pasindu
    public function assignments(): HasMany       { return $this->hasMany(Assignment::class); }       // Jithmi
    public function academicDocuments(): HasMany { return $this->hasMany(AcademicDocument::class); } // Kavishka
    public function academicDates(): HasMany     { return $this->hasMany(AcademicDate::class); }     // Kavishka
    public function conversations(): HasMany     { return $this->hasMany(ChatConversation::class); } // Kavishka
}
