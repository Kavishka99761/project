<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BETHMI — Smart Learning Materials.
 *   documents   uploaded lecture notes / PDF / Word / slides + extracted text
 *   summaries   saved short / medium / detailed summaries with keywords,
 *               key concepts, highlighted sentences and revision bullets
 *   study_aids  generated flashcards, quizzes and mind maps
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->string('title', 200);
            $table->string('topic', 120)->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('kind', 16)->default('pdf');
            $table->string('original_name')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('reading_minutes')->default(0);
            $table->longText('content')->nullable();
            $table->string('extraction_status', 16)->default('pending');
            $table->string('extraction_error', 500)->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->json('keywords')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'module_id']);
            $table->index(['user_id', 'topic']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents');
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->string('title', 200);
            $table->string('length', 16)->default('medium');
            $table->string('method', 24)->default('textrank');
            $table->longText('content');
            $table->json('bullet_points')->nullable();
            $table->json('keywords')->nullable();
            $table->json('key_concepts')->nullable();
            $table->json('highlights')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('source_word_count')->default(0);
            $table->unsignedInteger('sentence_count')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'document_id']);
        });

        Schema::create('study_aids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents');
            $table->string('type', 16);
            $table->string('title', 200);
            $table->json('content');
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_aids');
        Schema::dropIfExists('summaries');
        Schema::dropIfExists('documents');
    }
};
