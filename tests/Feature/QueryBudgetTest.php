<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\BuildsAttemptData;
use Tests\Concerns\BuildsForumData;
use Tests\TestCase;

/**
 * TC-063: jumlah query endpoint utama tidak tumbuh seiring data dan tidak melebihi anggaran.
 * Anggaran = hasil ukur UT-08 (13 November 2026) + 2. Dihitung dengan actingAs, jadi query sesi tidak ikut.
 */
class QueryBudgetTest extends TestCase
{
    use BuildsAttemptData;
    use BuildsForumData;
    use RefreshDatabase;

    private const BUDGET = [
        'dashboard guru' => 12,
        'beranda guru' => 8,
        'daftar siswa' => 4,
        'daftar utas' => 7,
        'satu utas' => 7,
        'ekspor percobaan' => 7,
        'ekspor penguasaan' => 7,
        'beranda siswa' => 10,
    ];

    public function test_tc063_query_counts_do_not_grow_with_data_and_stay_in_budget(): void
    {
        $small = $this->measureAll(3, 3, 2);
        $large = $this->measureAll(12, 25, 10);

        foreach (self::BUDGET as $label => $budget) {
            $this->assertSame(
                $small[$label],
                $large[$label],
                "{$label}: jumlah query naik seiring data ({$small[$label]} -> {$large[$label]}), kandidat N+1"
            );
            $this->assertLessThanOrEqual(
                $budget,
                $large[$label],
                "{$label}: {$large[$label]} query melebihi anggaran {$budget}"
            );
        }
    }

    /** @return array<string, int> */
    private function measureAll(int $studentCount, int $threadCount, int $postCount): array
    {
        $s = $this->scenario($studentCount, $threadCount, $postCount);
        $courseId = $s['course']->id;
        $quizId = $s['quiz']->id;
        $result = [];

        $this->signIn($s['teacher']);
        $targets = [
            'dashboard guru' => "/api/courses/{$courseId}/dashboard",
            'beranda guru' => '/api/home',
            'daftar siswa' => "/api/courses/{$courseId}/students",
            'daftar utas' => "/api/courses/{$courseId}/threads",
            'satu utas' => "/api/threads/{$s['thread']->id}",
            'ekspor percobaan' => "/api/quizzes/{$quizId}/export/attempts",
            'ekspor penguasaan' => "/api/quizzes/{$quizId}/export/mastery",
        ];

        foreach ($targets as $label => $url) {
            $result[$label] = $this->countQueries($url);
        }

        $this->signIn($s['students'][0]);
        $result['beranda siswa'] = $this->countQueries('/api/home');

        return $result;
    }

    private function countQueries(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson($url);
        $response->assertOk();

        if ($response->baseResponse instanceof StreamedResponse) {
            $response->streamedContent();
        }

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function scenario(int $studentCount, int $threadCount, int $postCount): array
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $a = $this->concept($course, 'Pecahan');
        $b = $this->concept($course, 'Aljabar');
        $quiz = $this->quiz($course);

        $q = [
            $this->question($quiz, $a, 1),
            $this->question($quiz, $a, 2),
            $this->question($quiz, $b, 3),
            $this->question($quiz, $b, 4),
        ];
        $this->remedialLesson($course, $a, 'Remedial Pecahan', 1);

        $students = [];
        for ($i = 0; $i < $studentCount; $i++) {
            $student = $this->student();
            $this->enroll($course, $student);
            $this->submitViaService($student, $quiz, [
                [$q[0], $i % 2 === 0],
                [$q[1], $i % 3 === 0],
                [$q[2], $i % 2 === 1],
                [$q[3], true],
            ]);
            $students[] = $student;
        }

        $first = null;
        for ($t = 0; $t < $threadCount; $t++) {
            $author = $t % 2 === 0 ? $teacher : $students[$t % $studentCount];
            $thread = $this->makeThread($course, $author, ['is_pinned' => $t === 0]);
            $first ??= $thread;
        }

        for ($p = 0; $p < $postCount; $p++) {
            $this->makePost($first, $students[$p % $studentCount], "Balasan {$p}");
        }

        return ['teacher' => $teacher, 'students' => $students, 'course' => $course, 'quiz' => $quiz, 'thread' => $first];
    }
}
