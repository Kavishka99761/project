<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing columns identified during full-feature audit:
 * - documents.extracted_text      : raw text pulled from PDF/DOCX
 * - users.firebase_token          : FCM device token for push notifications
 * - assignments.submission_*      : submission tracking columns
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // documents — store raw extracted text for summarisation
        // ------------------------------------------------------------------
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'extracted_text')) {
                $table->longText('extracted_text')->nullable()->after('size');
            }
            if (! Schema::hasColumn('documents', 'file_path')) {
                $table->string('file_path', 512)->nullable()->after('size');
            }
            if (! Schema::hasColumn('documents', 'file_type')) {
                $table->string('file_type', 20)->nullable()->after('size');
            }
        });

        // ------------------------------------------------------------------
        // users — FCM token for Firebase push notifications
        // ------------------------------------------------------------------
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'firebase_token')) {
                $table->string('firebase_token', 512)->nullable()->after('dark_mode');
            }
        });

        // ------------------------------------------------------------------
        // assignments — submission tracking
        // ------------------------------------------------------------------
        Schema::table('assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('assignments', 'submission_date')) {
                $table->date('submission_date')->nullable()->after('completed');
            }
            if (! Schema::hasColumn('assignments', 'submission_notes')) {
                $table->text('submission_notes')->nullable()->after('submission_date');
            }
            if (! Schema::hasColumn('assignments', 'submission_file')) {
                $table->string('submission_file', 512)->nullable()->after('submission_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumnIfExists(['extracted_text', 'file_path', 'file_type']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumnIfExists('firebase_token');
        });
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumnIfExists(['submission_date', 'submission_notes', 'submission_file']);
        });
    }
};
