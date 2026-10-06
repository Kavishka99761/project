<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PASINDU — Study Sessions & Engagement.
 *   study_sessions        timer sessions (planned vs actual focus time)
 *   study_session_events  every timer transition (start/pause/resume/break/stop)
 *   engagement_logs       automatic engagement samples + manual concentration
 *   study_plans           planned study time per day (planned vs actual)
 *   study_reminders       recurring study-session reminders
 *
 * Child tables of study_sessions carry a plain (un-constrained) user_id copy:
 * a second cascading FK to users would be a second cascade path, which SQL
 * Server does not allow. Integrity flows through study_session_id instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->foreignId('document_id')->nullable()->constrained('documents');
            $table->unsignedBigInteger('assignment_id')->nullable();          // FK added in _000600
            $table->string('activity', 32)->default('revision');
            $table->string('goal', 255)->nullable();
            $table->unsignedInteger('planned_minutes')->default(25);
            $table->string('status', 16)->default('active');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->dateTime('last_resumed_at')->nullable();
            $table->dateTime('paused_at')->nullable();
            $table->unsignedInteger('focus_seconds')->default(0);
            $table->unsignedInteger('break_seconds')->default(0);
            $table->unsignedInteger('pause_count')->default(0);
            $table->unsignedInteger('break_count')->default(0);
            $table->unsignedInteger('actual_minutes')->default(0);
            $table->unsignedTinyInteger('avg_engagement')->nullable();
            $table->unsignedTinyInteger('focus_score')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('mood', 16)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->json('feedback')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('study_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 32);
            $table->json('payload')->nullable();
            $table->dateTime('occurred_at');
        });

        Schema::create('engagement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('score');
            $table->string('level', 16);
            $table->string('source', 16)->default('auto');                  // auto|manual
            $table->unsignedTinyInteger('concentration')->nullable();       // 1–5 when manual
            $table->json('signals')->nullable();
            $table->string('note', 255)->nullable();
            $table->dateTime('logged_at');

            $table->index(['user_id', 'logged_at']);
            $table->index(['study_session_id', 'logged_at']);
        });

        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->date('plan_date');
            $table->unsignedInteger('planned_minutes');
            $table->string('title', 200)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'plan_date']);
        });

        Schema::create('study_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->string('title', 160);
            $table->string('message', 500)->nullable();
            $table->time('remind_time');
            $table->json('days');                                           // ["mon","tue",...]
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_reminders');
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('engagement_logs');
        Schema::dropIfExists('study_session_events');
        Schema::dropIfExists('study_sessions');
    }
};
