<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-033 dan bagian dashboard TC-028
    public function test_tc033_dashboard_numbers_match_manual_calculation(): void
    {
        $teacher = $this->teacher();
        $s1 = $this->student();
        $s2 = $this->student();
        $s3 = $this->student();
        $s4 = $this->student();
        $course = $this->course($teacher);
        foreach ([$s1, $s2, $s3, $s4] as $s) {
            $this->enroll($course, $s);
        }

        $a = $this->concept($course, 'Pecahan');
        $b = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $a1 = $this->question($quiz, $a, 1);
        $a2 = $this->question($quiz, $a, 2);
        $b1 = $this->question($quiz, $b, 3);
        $b2 = $this->question($quiz, $b, 4);
        $lesson = $this->remedialLesson($course, $a, 'Remedial Pecahan', 1);

        // s1: Pecahan 2/2 (100), Desimal 0/2 (0)
        $this->submitViaService($s1, $quiz, [[$a1, true], [$a2, true], [$b1, false], [$b2, false]]);
        // s2: percobaan 1 salah semua; percobaan 2 (terakhir): Pecahan 1/2 (50), Desimal 2/2 (100)
        $this->submitViaService($s2, $quiz, [[$a1, false], [$a2, false], [$b1, false], [$b2, false]]);
        $this->submitViaService($s2, $quiz, [[$a1, true], [$a2, false], [$b1, true], [$b2, true]]);
        // s3: Pecahan 0/2 (0), Desimal 2/2 (100). s4 belum mengerjakan.
        $this->submitViaService($s3, $quiz, [[$a1, false], [$a2, false], [$b1, true], [$b2, true]]);

        $this->signIn($s2);
        $this->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();

        $this->signIn($teacher);
        $data = $this->getJson("/api/courses/{$course->id}/dashboard")->assertOk()->json('data');

        $this->assertSame($quiz->id, $data['quiz']['id']);

        // Assignment dari percobaan terakhir saja: s2 (selesai) dan s3 (belum) = 2, selesai 1.
        $this->assertSame(4, $data['totals']['students_enrolled']);
        $this->assertSame(3, $data['totals']['students_submitted']);
        $this->assertSame(2, $data['totals']['assignments_total']);
        $this->assertSame(1, $data['totals']['assignments_completed']);
        $this->assertEqualsWithDelta(50.0, $data['totals']['assignments_completed_percent'], 0.01);

        $this->assertCount(2, $data['concepts']);
        $this->assertSame('Pecahan', $data['concepts'][0]['concept']);
        $this->assertEqualsWithDelta(50.0, $data['concepts'][0]['average_percent'], 0.01);
        $this->assertSame(3, $data['concepts'][0]['students_count']);
        $this->assertEquals(['mastered' => 1, 'developing' => 1, 'not_mastered' => 1, 'insufficient' => 0], $data['concepts'][0]['levels']);
        $this->assertSame('Desimal', $data['concepts'][1]['concept']);
        $this->assertEqualsWithDelta(66.67, $data['concepts'][1]['average_percent'], 0.01);
        $this->assertEquals(['mastered' => 2, 'developing' => 0, 'not_mastered' => 1, 'insufficient' => 0], $data['concepts'][1]['levels']);

        // Perlu perhatian: s1 (Desimal) dan s3 (Pecahan). s2 hanya developing.
        $ids = collect($data['attention'])->pluck('student.id')->sort()->values()->all();
        $this->assertSame(collect([$s1->id, $s3->id])->sort()->values()->all(), $ids);
        $entry1 = collect($data['attention'])->firstWhere('student.id', $s1->id);
        $this->assertSame('Desimal', $entry1['concepts'][0]['concept']);
        $this->assertEqualsWithDelta(0.0, $entry1['concepts'][0]['percent'], 0.01);
        $entry3 = collect($data['attention'])->firstWhere('student.id', $s3->id);
        $this->assertSame('Pecahan', $entry3['concepts'][0]['concept']);

        // Desimal lemah pada s1 dan tidak punya materi remedial terbit.
        $this->assertCount(1, $data['resource_gaps']);
        $this->assertSame('Desimal', $data['resource_gaps'][0]['concept']);
        $this->assertSame(1, $data['resource_gaps'][0]['students_count']);

        $this->assertSame([], $data['comparison']);
    }

    public function test_default_quiz_is_latest_base_quiz_with_attempts_and_quiz_id_selects(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);

        $first = $this->quiz($course, ['title' => 'Kuis A']);
        $second = $this->quiz($course, ['title' => 'Kuis B']);
        $third = $this->quiz($course, ['title' => 'Kuis C']);
        foreach ([$first, $second] as $q) {
            $q1 = $this->question($q, $concept, 1);
            $q2 = $this->question($q, $concept, 2);
            $this->submitViaService($student, $q, [[$q1, true], [$q2, true]]);
        }

        $reassessment = $this->quiz($course, ['title' => 'Ulang', 'kind' => 'reassessment', 'base_quiz_id' => $first->id]);

        $other = $this->course($teacher, ['title' => 'IPA']);
        $foreign = $this->quiz($other);

        $this->signIn($teacher);

        $default = $this->getJson("/api/courses/{$course->id}/dashboard")->assertOk()->json('data');
        $this->assertSame($second->id, $default['quiz']['id']);
        $this->assertSame([$first->id, $second->id, $third->id], collect($default['quizzes'])->pluck('id')->all());

        $this->getJson("/api/courses/{$course->id}/dashboard?quiz_id={$first->id}")
            ->assertOk()
            ->assertJsonPath('data.quiz.id', $first->id);

        $this->getJson("/api/courses/{$course->id}/dashboard?quiz_id={$reassessment->id}")->assertNotFound();
        $this->getJson("/api/courses/{$course->id}/dashboard?quiz_id={$foreign->id}")->assertNotFound();
        $this->getJson("/api/courses/{$course->id}/dashboard?quiz_id=abc")->assertUnprocessable();
    }

    public function test_dashboard_aggregates_before_after_per_reassessment(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);

        $base = $this->quiz($course);
        $b1 = $this->question($base, $concept, 1);
        $b2 = $this->question($base, $concept, 2);
        $re = $this->quiz($course, ['title' => 'Ulang', 'kind' => 'reassessment', 'base_quiz_id' => $base->id]);
        $r1 = $this->question($re, $concept, 1);
        $r2 = $this->question($re, $concept, 2);

        $this->submitViaService($student, $base, [[$b1, false], [$b2, false]]);
        $this->submitViaService($student, $re, [[$r1, true], [$r2, true]]);

        $this->signIn($teacher);
        $data = $this->getJson("/api/courses/{$course->id}/dashboard")->assertOk()->json('data');

        $this->assertSame($base->id, $data['quiz']['id']);
        $this->assertCount(1, $data['comparison']);
        $this->assertSame($re->id, $data['comparison'][0]['quiz']['id']);
        $this->assertSame(1, $data['comparison'][0]['students_count']);
        $this->assertSame('Perubahan skor, bukan bukti sebab-akibat.', $data['comparison'][0]['note']);
        $row = $data['comparison'][0]['concepts'][0];
        $this->assertEqualsWithDelta(0.0, $row['before'], 0.01);
        $this->assertEqualsWithDelta(100.0, $row['after'], 0.01);
        $this->assertEqualsWithDelta(100.0, $row['delta'], 0.01);
    }

    public function test_course_without_attempts_returns_empty_dashboard(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);

        $this->signIn($teacher);

        $this->getJson("/api/courses/{$course->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.quiz', null)
            ->assertJsonPath('data.totals.students_enrolled', 1)
            ->assertJsonPath('data.totals.students_submitted', 0)
            ->assertJsonPath('data.totals.assignments_completed_percent', null)
            ->assertJsonCount(0, 'data.concepts')
            ->assertJsonCount(0, 'data.attention')
            ->assertJsonCount(0, 'data.resource_gaps')
            ->assertJsonCount(0, 'data.comparison');
    }

    // TC-035
    public function test_tc035_access_rules(): void
    {
        $owner = $this->teacher();
        $member = $this->student();
        $outsider = $this->student();
        $course = $this->course($owner);
        $this->enroll($course, $member);

        $this->getJson("/api/courses/{$course->id}/dashboard")->assertUnauthorized();

        $this->signIn($this->teacher());
        $this->getJson("/api/courses/{$course->id}/dashboard")->assertNotFound();

        $this->signIn($member);
        $this->getJson("/api/courses/{$course->id}/dashboard")->assertForbidden();

        $this->signIn($outsider);
        $this->getJson("/api/courses/{$course->id}/dashboard")->assertNotFound();
    }
}
