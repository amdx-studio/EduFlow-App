<?php

namespace Tests\Feature;

use App\Models\AttemptConceptResult;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class AttemptResultTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-022
    public function test_tc022_typical_mastery_is_stored_with_thresholds_used(): void
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

        $attempt = $this->submitViaService($student, $quiz, [[$q[1], true], [$q[2], true], [$q[3], true], [$q[4], false]]);

        $this->signIn($student);
        $res = $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonCount(1, 'data.concepts')
            ->assertJsonPath('data.concepts.0.concept', 'Pecahan')
            ->assertJsonPath('data.concepts.0.correct', 3)
            ->assertJsonPath('data.concepts.0.total', 4)
            ->assertJsonPath('data.concepts.0.level', 'mastered')
            ->assertJsonPath('data.concepts.0.mastery_threshold_used', 70)
            ->assertJsonPath('data.concepts.0.weak_threshold_used', 50);

        $this->assertEquals(75.0, $res->json('data.concepts.0.percent'));

        $this->assertDatabaseHas('attempt_concept_results', [
            'attempt_id' => $attempt->id,
            'concept_id' => $concept->id,
            'correct_count' => 3,
            'total_count' => 4,
            'earned_points' => 3,
            'possible_points' => 4,
            'mastery_percent' => 75,
            'mastery_level' => 'mastered',
            'mastery_threshold_used' => 70,
            'weak_threshold_used' => 50,
        ]);
    }

    public function test_changing_course_thresholds_affects_only_later_attempts(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course, ['max_attempts' => 2]);
        $q = [];
        foreach ([1, 2, 3, 4] as $position) {
            $q[$position] = $this->question($quiz, $concept, $position);
        }
        $plan = [[$q[1], true], [$q[2], true], [$q[3], true], [$q[4], false]];

        $first = $this->submitViaService($student, $quiz, $plan);

        $course->update(['mastery_threshold' => 80]);

        $second = $this->submitViaService($student, $quiz, $plan);

        $firstResult = AttemptConceptResult::where('attempt_id', $first->id)->firstOrFail();
        $secondResult = AttemptConceptResult::where('attempt_id', $second->id)->firstOrFail();

        // Percobaan pertama tidak berubah (snapshot).
        $this->assertSame('mastered', $firstResult->mastery_level);
        $this->assertSame(70, (int) $firstResult->mastery_threshold_used);

        // Percobaan kedua memakai ambang baru: 75% < 80%.
        $this->assertSame('developing', $secondResult->mastery_level);
        $this->assertSame(80, (int) $secondResult->mastery_threshold_used);

        $this->signIn($student);
        $this->getJson("/api/attempts/{$first->id}/result")
            ->assertJsonPath('data.concepts.0.level', 'mastered')
            ->assertJsonPath('data.concepts.0.mastery_threshold_used', 70);
    }

    public function test_exactly_seventy_percent_of_points_is_mastered_end_to_end(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $heavy = $this->question($quiz, $concept, 1, 7);
        $light = $this->question($quiz, $concept, 2, 3);

        $attempt = $this->submitViaService($student, $quiz, [[$heavy, true], [$light, false]]);

        $this->signIn($student);
        $res = $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonPath('data.attempt.earned_points', 7)
            ->assertJsonPath('data.attempt.possible_points', 10)
            ->assertJsonPath('data.concepts.0.level', 'mastered');

        $this->assertEquals(70.0, $res->json('data.concepts.0.percent'));
        $this->assertEquals(70.0, $res->json('data.attempt.score_percent'));
    }

    // TC-024
    public function test_tc024_concept_with_one_question_is_insufficient_and_triggers_no_remediation(): void
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

        $attempt = $this->submitViaService($student, $quiz, [[$p1, true], [$p2, true], [$d1, false]]);

        $this->signIn($student);
        $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonCount(2, 'data.concepts')
            ->assertJsonPath('data.concepts.0.concept', 'Desimal')
            ->assertJsonPath('data.concepts.0.level', 'insufficient')
            ->assertJsonPath('data.concepts.0.total', 1)
            ->assertJsonPath('data.concepts.1.concept', 'Pecahan')
            ->assertJsonPath('data.concepts.1.level', 'mastered')
            ->assertJsonPath('data.remediation', [])
            ->assertJsonPath('data.remediation_gaps', []);

        $this->assertDatabaseCount('remediation_assignments', 0);
    }

    public function test_levels_at_the_weak_boundary_and_below(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $aljabar = $this->concept($course, 'Aljabar');
        $desimal = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $a1 = $this->question($quiz, $aljabar, 1);
        $a2 = $this->question($quiz, $aljabar, 2);
        $d1 = $this->question($quiz, $desimal, 3);
        $d2 = $this->question($quiz, $desimal, 4);

        $attempt = $this->submitViaService($student, $quiz, [[$a1, true], [$a2, false], [$d1, false], [$d2, false]]);

        $this->signIn($student);
        $res = $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            // Tepat 50% = ambang lemah: berkembang.
            ->assertJsonPath('data.concepts.0.concept', 'Aljabar')
            ->assertJsonPath('data.concepts.0.level', 'developing')
            ->assertJsonPath('data.concepts.1.concept', 'Desimal')
            ->assertJsonPath('data.concepts.1.level', 'not_mastered');

        $this->assertEquals(50.0, $res->json('data.concepts.0.percent'));
        $this->assertEquals(0.0, $res->json('data.concepts.1.percent'));
        $this->assertEquals(25.0, $res->json('data.attempt.score_percent'));
    }

    // TC-025
    public function test_tc025_result_matches_database_and_is_visible_only_to_owner_and_course_teacher(): void
    {
        $teacher = $this->teacher();
        $owner = $this->student();
        $classmate = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $owner);
        $this->enroll($course, $classmate);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $attempt = $this->submitViaService($owner, $quiz, [[$q1, true], [$q2, false]]);

        $this->signIn($owner);
        $res = $this->getJson("/api/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertJsonPath('data.attempt.id', $attempt->id)
            ->assertJsonPath('data.attempt.earned_points', 1)
            ->assertJsonPath('data.attempt.possible_points', 2);

        $this->assertEquals((float) $attempt->fresh()->score_percent, $res->json('data.attempt.score_percent'));

        $this->signIn($classmate);
        $this->getJson("/api/attempts/{$attempt->id}/result")->assertNotFound();

        $this->signIn($this->student());
        $this->getJson("/api/attempts/{$attempt->id}/result")->assertNotFound();

        $this->signIn($teacher);
        $this->getJson("/api/attempts/{$attempt->id}/result")->assertOk();

        $this->signIn($this->teacher());
        $this->getJson("/api/attempts/{$attempt->id}/result")->assertNotFound();
    }

    public function test_result_includes_review_fields_and_is_unavailable_before_submit(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course, ['max_attempts' => 2]);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $submitted = $this->submitViaService($student, $quiz, [[$q1, true], [$q2, false]]);

        $this->signIn($student);
        $this->getJson("/api/attempts/{$submitted->id}/result")
            ->assertOk()
            ->assertJsonPath('data.answers.0.explanation', 'Penjelasan 1')
            ->assertJsonPath('data.answers.0.concept', 'Pecahan')
            ->assertJsonPath('data.answers.0.correct_option_id', $this->optionId($q1, true))
            ->assertJsonPath('data.answers.1.selected_option_id', $this->optionId($q2, false))
            ->assertJsonCount(2, 'data.answers.0.options');

        // Percobaan berjalan belum punya hasil.
        [$running] = app(AttemptService::class)->start($student, $quiz->fresh());

        $this->getJson("/api/attempts/{$running->id}/result")->assertStatus(409);
    }
}
