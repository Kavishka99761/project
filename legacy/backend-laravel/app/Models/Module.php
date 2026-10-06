<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Common Platform Layer — a module/subject a student is registered for.
 */
class Module extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'code', 'name', 'color', 'icon'];

    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function documents(): HasMany     { return $this->hasMany(Document::class); }
    public function assignments(): HasMany   { return $this->hasMany(Assignment::class); }
    public function studySessions(): HasMany { return $this->hasMany(StudySession::class); }
}
