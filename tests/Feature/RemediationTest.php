<?php

namespace Tests\Feature;

use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use App\Services\RemediationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class RemediationTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-026
    public function test_tc026_failure_in_remediation_rolls_back_the_whole_submit(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $this->signIn($student);

        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $this->answer($id, $q1, true)->assertOk();
        $this->answer($id, $q2, true)->assertOk();

        $this->mock(RemediationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')->once()->andThrow(new RuntimeException('Kegagalan simulasi.'));
        });

        $this->postJson("/api/attempts/{$id}/submit")->assertStatus(500);

        $attempt = QuizAttempt::findOrFail($id);
        $this->assertSame('in_progress', $attempt->status);
        $this->assertNull($attempt->submitted_at);
        $this->assertDatabaseCount('attempt_concept_results', 0);
        $this->assertDatabaseCount('remediation_assignments', 0);
        $this->assertSame(0, $attempt->answers()->where('is_correct', true)->count());
        $this->assertSame(0, (int) $attempt->answers()->sum('points_awarded'));
    }

    // TC-027
    public function test_tc027_recommends_two_ordered_lessons_with_reason_and_no_duplicates(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course, 'Pecahan');
        $quiz = $this->quiz($course);
        $q = [];
        foreach ([1, 2, 3, 4] as $position) {
            $q[$position] = $this->question($quiz, $concept, $position);
        }

        $this->remedialLesson($course, $concept, 'Remedial C', 3);
        $first = $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $second = $this->remedialLesson($course, $concept, 'Remedial B', 2);
        $this->remedialLesson($course, $concept, 'Draf remedial', 0, false);
        $this->remedialLesson($course, $concept, 'Materi reguler', 0, true, 'regular');

        $attempt = $this->submitViaService($student, $quiz, [
            [$q[1], true], [$q[2], true], [$q[3], false], [$q[4], false],
        ]);

        $assignments = RemediationAssignment::where('attempt_id', $attempt->id)->orderBy('id')->get();

        $this->assertCount(2, $assignments);
        $this->assertEquals([$first->id, $second->id], $assignments->pluck('lesson_id')->all());

        foreach ($assignments as $assignment) {
            $this->assertSame('rule', $assignment->source);
            $this->assertSame('assigned', $assignment->status);
            $this->assertEquals($student->id, $assignment->student_id);
            $this->assertEquals($concept->id, $assignment->concept_id);
            $this->assertNull($assignment->completed_at);
        }

        $this->assertSame(
            'Penguasaan konsep "Pecahan" 50% (2/4 soal benar), di bawah ambang 70%. Materi "Remedial A" ditautkan ke konsep ini.',
            $assignments[0]->reason
        );
        $this->assertSame(
            'Penguasaan konsep "Pecahan" 50% (2/4 soal benar), di bawah ambang 70%. Materi "Remedial B" ditautkan ke konsep ini.',
            $assignments[1]->reason
        );

        // Panggil ulang generator: tanpa duplikat.
        app(RemediationService::class)->generate($attempt);
        $this->assertDatabaseCount('remediation_assignments', 2);

        $this->signIn($student);

        $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonCount(2, 'data.remediation')
            ->assertJsonPath('data.remediation.0.lesson.title', 'Remedial A')
            ->assertJsonPath('data.remediation.1.lesson.title', 'Remedial B')
            ->assertJsonPath('data.remediation.0.source', 'rule')
            ->assertJsonPath('data.remediation.0.status', 'assigned')
            ->assertJsonPath('data.remediation_gaps', []);
    }

    // TC-028
    public function test_tc028_weak_concept_without_material_gets_no_assignment_and_is_a_gap(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $pecahan = $this->concept($course, 'Pecahan');
        $desimal = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $p1 = $this->question($quiz, $pecahan, 1);
        $p2 = $this->question($quiz, $pecahan, 2);
        $d1 = $this->question($quiz, $desimal, 3);
        $d2 = $this->question($quiz, $desimal, 4);

        $lesson = $this->remedialLesson($course, $desimal, 'Remedial Desimal', 1);

        $attempt = $this->submitViaService($student, $quiz, [
            [$p1, false], [$p2, false], [$d1, true], [$d2, false],
        ]);

        $this->assertDatabaseCount('remediation_assignments', 1);
        $this->assertDatabaseHas('remediation_assignments', [
            'attempt_id' => $attempt->id,
            'concept_id' => $desimal->id,
            'lesson_id' => $lesson->id,
        ]);

        $this->signIn($student);

        $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonCount(1, 'data.remediation')
            ->assertJsonPath('data.remediation.0.concept', 'Desimal')
            ->assertJsonCount(1, 'data.remediation_gaps')
            ->assertJsonPath('data.remediation_gaps.0.concept', 'Pecahan')
            ->assertJsonPath('data.remediation_gaps.0.concept_id', $pecahan->id);
    }

    public function test_not_mastered_is_recommended_before_developing_and_mastered_is_skipped(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $aljabar = $this->concept($course, 'Aljabar');
        $pecahan = $this->concept($course, 'Pecahan');
        $geometri = $this->concept($course, 'Geometri');
        $quiz = $this->quiz($course);
        $a1 = $this->question($quiz, $aljabar, 1);
        $a2 = $this->question($quiz, $aljabar, 2);
        $p1 = $this->question($quiz, $pecahan, 3);
        $p2 = $this->question($quiz, $pecahan, 4);
        $g1 = $this->question($quiz, $geometri, 5);
        $g2 = $this->question($quiz, $geometri, 6);

        $this->remedialLesson($course, $aljabar, 'Remedial Aljabar', 1);
        $this->remedialLesson($course, $pecahan, 'Remedial Pecahan', 1);
        $this->remedialLesson($course, $geometri, 'Remedial Geometri', 1);

        $attempt = $this->submitViaService($student, $quiz, [
            [$a1, true], [$a2, false],
            [$p1, false], [$p2, false],
            [$g1, true], [$g2, true],
        ]);

        $order = RemediationAssignment::where('attempt_id', $attempt->id)->orderBy('id')->pluck('concept_id')->all();

        $this->assertEquals([$pecahan->id, $aljabar->id], $order);

        $this->signIn($student);

        $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonPath('data.remediation.0.concept', 'Pecahan')
            ->assertJsonPath('data.remediation.1.concept', 'Aljabar')
            ->assertJsonPath('data.remediation_gaps', []);
    }
}
