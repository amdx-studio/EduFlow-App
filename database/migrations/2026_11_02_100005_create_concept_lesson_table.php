<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concept_lesson', function (Blueprint $table) {
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('learning_concepts')->cascadeOnDelete();

            $table->primary(['lesson_id', 'concept_id']);
            $table->index('concept_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_lesson');
    }
};
