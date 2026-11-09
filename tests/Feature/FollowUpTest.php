<?php

namespace Tests\Feature;

use App\Models\RemediationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-034
    public function test_tc034_teacher_adds_manual_remedial_and_system_ones_stay(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $other = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $lessonB = $this->remedialLesson($course, $other, 'Remedial B', 2);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);

        $this->signIn($teacher);

        $this->postJson("/api/attempts/{$attempt->id}/remediation", [
            'concept_id' => $concept->id,
            'lesson_id' => $lessonB->id,
            'note' => 'Fokus latihan soal',
        ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'teacher')
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.note', 'Fokus latihan soal')
            ->assertJsonPath('data.reason', 'Ditambahkan oleh guru: '.$teacher->name)
            ->assertJsonPath('data.lesson.title', 'Remedial B');

        $this->assertSame(1, RemediationAssignment::where('source', 'rule')->count());
        $this->assertSame(1, RemediationAssignment::where('source', 'teacher')->whereNotNull('assigned_by')->count());

        $this->signIn($student);

        $items = $this->getJson("/api/courses/{$course->id}/remediation")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $this->assertEqualsCanonicalizing(['rule', 'teacher'], array_column($items, 'source'));
    }

    public function test_manual_remedial_validation_rules(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $other = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $systemLesson = $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $regular = $this->remedialLesson($course, $other, 'Reguler', 2, true, 'regular');
        $draft = $this->remedialLesson($course, $other, 'Draf', 3, false);

        $course2 = $this->course($teacher, ['title' => 'IPA']);
        $foreignConcept = $this->concept($course2, 'Gaya');
        $foreign = $this->remedialLesson($course2, $foreignConcept, 'Remedial IPA', 1);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);
        $url = "/api/attempts/{$attempt->id}/remediation";

        $this->signIn($teacher);

        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['concept_id', 'lesson_id']);
        $this->postJson($url, ['concept_id' => $concept->id, 'lesson_id' => $systemLesson->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['lesson_id']);
        $this->postJson($url, ['concept_id' => $concept->id, 'lesson_id' => $regular->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['lesson_id']);
        $this->postJson($url, ['concept_id' => $concept->id, 'lesson_id' => $draft->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['lesson_id']);
        $this->postJson($url, ['concept_id' => $concept->id, 'lesson_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['lesson_id']);
        $this->postJson($url, ['concept_id' => $other->id, 'lesson_id' => $systemLesson->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['concept_id']);

        $valid = $this->remedialLesson($course, $other, 'Remedial B', 4);
        $this->postJson($url, ['concept_id' => $concept->id, 'lesson_id' => $valid->id, 'note' => str_repeat('a', 1001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['note']);

        $this->assertSame(0, RemediationAssignment::where('source', 'teacher')->count());
    }

    public function test_manual_remedial_access_rules(): void
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
        $attempt = $this->submitViaService($student, $quiz, [[$q1, true], [$q2, true]]);
        $payload = ['concept_id' => $concept->id, 'lesson_id' => $lesson->id];
        $url = "/api/attempts/{$attempt->id}/remediation";

        $this->postJson($url, $payload)->assertUnauthorized();

        $this->signIn($student);
        $this->postJson($url, $payload)->assertForbidden();

        $this->signIn($this->teacher());
        $this->postJson($url, $payload)->assertNotFound();

        $quiz2 = $this->quiz($course, ['title' => 'Kuis 2']);
        $this->question($quiz2, $concept, 1);
        $this->signIn($student);
        $inProgressId = $this->postJson("/api/quizzes/{$quiz2->id}/attempts")->assertCreated()->json('data.id');

        $this->signIn($teacher);
        $this->postJson("/api/attempts/{$inProgressId}/remediation", $payload)->assertNotFound();
    }

    public function test_student_detail_shows_attempts_concepts_remediation_and_comparison(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);

        $base = $this->quiz($course, ['title' => 'Kuis Dasar']);
        $b1 = $this->question($base, $concept, 1);
        $b2 = $this->question($base, $concept, 2);
        $lesson = $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $re = $this->quiz($course, ['title' => 'Ulang', 'kind' => 'reassessment', 'base_quiz_id' => $base->id]);
        $r1 = $this->question($re, $concept, 1);
        $r2 = $this->question($re, $concept, 2);

        $this->submitViaService($student, $base, [[$b1, false], [$b2, false]]);

        $this->signIn($student);
        $this->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();
        $this->submitViaService($student, $re, [[$r1, true], [$r2, true]]);

        $this->signIn($teacher);

        $this->getJson("/api/courses/{$course->id}/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonCount(2, 'data.attempts')
            ->assertJsonPath('data.attempts.0.quiz.title', 'Kuis Dasar')
            ->assertJsonPath('data.attempts.1.quiz.title', 'Ulang')
            ->assertJsonCount(2, 'data.latest_concepts')
            ->assertJsonPath('data.latest_concepts.0.concepts.0.level', 'not_mastered')
            ->assertJsonCount(1, 'data.remediation')
            ->assertJsonPath('data.remediation.0.status', 'completed')
            ->assertJsonPath('data.remediation.0.source', 'rule')
            ->assertJsonCount(1, 'data.comparisons')
            ->assertJsonPath('data.comparisons.0.status', 'complete')
            ->assertJsonPath('data.comparisons.0.concepts.0.trend', 'improved');
    }

    public function test_student_detail_access_rules(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $outsider = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $url = "/api/courses/{$course->id}/students/{$student->id}";

        $this->getJson($url)->assertUnauthorized();

        $this->signIn($teacher);
        $this->getJson("/api/courses/{$course->id}/students/{$outsider->id}")->assertNotFound();
        $this->getJson("/api/courses/{$course->id}/students/abc")->assertNotFound();
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.attempts');

        $this->signIn($this->teacher());
        $this->getJson($url)->assertNotFound();

        $this->signIn($student);
        $this->getJson($url)->assertForbidden();
    }
}
