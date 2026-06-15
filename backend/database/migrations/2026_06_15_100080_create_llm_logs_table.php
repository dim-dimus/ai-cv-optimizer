<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('provider', ['anthropic', 'voyage']);
            $table->string('model');
            $table->enum('operation', ['extract_skills', 'extract_requirements', 'scoring', 'bullet_rewrite', 'cover_letter', 'embedding']);
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->integer('latency_ms')->nullable();
            $table->enum('status', ['success', 'failed']);
            $table->text('error')->nullable();
            // Minimal payloads — contain personal data, admin-only access.
            $table->jsonb('request_meta')->nullable();
            $table->jsonb('response_meta')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'user_id', 'analysis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
    }
};
