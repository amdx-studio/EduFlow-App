<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_concept_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('learning_concepts')->restrictOnDelete();
            $table->unsignedSmallInteger('correct_count');
            $table->unsignedSmallInteger('total_count');
            $table->unsignedInteger('earned_points');
            $table->unsignedInteger('possible_points');
            $table->decimal('mastery_percent', 5, 2);
            $table->string('mastery_level', 15);
            $table->unsignedSmallInteger('mastery_threshold_used');
            $table->unsignedSmallInteger('weak_threshold_used');
            $table->timestamps();

            $table->unique(['attempt_id', 'concept_id']);
            $table->index('concept_id');
        });

        DB::statement("ALTER TABLE attempt_concept_results ADD CONSTRAINT attempt_concept_results_level_check CHECK (mastery_level IN ('mastered', 'developing', 'not_mastered', 'insufficient'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_concept_results');
    }
};
