<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KAVISHKA — AI Academic Assistant.
 * academic_documents + academic_chunks: indexed institutional documents for RAG.
 * chat_conversations + chat_messages: the academic chatbot history.
 * academic_dates: deadlines / exams / milestones extracted from documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32)->default('Handbook'); // Handbook|Project|Regulation
            $table->string('title', 200);
            $table->unsignedInteger('pages')->default(0);
            $table->string('file_path', 500)->nullable();
            $table->boolean('is_indexed')->default(false);
            $table->timestamps();
        });

        Schema::create('academic_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_document_id')->constrained('academic_documents')->cascadeOnDelete();
            $table->string('section', 200)->nullable();
            $table->unsignedInteger('page')->default(0);
            $table->longText('content');
            $table->json('keywords')->nullable();

            $table->fullText(['content', 'section']);
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('role', 16)->default('user'); // user|bot
            $table->text('content');
            $table->foreignId('source_chunk_id')->nullable()->constrained('academic_chunks')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('academic_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_document_id')->nullable()->constrained('academic_documents')->nullOnDelete();
            $table->string('title', 200);
            $table->date('event_date');
            $table->string('type', 24)->default('Milestone'); // Deadline|Milestone|Exam|Event
            $table->string('reminder', 48)->default('1 day before');
            $table->timestamps();

            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_dates');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('academic_chunks');
        Schema::dropIfExists('academic_documents');
    }
};
