<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PASINDU — Study Session & Engagement.
 * study_sessions: focus sessions with planned vs actual minutes.
 *   - document_id  (FK) integration with Bethmi's material
 *   - assignment_id (FK added later) integration with Jithmi's recommended task
 * engagement_logs: auto-detected or manually reported concentration levels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->unsignedBigInteger('assignment_id')->nullable(); // FK added in _000006
            $table->string('activity', 80)->default('Revision');
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('actual_minutes')->default(0);
            $table->string('status', 16)->default('active'); // active|paused|completed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('engagement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_session_id')->nullable()->constrained('study_sessions')->nullOnDelete();
            $table->string('level', 16)->default('Good'); // Low|Moderate|Good
            $table->unsignedTinyInteger('percent')->default(0);
            $table->string('source', 16)->default('auto'); // auto|manual
            $table->timestamp('logged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_logs');
        Schema::dropIfExists('study_sessions');
    }
};
