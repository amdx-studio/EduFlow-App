<?php

namespace Tests\Unit;

use App\Services\ComparisonService;
use PHPUnit\Framework\TestCase;

class ComparisonServiceTest extends TestCase
{
    public function test_trend_boundaries_with_default_delta(): void
    {
        $this->assertSame('improved', ComparisonService::trend(10.0, 10.0));
        $this->assertSame('unchanged', ComparisonService::trend(9.99, 10.0));
        $this->assertSame('declined', ComparisonService::trend(-10.0, 10.0));
        $this->assertSame('unchanged', ComparisonService::trend(-9.99, 10.0));
    }

    public function test_trend_extremes_and_zero(): void
    {
        $this->assertSame('improved', ComparisonService::trend(40.0, 10.0));
        $this->assertSame('declined', ComparisonService::trend(-100.0, 10.0));
        $this->assertSame('unchanged', ComparisonService::trend(0.0, 10.0));
        $this->assertSame('unchanged', ComparisonService::trend(5.0, 10.0));
    }

    public function test_trend_uses_the_threshold_given(): void
    {
        $this->assertSame('improved', ComparisonService::trend(5.0, 5.0));
        $this->assertSame('unchanged', ComparisonService::trend(5.0, 6.0));
        $this->assertSame('declined', ComparisonService::trend(-5.0, 5.0));
    }

    public function test_note_is_fixed_by_br017(): void
    {
        $this->assertSame('Perubahan skor, bukan bukti sebab-akibat.', ComparisonService::NOTE);
    }
}
