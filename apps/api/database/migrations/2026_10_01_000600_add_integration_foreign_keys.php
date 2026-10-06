<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-module integration links, added once every referenced table exists.
 *
 *   Kavishka → Jithmi   assignments.academic_date_id   (deadline detected in a document)
 *   Kavishka → Calendar academic_dates.calendar_event_id
 *   Jithmi  → Pasindu   study_sessions.assignment_id  (study the priority task)
 *   Jithmi  → Pasindu   study_plans.assignment_id
 *
 * All are NO ACTION: the owning models clear these references before delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->foreign('assignment_id')->references('id')->on('assignments');
        });

        Schema::table('study_plans', function (Blueprint $table) {
            $table->foreign('assignment_id')->references('id')->on('assignments');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreign('academic_date_id')->references('id')->on('academic_dates');
        });

        Schema::table('academic_dates', function (Blueprint $table) {
            $table->foreign('calendar_event_id')->references('id')->on('calendar_events');
        });
    }

    public function down(): void
    {
        Schema::table('academic_dates', fn (Blueprint $table) => $table->dropForeign(['calendar_event_id']));
        Schema::table('assignments', fn (Blueprint $table) => $table->dropForeign(['academic_date_id']));
        Schema::table('study_plans', fn (Blueprint $table) => $table->dropForeign(['assignment_id']));
        Schema::table('study_sessions', fn (Blueprint $table) => $table->dropForeign(['assignment_id']));
    }
};
