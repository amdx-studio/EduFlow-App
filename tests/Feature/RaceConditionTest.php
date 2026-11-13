<?php

namespace Tests\Feature;

use App\Models\LessonProgress;
use App\Models\RemediationAssignment;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAttemptData;
use Tests\Concerns\TruncatesAfterTest;
use Tests\TestCase;

/**
 * UT-07: kondisi hasil balapan, disimulasikan secara deterministik.
 * Koneksi kedua meng-commit baris pesaing tepat sebelum INSERT request utama.
 * Bukan dua request paralel sungguhan; itu tidak dapat dijalankan PHPUnit.
 */
class RaceConditionTest extends TestCase
{
    use BuildsAttemptData;
    use DatabaseTruncation;
    use TruncatesAfterTest;

    private bool $rivalFired = false;

    public function test_ut07_complete_survives_a_competing_insert(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $lesson = $this->remedialLesson($course, $concept, 'Materi', 1);

        $this->signIn($student);

        $this->competeBefore('lesson_progress', fn ($rival) => $rival->table('lesson_progress')->insert([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'completed_at' => now(),
        ]));

        $this->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.assignments_completed', 0);

        $this->assertTrue($this->rivalFired, 'Pesaing tidak pernah dijalankan: tes ini tidak menguji balapan.');
        $this->assertSame(1, LessonProgress::count());
    }

    public function test_ut07_manual_remedial_survives_a_competing_insert(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);
        $lesson = $this->remedialLesson($course, $concept, 'Remedial manual', 1);

        $this->signIn($teacher);

        $this->competeBefore('remediation_assignments', fn () => RemediationAssignment::on('pgsql_rival')->create([
            'attempt_id' => $attempt->id,
            'student_id' => $student->id,
            'concept_id' => $concept->id,
            'lesson_id' => $lesson->id,
            'source' => 'teacher',
            'reason' => 'Pesaing.',
            'status' => 'assigned',
            'assigned_by' => $teacher->id,
            'assigned_at' => now(),
        ]));

        $this->postJson("/api/attempts/{$attempt->id}/remediation", [
            'concept_id' => $concept->id,
            'lesson_id' => $lesson->id,
        ])->assertStatus(422);

        $this->assertTrue($this->rivalFired, 'Pesaing tidak pernah dijalankan: tes ini tidak menguji balapan.');
        $this->assertSame(1, RemediationAssignment::count());
    }

    /** Jalankan $competitor (lewat koneksi kedua) sekali, tepat sebelum INSERT pertama ke $table. */
    private function competeBefore(string $table, callable $competitor): void
    {
        config(['database.connections.pgsql_rival' => config('database.connections.pgsql')]);

        $done = false;

        DB::connection()->beforeExecuting(function (string $query) use (&$done, $table, $competitor) {
            if ($done || ! str_starts_with(strtolower(ltrim($query)), 'insert into "'.$table.'"')) {
                return;
            }

            $done = true;
            $this->rivalFired = true;
            $competitor(DB::connection('pgsql_rival'));
        });
    }
}
