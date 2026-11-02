<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('join_code', 8)->unique();
            $table->unsignedSmallInteger('mastery_threshold')->default(70);
            $table->unsignedSmallInteger('weak_threshold')->default(50);
            $table->unsignedSmallInteger('min_questions_per_concept')->default(2);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index('teacher_id');
        });

        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_thresholds_check CHECK (weak_threshold > 0 AND weak_threshold < mastery_threshold AND mastery_threshold <= 100)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_min_questions_check CHECK (min_questions_per_concept >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
