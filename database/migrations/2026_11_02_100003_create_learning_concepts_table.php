<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_concepts');
    }
};
