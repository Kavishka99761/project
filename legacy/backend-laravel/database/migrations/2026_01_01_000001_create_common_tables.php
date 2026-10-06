<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Common Platform Layer — extends users with profile fields and creates the
 * shared `modules` and `notifications` tables used by all four modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('program', 160)->nullable()->after('password');
            $table->string('academic_year', 80)->nullable()->after('program');
            $table->boolean('dark_mode')->default(false)->after('academic_year');
            $table->unsignedInteger('daily_target_minutes')->default(180)->after('dark_mode');
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->string('color', 32)->default('primary');
            $table->string('icon', 48)->default('book');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module_source', 32); // bethmi|pasindu|kavishka|jithmi|common
            $table->string('title', 160);
            $table->string('message', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('modules');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['program', 'academic_year', 'dark_mode', 'daily_target_minutes']);
        });
    }
};
