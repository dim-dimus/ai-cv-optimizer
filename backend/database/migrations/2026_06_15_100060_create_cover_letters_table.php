<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cover_letters', function (Blueprint $table) {
            $table->id();
            // One cover letter per analysis.
            $table->foreignId('analysis_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('tone')->nullable();
            $table->string('length')->nullable();
            $table->string('language', 8)->default('en');
            $table->text('content')->nullable(); // regeneration overwrites; no history.
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_letters');
    }
};
