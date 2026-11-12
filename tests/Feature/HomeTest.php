<?php

namespace Tests\Feature;

use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-059 (siswa): remedial, kuis tersedia, kuis terkunci, lanjutkan
    public function test_tc059_student_home_shows_remediation_available_locked_and_continue(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);

        $a = $this->concept($course, 'Pecahan');
        $base = $this->quiz($course, ['title' => 'Kuis Dasar']);
        $a1 = $this->question($base, $a, 1);
        $a2 = $this->question($base, $a, 2);
        $lesson = $this->remedialLesson($course, $a, 'Remedial Pecahan', 1);
        $reassessment = $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
        ]);

        $this->submitViaService($student, $base, [[$a1, false], [$a2, false]]);

        $this->signIn($student);
        $data = $this->getJson('/api/home')->assertOk()->json('data');

        $this->assertSame('student', $data['role']);
        $this->assertSame(1, $data['courses_count']);
        $this->assertSame([], $data['continue']);

        $this->assertCount(1, $data['remediation']);
        $this->assertSame($course->id, $data['remediation'][0]['course']['id']);
        $this->assertSame(1, $data['remediation'][0]['pending_count']);

        $this->assertSame([$base->id], collect($data['quizzes']['available'])->pluck('id')->all());
        $this->assertSame(1, $data['quizzes']['available'][0]['attempts_used']);
        $this->assertSame(2, $data['quizzes']['available'][0]['max_attempts']);

        $this->assertSame([$reassessment->id], collect($data['quizzes']['locked'])->pluck('id')->all());
        $this->assertSame('Selesaikan remedial.', $data['quizzes']['locked'][0]['gate']['reason']);
        $this->assertSame(1, $data['quizzes']['locked'][0]['gate']['pending_count']);

        // Percobaan berjalan muncul di "lanjutkan" dan kuisnya tidak lagi di "tersedia".
        [$attempt] = app(AttemptService::class)->start($student, $base);

        $data = $this->getJson('/api/home')->assertOk()->json('data');
        $this->assertCount(1, $data['continue']);
        $this->assertSame($attempt->id, $data['continue'][0]['attempt_id']);
        $this->assertSame(2, $data['continue'][0]['attempt_no']);
        $this->assertSame([], $data['quizzes']['available']);

        // Remedial selesai: daftar remedial kosong dan asesmen ulang terbuka.
        $this->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();

        $data = $this->getJson('/api/home')->assertOk()->json('data');
        $this->assertSame([], $data['remediation']);
        $this->assertSame([], $data['quizzes']['locked']);
        $this->assertSame([$reassessment->id], collect($data['quizzes']['available'])->pluck('id')->all());
    }

    public function test_tc059_student_home_excludes_archived_draft_and_other_students(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $other = $this->student();

        $active = $this->course($teacher, ['title' => 'Aktif']);
        $archived = $this->course($teacher, ['title' => 'Arsip', 'is_archived' => true]);
        $foreign = $this->course($teacher, ['title' => 'Asing']);
        $this->enroll($active, $student);
        $this->enroll($active, $other);
        $this->enroll($archived, $student);
        $this->enroll($foreign, $other);

        $quizActive = $this->quiz($active, ['title' => 'Kuis Aktif']);
        $this->quiz($active, ['title' => 'Kuis Draf', 'status' => 'draft', 'published_at' => null]);
        $this->quiz($archived, ['title' => 'Kuis Arsip']);
        $this->quiz($foreign, ['title' => 'Kuis Asing']);

        // Percobaan berjalan milik siswa lain tidak boleh muncul.
        app(AttemptService::class)->start($other, $quizActive);

        $this->signIn($student);
        $data = $this->getJson('/api/home')->assertOk()->json('data');

        $this->assertSame(2, $data['courses_count']);
        $this->assertSame([], $data['continue']);
        $this->assertSame([], $data['remediation']);
        $this->assertSame([$quizActive->id], collect($data['quizzes']['available'])->pluck('id')->all());
        $this->assertSame([], $data['quizzes']['locked']);
    }

    public function test_tc059_student_home_limits_lists_and_reports_totals(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);

        for ($i = 1; $i <= 12; $i++) {
            $this->quiz($course, ['title' => "Kuis {$i}"]);
        }

        $this->signIn($student);
        $data = $this->getJson('/api/home')->assertOk()->json('data');

        $this->assertCount(10, $data['quizzes']['available']);
        $this->assertSame(12, $data['quizzes']['available_total']);
        $this->assertSame(0, $data['quizzes']['locked_total']);
    }

    public function test_tc059_student_without_courses_gets_empty_home(): void
    {
        $this->signIn($this->student());
        $data = $this->getJson('/api/home')->assertOk()->json('data');

        $this->assertSame(0, $data['courses_count']);
        $this->assertSame([], $data['continue']);
        $this->assertSame([], $data['remediation']);
        $this->assertSame([], $data['quizzes']['available']);
        $this->assertSame([], $data['quizzes']['locked']);
    }

    // TC-059 (guru): angka perlu perhatian harus sama dengan dashboard (KT-15)
    public function test_tc059_teacher_home_attention_count_equals_dashboard(): void
    {
        $teacher = $this->teacher();
        $otherTeacher = $this->teacher();
        $s1 = $this->student();
        $s2 = $this->student();
        $s3 = $this->student();

        $course = $this->course($teacher, ['title' => 'Geometri']);
        $second = $this->course($teacher, ['title' => 'Aljabar']);
        $this->course($teacher, ['title' => 'Arsip', 'is_archived' => true]);
        $this->course($otherTeacher, ['title' => 'Milik Lain']);
        foreach ([$s1, $s2, $s3] as $s) {
            $this->enroll($course, $s);
        }

        $a = $this->concept($course, 'Pecahan');
        $quiz = $this->quiz($course);
        $this->quiz($course, ['title' => 'Kuis Draf', 'status' => 'draft', 'published_at' => null]);
        $a1 = $this->question($quiz, $a, 1);
        $a2 = $this->question($quiz, $a, 2);

        // s1 lemah (0%), s2 menguasai (100%), s3 belum mengerjakan.
        $this->submitViaService($s1, $quiz, [[$a1, false], [$a2, false]]);
        $this->submitViaService($s2, $quiz, [[$a1, true], [$a2, true]]);

        $this->signIn($teacher);
        $home = $this->getJson('/api/home')->assertOk()->json('data');
        $dashboard = $this->getJson("/api/courses/{$course->id}/dashboard")->assertOk()->json('data');

        $this->assertSame('teacher', $home['role']);
        $this->assertSame(3, $home['courses_count']);
        $this->assertSame(['Aljabar', 'Geometri'], collect($home['courses'])->pluck('title')->all());

        $row = collect($home['courses'])->firstWhere('id', $course->id);
        $this->assertSame(3, $row['students_count']);
        $this->assertSame(1, $row['published_quizzes_count']);
        $this->assertSame(1, $row['attention_count']);
        $this->assertSame(count($dashboard['attention']), $row['attention_count']);

        $empty = collect($home['courses'])->firstWhere('id', $second->id);
        $this->assertSame(0, $empty['students_count']);
        $this->assertSame(0, $empty['published_quizzes_count']);
        $this->assertSame(0, $empty['attention_count']);
    }

    public function test_tc059_guest_is_rejected_and_teacher_without_courses_gets_empty_home(): void
    {
        $this->getJson('/api/home')->assertUnauthorized();

        $this->signIn($this->teacher());
        $data = $this->getJson('/api/home')->assertOk()->json('data');

        $this->assertSame('teacher', $data['role']);
        $this->assertSame(0, $data['courses_count']);
        $this->assertSame([], $data['courses']);
    }
}
