<?php

namespace Tests\Feature;

use App\Models\QuizAttempt;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    /**
     * @return array{0: TestResponse, 1: array<int, array<int, string|null>>}
     */
    private function fetch(string $url): array
    {
        $response = $this->getJson($url)->assertOk();
        $content = $response->streamedContent();

        $this->assertStringStartsWith(ExportService::BOM, $content);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($content, 3));
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return [$response, $rows];
    }

    /**
     * Skenario sama dengan DashboardTest::tc033: hasil per konsep sudah terbukti di sana.
     *
     * @return array{0: mixed, 1: mixed, 2: mixed, 3: array<string, mixed>}
     */
    private function scenario(): array
    {
        $teacher = $this->teacher();
        $students = [];
        foreach (['Ani', 'Budi', 'Citra', 'Dewi'] as $name) {
            $s = $this->student();
            $s->forceFill(['name' => $name])->save();
            $students[$name] = $s;
        }

        $course = $this->course($teacher);
        foreach ($students as $s) {
            $this->enroll($course, $s);
        }

        $a = $this->concept($course, 'Pecahan');
        $b = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course, ['title' => 'Kuis Pecahan']);
        $a1 = $this->question($quiz, $a, 1);
        $a2 = $this->question($quiz, $a, 2);
        $b1 = $this->question($quiz, $b, 3);
        $b2 = $this->question($quiz, $b, 4);

        // Ani: Pecahan 2/2 (100), Desimal 0/2 (0)
        $this->submitViaService($students['Ani'], $quiz, [[$a1, true], [$a2, true], [$b1, false], [$b2, false]]);
        // Budi: percobaan 1 salah semua; percobaan 2: Pecahan 1/2 (50), Desimal 2/2 (100)
        $this->submitViaService($students['Budi'], $quiz, [[$a1, false], [$a2, false], [$b1, false], [$b2, false]]);
        $this->submitViaService($students['Budi'], $quiz, [[$a1, true], [$a2, false], [$b1, true], [$b2, true]]);
        // Citra: Pecahan 0/2 (0), Desimal 2/2 (100). Dewi belum mengerjakan.
        $this->submitViaService($students['Citra'], $quiz, [[$a1, false], [$a2, false], [$b1, true], [$b2, true]]);

        return [$teacher, $course, $quiz, $students];
    }

    // TC-053: isi CSV percobaan sama dengan hitungan manual
    public function test_tc053_attempts_csv_matches_manual_calculation(): void
    {
        [$teacher, , $quiz, $students] = $this->scenario();

        $this->signIn($teacher);
        [$response, $rows] = $this->fetch("/api/quizzes/{$quiz->id}/export/attempts");

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertMatchesRegularExpression('/kuis-pecahan-percobaan-\d{4}-\d{2}-\d{2}\.csv/', $disposition);
        $this->assertStringContainsString(now('Asia/Jakarta')->format('Y-m-d'), $disposition);

        $this->assertSame(
            ['Nama', 'Percobaan ke', 'Skor (%)', 'Poin', 'Poin maksimum', 'Waktu kirim (WIB)',
                'Pecahan (%)', 'Pecahan (level)', 'Desimal (%)', 'Desimal (level)'],
            $rows[0]
        );

        // Dewi tidak punya percobaan terkirim: header + 4 baris.
        $this->assertCount(5, $rows);

        // [nama, percobaan ke, skor %, Pecahan %, level, Desimal %, level]
        $expected = [
            ['Ani', '1', 50.0, 100.0, 'Menguasai', 0.0, 'Belum menguasai'],
            ['Budi', '1', 0.0, 0.0, 'Belum menguasai', 0.0, 'Belum menguasai'],
            ['Budi', '2', 75.0, 50.0, 'Berkembang', 100.0, 'Menguasai'],
            ['Citra', '1', 50.0, 0.0, 'Belum menguasai', 100.0, 'Menguasai'],
        ];

        foreach ($expected as $i => [$name, $no, $score, $pPct, $pLevel, $dPct, $dLevel]) {
            $row = $rows[$i + 1];
            $this->assertSame($name, $row[0]);
            $this->assertSame($no, $row[1]);
            $this->assertEqualsWithDelta($score, (float) $row[2], 0.01);
            $this->assertEqualsWithDelta($pPct, (float) $row[6], 0.01);
            $this->assertSame($pLevel, $row[7]);
            $this->assertEqualsWithDelta($dPct, (float) $row[8], 0.01);
            $this->assertSame($dLevel, $row[9]);
        }

        // Poin sama dengan database.
        $attempt = QuizAttempt::where('student_id', $students['Ani']->id)->firstOrFail();
        $this->assertEquals($attempt->earned_points, $rows[1][3]);
        $this->assertEquals($attempt->possible_points, $rows[1][4]);

        // Waktu kirim dalam WIB, bukan UTC.
        $this->assertSame($attempt->submitted_at->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'), $rows[1][5]);
        $this->assertNotSame($attempt->submitted_at->copy()->utc()->format('Y-m-d H:i:s'), $rows[1][5]);
    }

    // TC-053: rekap penguasaan memakai percobaan terkirim TERAKHIR (KT-15)
    public function test_tc053_mastery_csv_uses_latest_submitted_attempt(): void
    {
        [$teacher, , $quiz] = $this->scenario();

        $this->signIn($teacher);
        [$response, $rows] = $this->fetch("/api/quizzes/{$quiz->id}/export/mastery");

        $this->assertMatchesRegularExpression('/kuis-pecahan-penguasaan-\d{4}-\d{2}-\d{2}\.csv/', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(
            ['Nama', 'Konsep', 'Benar', 'Jumlah soal', 'Poin', 'Poin maksimum', 'Penguasaan (%)', 'Level', 'Percobaan ke'],
            $rows[0]
        );

        $data = [];
        foreach (array_slice($rows, 1) as $r) {
            $data[$r[0].'|'.$r[1]] = $r;
        }

        // 3 siswa x 2 konsep. Dewi tidak ada. Budi hanya percobaan 2.
        $this->assertCount(6, $data);
        $this->assertEmpty(array_filter(array_keys($data), fn ($k) => str_starts_with($k, 'Dewi')));

        // [benar, jumlah soal, persen, level, percobaan ke]
        $expected = [
            'Ani|Pecahan' => ['2', '2', 100.0, 'Menguasai', '1'],
            'Ani|Desimal' => ['0', '2', 0.0, 'Belum menguasai', '1'],
            'Budi|Pecahan' => ['1', '2', 50.0, 'Berkembang', '2'],
            'Budi|Desimal' => ['2', '2', 100.0, 'Menguasai', '2'],
            'Citra|Pecahan' => ['0', '2', 0.0, 'Belum menguasai', '1'],
            'Citra|Desimal' => ['2', '2', 100.0, 'Menguasai', '1'],
        ];

        foreach ($expected as $key => [$correct, $total, $percent, $level, $no]) {
            $this->assertArrayHasKey($key, $data);
            $this->assertSame($correct, $data[$key][2]);
            $this->assertSame($total, $data[$key][3]);
            $this->assertEqualsWithDelta($percent, (float) $data[$key][6], 0.01);
            $this->assertSame($level, $data[$key][7]);
            $this->assertSame($no, $data[$key][8]);
        }
    }

    public function test_export_of_quiz_without_attempts_has_only_header(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $quiz = $this->quiz($course);

        $this->signIn($teacher);

        [, $attempts] = $this->fetch("/api/quizzes/{$quiz->id}/export/attempts");
        $this->assertCount(1, $attempts);
        $this->assertCount(6, $attempts[0]);

        [, $mastery] = $this->fetch("/api/quizzes/{$quiz->id}/export/mastery");
        $this->assertCount(1, $mastery);
        $this->assertCount(9, $mastery[0]);
    }

    // TC-054: formula injection
    public function test_tc054_cells_starting_with_formula_characters_are_quoted(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course, '=Konsep');
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $names = ['=HYPERLINK("http://x")', '+1+1', '-2+3', '@SUM(A1)'];
        foreach ($names as $name) {
            $s = $this->student();
            $s->forceFill(['name' => $name])->save();
            $this->enroll($course, $s);
            $this->submitViaService($s, $quiz, [[$q1, true], [$q2, true]]);
        }

        $this->signIn($teacher);
        $expected = array_map(fn ($n) => "'".$n, $names);

        [, $attempts] = $this->fetch("/api/quizzes/{$quiz->id}/export/attempts");
        $this->assertSame("'=Konsep (%)", $attempts[0][6]);
        $this->assertSame("'=Konsep (level)", $attempts[0][7]);
        $this->assertEqualsCanonicalizing($expected, array_column(array_slice($attempts, 1), 0));

        [, $mastery] = $this->fetch("/api/quizzes/{$quiz->id}/export/mastery");
        $this->assertEqualsCanonicalizing($expected, array_column(array_slice($mastery, 1), 0));
        $this->assertSame("'=Konsep", $mastery[1][1]);
    }

    public function test_tc054_safe_prefixes_only_dangerous_strings(): void
    {
        $export = new ExportService;

        foreach (['=1', '+1', '-1', '@a', "\tx", "\rx"] as $value) {
            $this->assertSame("'".$value, $export->safe($value));
        }

        foreach (['Ani', 'a=b', '', 'x-y'] as $value) {
            $this->assertSame($value, $export->safe($value));
        }

        $this->assertSame(5, $export->safe(5));
        $this->assertNull($export->safe(null));
    }

    public function test_level_labels_are_indonesian(): void
    {
        $export = new ExportService;

        $this->assertSame('Menguasai', $export->levelLabel('mastered'));
        $this->assertSame('Berkembang', $export->levelLabel('developing'));
        $this->assertSame('Belum menguasai', $export->levelLabel('not_mastered'));
        $this->assertSame('Data belum cukup', $export->levelLabel('insufficient'));
        $this->assertNull($export->levelLabel(null));
    }

    // TC-055: akses ekspor
    public function test_tc055_access_rules(): void
    {
        [$teacher, $course, $quiz, $students] = $this->scenario();

        $otherTeacher = $this->teacher();
        $outsider = $this->student();

        foreach (['attempts', 'mastery'] as $kind) {
            $url = "/api/quizzes/{$quiz->id}/export/{$kind}";

            // Tamu: 401 (sebelum signIn).
            $this->app['auth']->forgetGuards();
            $this->getJson($url)->assertUnauthorized();

            $this->signIn($students['Ani']);
            $this->getJson($url)->assertForbidden();

            $this->signIn($outsider);
            $this->assertContains($this->getJson($url)->status(), [403, 404]);

            $this->signIn($otherTeacher);
            $this->getJson($url)->assertNotFound();

            $this->signIn($teacher);
            $this->getJson($url)->assertOk();
        }
    }
}
