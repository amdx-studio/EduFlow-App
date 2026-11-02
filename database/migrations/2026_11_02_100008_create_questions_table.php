<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('learning_concepts')->restrictOnDelete();
            $table->text('body');
            $table->unsignedSmallInteger('points')->default(1);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['quiz_id', 'position']);
            $table->index('concept_id');
        });

        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_points_check CHECK (points > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
