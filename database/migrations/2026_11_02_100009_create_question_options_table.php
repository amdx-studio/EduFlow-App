<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX question_options_one_correct ON question_options (question_id) WHERE is_correct');
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
