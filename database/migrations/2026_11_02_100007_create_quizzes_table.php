<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('kind', 12)->default('assessment');
            $table->foreignId('base_quiz_id')->nullable()->constrained('quizzes')->restrictOnDelete();
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->string('status', 10)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'status']);
        });

        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_kind_check CHECK (kind IN ('assessment', 'reassessment'))");
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_status_check CHECK (status IN ('draft', 'published'))");
        DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_max_attempts_check CHECK (max_attempts >= 1)');
        DB::statement("ALTER TABLE quizzes ADD CONSTRAINT quizzes_base_check CHECK ((kind = 'reassessment') = (base_quiz_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
