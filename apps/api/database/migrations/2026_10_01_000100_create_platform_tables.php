<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Common Platform Layer — shared services used by all four feature modules:
 * preferences, academic modules (subjects), notifications, the audit trail of
 * every action, search history, export history and the unified calendar.
 *
 * SQL Server note: only `user_id` foreign keys cascade. Secondary references
 * (e.g. module_id) use NO ACTION because SQL Server rejects multiple cascade
 * paths; the models null those references before a parent row is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Appearance
            $table->string('theme', 16)->default('system');           // light|dark|system
            $table->string('accent_color', 16)->default('#6366f1');
            $table->boolean('glass_effects')->default(true);
            $table->boolean('reduce_motion')->default(false);
            $table->boolean('compact_mode')->default(false);
            // Goals & study rhythm
            $table->unsignedInteger('daily_goal_minutes')->default(180);
            $table->unsignedInteger('weekly_goal_minutes')->default(900);
            $table->unsignedSmallInteger('focus_minutes')->default(25);
            $table->unsignedSmallInteger('short_break_minutes')->default(5);
            $table->unsignedSmallInteger('long_break_minutes')->default(15);
            $table->unsignedSmallInteger('sessions_before_long_break')->default(4);
            $table->json('availability')->nullable();                   // hours per weekday
            // Learning
            $table->boolean('auto_summarize')->default(true);
            $table->string('default_summary_length', 16)->default('medium');
            // Notifications
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_browser')->default(true);
            $table->boolean('notify_study_reminders')->default(true);
            $table->boolean('notify_deadlines')->default(true);
            $table->boolean('notify_engagement')->default(true);
            $table->unsignedInteger('default_reminder_minutes')->default(1440);
            $table->timestamps();
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->string('color', 16)->default('#2a78d6');
            $table->string('icon', 48)->default('journal-bookmark');
            $table->unsignedTinyInteger('credits')->nullable();
            $table->string('semester', 40)->nullable();
            $table->string('lecturer', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'code']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 24)->default('platform');
            $table->string('type', 24)->default('info');
            $table->string('title', 160);
            $table->string('message', 1000)->nullable();
            $table->string('icon', 48)->nullable();
            $table->string('action_url', 255)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Audit trail — one row per user action (API request or system event).
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('module', 24)->default('platform');
            $table->string('action', 80);
            $table->string('description', 500)->nullable();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('method', 8)->nullable();
            $table->string('route', 160)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'created_at']);
            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('search_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 255);
            $table->string('scope', 32)->default('all');
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dataset', 48);
            $table->string('format', 8);
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('file_name', 200);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->string('source', 24)->default('manual');            // manual|academic_date
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 24)->default('event');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location', 200)->nullable();
            $table->string('color', 16)->nullable();
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->dateTime('reminder_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'starts_at']);
            $table->index(['reminder_at', 'reminder_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('search_history');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('user_settings');
    }
};
