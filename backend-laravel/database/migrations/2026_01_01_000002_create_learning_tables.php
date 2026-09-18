<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BETHMI — Smart Notes & Learning Materials.
 * documents: uploaded lecture notes / PDFs / Word files + extracted text.
 * summaries: generated short / medium / detailed summaries with keywords.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->string('title', 200);
            $table->string('topic', 120)->nullable();
            $table->string('type', 16)->default('PDF'); // PDF|Word|Text
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('file_path', 500)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->timestamps();

            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'topic', 'extracted_text']);
            }
        });

        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('title', 200);
            $table->string('length_type', 16)->default('Medium'); // Short|Medium|Detailed
            $table->longText('body');
            $table->json('keywords')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
        Schema::dropIfExists('documents');
    }
};
