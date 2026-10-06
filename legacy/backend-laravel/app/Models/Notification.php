<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Common Platform Layer — a cross-module notification surfaced in the app shell.
 */
class Notification extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id', 'module_source', 'title', 'message', 'is_read', 'created_at'];

    protected $casts = ['is_read' => 'boolean', 'created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
