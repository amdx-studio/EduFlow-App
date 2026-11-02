<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remediation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('learning_concepts')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();
            $table->string('source', 7);
            $table->text('reason');
            $table->string('status', 10)->default('assigned');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'concept_id', 'lesson_id']);
            $table->index(['student_id', 'status']);
        });

        DB::statement("ALTER TABLE remediation_assignments ADD CONSTRAINT remediation_assignments_source_check CHECK (source IN ('rule', 'teacher'))");
        DB::statement("ALTER TABLE remediation_assignments ADD CONSTRAINT remediation_assignments_status_check CHECK (status IN ('assigned', 'completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('remediation_assignments');
    }
};
