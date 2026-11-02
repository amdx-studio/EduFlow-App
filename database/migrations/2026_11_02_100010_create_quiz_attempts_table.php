<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 12)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('earned_points')->nullable();
            $table->unsignedInteger('possible_points')->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id', 'attempt_no']);
            $table->index(['student_id', 'quiz_id']);
        });

        DB::statement("ALTER TABLE quiz_attempts ADD CONSTRAINT quiz_attempts_status_check CHECK (status IN ('in_progress', 'submitted'))");
        DB::statement("CREATE UNIQUE INDEX quiz_attempts_one_active ON quiz_attempts (quiz_id, student_id) WHERE status = 'in_progress'");
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
