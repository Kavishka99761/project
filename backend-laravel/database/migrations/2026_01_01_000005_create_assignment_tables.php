<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JITHMI — Assignment & Deadline Risk Management.
 * assignments: tasks with deadline, priority, workload and progress.
 *   - academic_date_id links a deadline extracted by Kavishka (integration).
 * risk_assessments: history of computed risk scores for each assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->foreignId('academic_date_id')->nullable()->constrained('academic_dates')->nullOnDelete();
            $table->string('title', 200);
            $table->date('deadline');
            $table->string('priority', 16)->default('Medium'); // Low|Medium|High
            $table->decimal('est_hours', 5, 1)->default(0);
            $table->decimal('done_hours', 5, 1)->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->index('deadline');
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0); // 0-100
            $table->string('level', 16)->default('Low');      // Low|Medium|High|Critical
            $table->decimal('hours_per_day', 5, 2)->default(0);
            $table->json('reasons')->nullable();
            $table->timestamp('calculated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('assignments');
    }
};
