<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_en01_schema_test_terisolasi_dan_semua_tabel_termigrasi(): void
    {
        $this->assertSame('eduflow_test', DB::selectOne('select current_schema() as s')->s);

        $ada = collect(DB::select('select table_name from information_schema.tables where table_schema = ?', ['eduflow_test']))
            ->pluck('table_name');

        $harus = [
            'users', 'sessions', 'courses', 'course_enrollments', 'learning_concepts', 'lessons',
            'concept_lesson', 'lesson_progress', 'quizzes', 'questions', 'question_options',
            'quiz_attempts', 'quiz_answers', 'attempt_concept_results', 'remediation_assignments',
        ];

        foreach ($harus as $tabel) {
            $this->assertTrue($ada->contains($tabel), "Tabel {$tabel} tidak ada di eduflow_test");
        }
    }
}