<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KAVISHKA — AI Academic Assistant.
 *   knowledge_documents  handbooks, project guidelines, regulations, module docs
 *   knowledge_chunks     indexed passages (section + page + BM25 term vector)
 *   chat_conversations   chatbot threads (optionally scoped to documents)
 *   chat_messages        questions and grounded answers with source citations
 *   academic_dates       deadlines / exams / milestones / events extracted from
 *                        documents, reviewed and pushed to the calendar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->string('category', 32)->default('handbook');
            $table->string('title', 200);
            $table->string('description', 1000)->nullable();
            $table->string('original_name')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->string('status', 16)->default('pending');
            $table->string('error', 500)->nullable();
            $table->longText('content')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'category']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('chunk_index');
            $table->string('section', 255)->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->longText('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->json('terms')->nullable();          // stemmed term => frequency (BM25)
            $table->json('keywords')->nullable();
            $table->timestamps();

            $table->index(['knowledge_document_id', 'chunk_index']);
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->json('scope')->nullable();          // {"categories":[], "document_ids":[]}
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('role', 16);                 // user|assistant
            $table->longText('content');
            $table->json('sources')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('suggestions')->nullable();
            $table->string('feedback', 16)->nullable(); // helpful|not_helpful
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['chat_conversation_id', 'created_at']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('academic_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents');
            $table->unsignedBigInteger('knowledge_chunk_id')->nullable();
            $table->unsignedBigInteger('calendar_event_id')->nullable();     // FK added in _000600
            $table->string('title', 200);
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('type', 32)->default('other');
            $table->decimal('confidence', 4, 3)->default(0.5);
            $table->string('context', 1000)->nullable();
            $table->string('matched_text', 120)->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_dates');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
    }
};
