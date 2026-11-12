<?php

namespace Tests\Unit;

use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use App\Services\RemediationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

/**
 * UT-01: logika rekomendasi remedial diuji langsung pada service, di luar jalur HTTP.
 */
class RemediationServiceTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    private function service(): RemediationService
    {
        return app(RemediationService::class);
    }

    /** @return array{0: mixed, 1: mixed, 2: mixed} */
    private function base(): array
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);

        return [$teacher, $student, $course];
    }

    /** @return array<int, int> lesson_id berurutan menurut id assignment */
    private function lessonIds(QuizAttempt $attempt): array
    {
        return RemediationAssignment::where('attempt_id', $attempt->id)->orderBy('id')->pluck('lesson_id')->all();
    }

    public function test_ut01_limit_per_concept_follows_config(): void
    {
        config(['eduflow.remedial_per_concept' => 1]);

        [, $student, $course] = $this->base();
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $first = $this->remedialLesson($course, $concept, 'Remedial 1', 1);
        $this->remedialLesson($course, $concept, 'Remedial 2', 2);
        $this->remedialLesson($course, $concept, 'Remedial 3', 3);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);

        $this->assertEquals([$first->id], $this->lessonIds($attempt));
    }

    public function test_ut01_lessons_with_same_position_are_ordered_by_id(): void
    {
        [, $student, $course] = $this->base();
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $a = $this->remedialLesson($course, $concept, 'Sama A', 1);
        $b = $this->remedialLesson($course, $concept, 'Sama B', 1);
        $this->remedialLesson($course, $concept, 'Sama C', 1);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);

        $this->assertEquals([$a->id, $b->id], $this->lessonIds($attempt));
    }

    public function test_ut01_lesson_from_another_course_is_never_used(): void
    {
        [$teacher, $student, $course] = $this->base();
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $otherCourse = $this->course($teacher, ['title' => 'Kelas lain']);
        $this->remedialLesson($otherCourse, $concept, 'Materi kelas lain', 1);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);

        $this->assertSame(0, RemediationAssignment::where('attempt_id', $attempt->id)->count());
        $this->assertEquals([$concept->id], $this->service()->gaps($attempt)->pluck('concept_id')->all());
    }

    public function test_ut01_mastered_and_insufficient_concepts_produce_nothing(): void
    {
        [, $student, $course] = $this->base();
        $mastered = $this->concept($course, 'Dikuasai');
        $thin = $this->concept($course, 'Sedikit');
        $quiz = $this->quiz($course);
        $m1 = $this->question($quiz, $mastered, 1);
        $m2 = $this->question($quiz, $mastered, 2);
        $t1 = $this->question($quiz, $thin, 3);

        $this->remedialLesson($course, $mastered, 'Remedial Dikuasai', 1);
        $this->remedialLesson($course, $thin, 'Remedial Sedikit', 1);

        $attempt = $this->submitViaService($student, $quiz, [[$m1, true], [$m2, true], [$t1, false]]);

        $this->assertSame(0, RemediationAssignment::where('attempt_id', $attempt->id)->count());
        $this->assertTrue($this->service()->generate($attempt)->isEmpty());
        $this->assertTrue($this->service()->gaps($attempt)->isEmpty());
    }

    public function test_ut01_generate_again_returns_the_existing_assignments(): void
    {
        [, $student, $course] = $this->base();
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $this->remedialLesson($course, $concept, 'Remedial 1', 1);
        $this->remedialLesson($course, $concept, 'Remedial 2', 2);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);
        $stored = RemediationAssignment::where('attempt_id', $attempt->id)->orderBy('id')->pluck('id')->all();

        $returned = $this->service()->generate($attempt)->pluck('id')->all();

        $this->assertEquals($stored, $returned);
        $this->assertSame(2, RemediationAssignment::count());
    }

    public function test_ut01_same_level_is_ordered_by_lowest_percent_then_concept_id(): void
    {
        [, $student, $course] = $this->base();

        // x dibuat lebih dulu (id kecil) tetapi persennya lebih tinggi: 2/3 = 66,67; y = 1/2 = 50.
        $x = $this->concept($course, 'X');
        $y = $this->concept($course, 'Y');
        // Dua konsep tidak dikuasai dengan persen sama (0): urut menurut id.
        $z = $this->concept($course, 'Z');
        $w = $this->concept($course, 'W');

        $quiz = $this->quiz($course);
        $x1 = $this->question($quiz, $x, 1);
        $x2 = $this->question($quiz, $x, 2);
        $x3 = $this->question($quiz, $x, 3);
        $y1 = $this->question($quiz, $y, 4);
        $y2 = $this->question($quiz, $y, 5);
        $z1 = $this->question($quiz, $z, 6);
        $z2 = $this->question($quiz, $z, 7);
        $w1 = $this->question($quiz, $w, 8);
        $w2 = $this->question($quiz, $w, 9);

        foreach ([$x, $y, $z, $w] as $concept) {
            $this->remedialLesson($course, $concept, 'Remedial '.$concept->name, 1);
        }

        $attempt = $this->submitViaService($student, $quiz, [
            [$x1, true], [$x2, true], [$x3, false],
            [$y1, true], [$y2, false],
            [$z1, false], [$z2, false],
            [$w1, false], [$w2, false],
        ]);

        $order = RemediationAssignment::where('attempt_id', $attempt->id)->orderBy('id')->pluck('concept_id')->all();

        // not_mastered dahulu (Z lalu W, persen sama, id menaik), lalu developing dari persen terendah (Y, lalu X).
        $this->assertEquals([$z->id, $w->id, $y->id, $x->id], $order);
    }

    public function test_ut01_reason_formats_percent_with_comma_and_no_trailing_zeros(): void
    {
        [, $student, $course] = $this->base();
        $concept = $this->concept($course, 'Pecahan');
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $q3 = $this->question($quiz, $concept, 3);

        $this->remedialLesson($course, $concept, 'Remedial P', 1);

        $attempt = $this->submitViaService($student, $quiz, [[$q1, true], [$q2, true], [$q3, false]]);

        $reason = RemediationAssignment::where('attempt_id', $attempt->id)->value('reason');

        $this->assertSame(
            'Penguasaan konsep "Pecahan" 66,67% (2/3 soal benar), di bawah ambang 70%. Materi "Remedial P" ditautkan ke konsep ini.',
            $reason
        );
    }
}
