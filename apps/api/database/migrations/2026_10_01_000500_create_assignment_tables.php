<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JITHMI — Assignments, Workload & Deadline Risk.
 *   assignments               tasks with deadline, priority, workload, progress
 *                             (+ denormalised latest risk for fast ranking)
 *   assignment_progress_logs  every progress change (manual or from study)
 *   risk_assessments          risk history — recalculated on every change
 *   assignment_submissions    submission history (on time vs late)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->unsignedBigInteger('academic_date_id')->nullable();      // FK added in _000600
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 32)->default('coursework');
            $table->dateTime('deadline');
            $table->string('priority', 16)->default('medium');
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->decimal('estimated_hours', 6, 2)->default(1);
            $table->decimal('completed_hours', 6, 2)->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 16)->default('not_started');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->boolean('was_overdue')->default(false);
            $table->dateTime('overdue_notified_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->string('risk_level', 16)->nullable();
            $table->dateTime('risk_updated_at')->nullable();
            $table->unsignedInteger('priority_rank')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'deadline']);
        });

        Schema::create('assignment_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('study_session_id')->nullable();
            $table->unsignedTinyInteger('progress_before');
            $table->unsignedTinyInteger('progress_after');
            $table->decimal('hours_added', 6, 2)->default(0);
            $table->decimal('completed_hours_after', 6, 2)->default(0);
            $table->string('source', 24)->default('manual');   // manual|study_session|completion
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['assignment_id', 'created_at']);
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedTinyInteger('score');
            $table->string('level', 16);
            $table->decimal('probability', 5, 4);
            $table->decimal('remaining_hours', 7, 2);
            $table->decimal('available_hours', 7, 2);
            $table->decimal('required_hours_per_day', 6, 2);
            $table->decimal('available_hours_per_day', 6, 2);
            $table->decimal('days_left', 7, 2);
            $table->decimal('load_ratio', 7, 3);
            $table->json('factors')->nullable();
            $table->json('reasons')->nullable();
            $table->string('trigger', 32)->default('manual');
            $table->dateTime('calculated_at');

            $table->index(['assignment_id', 'calculated_at']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->dateTime('submitted_at');
            $table->dateTime('deadline_at');
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('minutes_late')->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('assignment_progress_logs');
        Schema::dropIfExists('assignments');
    }
};
