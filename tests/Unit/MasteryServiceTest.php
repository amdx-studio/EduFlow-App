<?php

namespace Tests\Unit;

use App\Services\MasteryService;
use PHPUnit\Framework\TestCase;

class MasteryServiceTest extends TestCase
{
    // TC-023
    public function test_tc023_boundary_values_with_default_thresholds(): void
    {
        $cases = [
            [70, MasteryService::MASTERED],
            [69.99, MasteryService::DEVELOPING],
            [50, MasteryService::DEVELOPING],
            [49.99, MasteryService::NOT_MASTERED],
        ];

        foreach ($cases as [$raw, $expected]) {
            $this->assertSame(
                $expected,
                MasteryService::classify($raw, 2, 2, 70, 50),
                "raw {$raw} seharusnya {$expected}"
            );
        }
    }

    public function test_extremes_are_classified(): void
    {
        $this->assertSame(MasteryService::MASTERED, MasteryService::classify(100, 4, 2, 70, 50));
        $this->assertSame(MasteryService::NOT_MASTERED, MasteryService::classify(0, 4, 2, 70, 50));
    }

    public function test_insufficient_when_question_count_below_minimum(): void
    {
        // US-011 (3): satu soal pada konsep berarti bukti tidak cukup, berapa pun nilainya.
        $this->assertSame(MasteryService::INSUFFICIENT, MasteryService::classify(100, 1, 2, 70, 50));
        $this->assertSame(MasteryService::INSUFFICIENT, MasteryService::classify(0, 1, 2, 70, 50));

        // Tepat di minimum sudah cukup.
        $this->assertSame(MasteryService::MASTERED, MasteryService::classify(100, 2, 2, 70, 50));
    }

    public function test_uses_the_thresholds_given_instead_of_hardcoded_values(): void
    {
        $this->assertSame(MasteryService::DEVELOPING, MasteryService::classify(75, 3, 2, 80, 40));
        $this->assertSame(MasteryService::MASTERED, MasteryService::classify(80, 3, 2, 80, 40));
        $this->assertSame(MasteryService::NOT_MASTERED, MasteryService::classify(39.99, 3, 2, 80, 40));

        // Minimum soal juga mengikuti kelas.
        $this->assertSame(MasteryService::INSUFFICIENT, MasteryService::classify(100, 2, 3, 70, 50));
    }

    public function test_raw_percent_is_exact_for_whole_results(): void
    {
        $this->assertSame(70.0, MasteryService::rawPercent(7, 10));
        $this->assertSame(75.0, MasteryService::rawPercent(3, 4));
        $this->assertSame(50.0, MasteryService::rawPercent(1, 2));
        $this->assertSame(100.0, MasteryService::rawPercent(5, 5));
        $this->assertSame(0.0, MasteryService::rawPercent(0, 5));
    }

    public function test_raw_percent_is_zero_when_no_points_possible(): void
    {
        $this->assertSame(0.0, MasteryService::rawPercent(0, 0));
    }

    public function test_raw_percent_feeds_classification_without_rounding_loss(): void
    {
        // 7 dari 10 poin tepat di ambang 70: harus dikuasai.
        $this->assertSame(
            MasteryService::MASTERED,
            MasteryService::classify(MasteryService::rawPercent(7, 10), 2, 2, 70, 50)
        );

        // 2 dari 3 poin = 66,67%: berkembang. Nilai yang dibulatkan tidak dipakai untuk klasifikasi.
        $raw = MasteryService::rawPercent(2, 3);
        $this->assertSame(MasteryService::DEVELOPING, MasteryService::classify($raw, 3, 2, 70, 50));
        $this->assertSame(66.67, round($raw, 2));
    }
}
