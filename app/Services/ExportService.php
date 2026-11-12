<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV (BR-023, KT-27): di-stream, tanpa file tersimpan.
 */
class ExportService
{
    public const BOM = "\xEF\xBB\xBF";

    /**
     * US-026: semua percobaan terkirim, satu baris per percobaan.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    public function attemptsTable(Quiz $quiz): array
    {
        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('status', 'submitted')
            ->with(['student:id,name', 'conceptResults.concept'])
            ->get()
            ->sort(fn (QuizAttempt $a, QuizAttempt $b) => [mb_strtolower((string) $a->student?->name), $a->attempt_no]
                <=> [mb_strtolower((string) $b->student?->name), $b->attempt_no])
            ->values();

        $concepts = $attempts
            ->flatMap(fn (QuizAttempt $a) => $a->conceptResults)
            ->map(fn ($r) => $r->concept)
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();

        $header = ['Nama', 'Percobaan ke', 'Skor (%)', 'Poin', 'Poin maksimum', 'Waktu kirim (WIB)'];
        foreach ($concepts as $concept) {
            $label = $this->conceptLabel($concept);
            $header[] = $label.' (%)';
            $header[] = $label.' (level)';
        }

        $rows = $attempts->map(function (QuizAttempt $a) use ($concepts) {
            $byConcept = $a->conceptResults->keyBy('concept_id');
            $row = [
                $a->student?->name,
                $a->attempt_no,
                $a->score_percent,
                $a->earned_points,
                $a->possible_points,
                $this->wib($a->submitted_at),
            ];
            foreach ($concepts as $concept) {
                $result = $byConcept->get($concept->id);
                $row[] = $result?->mastery_percent;
                $row[] = $result?->mastery_level;
            }

            return $row;
        })->all();

        return [$header, $rows];
    }

    /**
     * US-027: penguasaan per siswa per konsep dari percobaan terkirim TERAKHIR (KT-15).
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    public function masteryTable(Quiz $quiz): array
    {
        $latest = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('status', 'submitted')
            ->with(['student:id,name', 'conceptResults.concept'])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->sortByDesc('attempt_no')->first())
            ->sortBy(fn (QuizAttempt $a) => mb_strtolower((string) $a->student?->name))
            ->values();

        $header = ['Nama', 'Konsep', 'Benar', 'Jumlah soal', 'Poin', 'Poin maksimum', 'Penguasaan (%)', 'Level', 'Percobaan ke'];

        $rows = [];
        foreach ($latest as $attempt) {
            foreach ($attempt->conceptResults->sortBy('concept_id') as $r) {
                $rows[] = [
                    $attempt->student?->name,
                    $r->concept ? $this->conceptLabel($r->concept) : null,
                    $r->correct_count,
                    $r->total_count,
                    $r->earned_points,
                    $r->possible_points,
                    $r->mastery_percent,
                    $r->mastery_level,
                    $attempt->attempt_no,
                ];
            }
        }

        return [$header, $rows];
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function download(string $quizTitle, string $kind, array $header, array $rows): StreamedResponse
    {
        $slug = Str::slug($quizTitle);
        $filename = ($slug !== '' ? $slug : 'kuis').'-'.$kind.'-'.now('Asia/Jakarta')->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, self::BOM);
            fputcsv($out, array_map($this->safe(...), $header), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map($this->safe(...), $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * BR-023: sel berawalan = + - @ tab atau CR diberi kutip tunggal di depan.
     */
    public function safe(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    private function wib($time): ?string
    {
        return $time?->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
    }

    private function conceptLabel($concept): string
    {
        return (string) $concept->name;
    }
}
