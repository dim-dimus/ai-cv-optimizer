<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bullet_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->text('original_text');
            $table->text('suggested_text');
            $table->text('rationale')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'edited'])->default('pending');
            $table->text('edited_text')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('analysis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bullet_suggestions');
    }
};
