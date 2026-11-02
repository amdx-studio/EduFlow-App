<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('content');
            $table->string('kind', 10)->default('regular');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['course_id', 'kind']);
        });

        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_kind_check CHECK (kind IN ('regular', 'remedial'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
