<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->string('requirement_text');
            $table->enum('category', ['hard_skill', 'soft_skill', 'experience', 'education', 'keyword'])->nullable();
            $table->boolean('is_matched')->default(false);
            // Null for gaps; best-matching resume skill otherwise.
            $table->foreignId('matched_resume_skill_id')->nullable()->constrained('resume_skills')->nullOnDelete();
            $table->float('similarity')->nullable();
            $table->timestamps();

            $table->index('analysis_id');
        });

        DB::statement('ALTER TABLE job_requirements ADD COLUMN embedding vector(1024)');
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requirements');
    }
};
