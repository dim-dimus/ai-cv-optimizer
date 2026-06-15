<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // extract_skills | scoring | bullet_rewrite | cover_letter
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('content'); // template with placeholders, e.g. {{resume_text}}
            $table->string('model');
            $table->integer('max_tokens')->default(1024);
            $table->float('temperature')->default(0.2);
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
