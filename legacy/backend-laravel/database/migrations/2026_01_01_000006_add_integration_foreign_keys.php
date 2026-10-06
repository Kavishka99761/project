<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration foreign keys — added last so all referenced tables exist.
 * study_sessions.assignment_id -> assignments  (Jithmi priority drives a study task)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->foreign('assignment_id')
                  ->references('id')->on('assignments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
        });
    }
};
