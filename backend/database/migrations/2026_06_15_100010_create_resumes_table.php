<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            // One resume per user — UNIQUE enforces the 1:1 invariant.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_path'); // S3 key — the uploaded file is kept.
            $table->string('file_mime');
            $table->text('parsed_text')->nullable(); // edited extracted text; reused as analysis input.
            $table->string('language', 8)->default('en');
            $table->timestamp('skills_synced_at')->nullable(); // cache marker; reset when parsed_text changes.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
