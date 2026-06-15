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
        Schema::create('resume_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->string('skill_text');
            $table->timestamps();
        });

        // Voyage voyage-3-large = 1024 dims. Schema builder has no vector type.
        DB::statement('ALTER TABLE resume_skills ADD COLUMN embedding vector(1024)');
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_skills');
    }
};
