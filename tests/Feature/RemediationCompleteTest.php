<?php

namespace Tests\Feature;

use App\Models\RemediationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class RemediationCompleteTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-029
    public function test_tc029_completing_a_lesson_completes_only_own_assignments_for_that_lesson(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $other = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $this->enroll($course, $other);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $lessonA = $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $lessonB = $this->remedialLesson($course, $concept, 'Remedial B', 2);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);
        $this->submitViaService($other, $quiz, [[$q1, false], [$q2, false]]);

        $this->assertSame(4, RemediationAssignment::where('status', 'assigned')->count());

        $this->signIn($student);

        $this->postJson("/api/lessons/{$lessonA->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.assignments_completed', 1);

        $mine = RemediationAssignment::where('student_id', $student->id)->where('lesson_id', $lessonA->id)->firstOrFail();
        $this->assertSame('completed', $mine->status);
        $this->assertNotNull($mine->completed_at);
        $completedAt = $mine->completed_at->toIso8601String();

        $this->assertSame('assigned', RemediationAssignment::where('student_id', $student->id)->where('lesson_id', $lessonB->id)->value('status'));
        $this->assertSame('assigned', RemediationAssignment::where('student_id', $other->id)->where('lesson_id', $lessonA->id)->value('status'));
        $this->assertSame('assigned', RemediationAssignment::where('student_id', $other->id)->where('lesson_id', $lessonB->id)->value('status'));

        // Idempoten: panggilan kedua tidak mengubah apa pun dan tidak menimpa completed_at.
        $this->postJson("/api/lessons/{$lessonA->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.assignments_completed', 0);

        $again = RemediationAssignment::findOrFail($mine->id);
        $this->assertSame($completedAt, $again->completed_at->toIso8601String());
        $this->assertDatabaseCount('lesson_progress', 1);

        $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonPath('data.remediation.0.status', 'completed')
            ->assertJsonPath('data.remediation.1.status', 'assigned');
    }

    public function test_completing_a_lesson_completes_assignments_from_all_own_attempts(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $lesson = $this->remedialLesson($course, $concept, 'Remedial A', 1);

        $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);
        $this->submitViaService($student, $quiz, [[$q1, true], [$q2, false]]);

        $this->assertSame(2, RemediationAssignment::where('status', 'assigned')->count());

        $this->signIn($student);

        $this->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.assignments_completed', 2);

        $this->assertSame(0, RemediationAssignment::where('status', 'assigned')->count());
        $this->assertSame(2, RemediationAssignment::where('status', 'completed')->whereNotNull('completed_at')->count());
    }
}
