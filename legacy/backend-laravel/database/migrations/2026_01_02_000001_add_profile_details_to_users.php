<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Common Platform Layer — richer profile fields used by the Profile page:
 * a contact number, a short bio, and a stored avatar path (served from the
 * `public` disk as `avatar_url`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('academic_year');
            $table->string('bio', 500)->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'bio', 'avatar_path']);
        });
    }
};
