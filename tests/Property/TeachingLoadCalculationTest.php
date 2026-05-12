<?php
/**
 * Property 3: Calculation Correctness — Teaching Load & Pass Rate
 *
 * Teaching_Load = SUM(credits) của các sections được phân công
 * pass_rate = (count final_score >= 5.0) / count(*) * 100
 *
 * Validates: Requirements 3.1, 3.6, 5.2
 */

namespace Tests\Property;

use PHPUnit\Framework\TestCase;

class TeachingLoadCalculationTest extends TestCase
{
    // ── Teaching Load = SUM(credits) ─────────────────────────

    /**
     * @dataProvider teachingLoadProvider
     */
    public function testTeachingLoadIsSumOfCredits(array $sections, int $expectedLoad): void
    {
        $actual = $this->computeTeachingLoad($sections);
        $this->assertSame(
            $expectedLoad,
            $actual,
            'Teaching load phải = SUM(credits) của tất cả sections được phân công'
        );
    }

    public static function teachingLoadProvider(): array
    {
        return [
            'no sections → 0'          => [[], 0],
            'one section 3 credits'    => [[['credits' => 3]], 3],
            'two sections 3+4 credits' => [[['credits' => 3], ['credits' => 4]], 7],
            'five sections'            => [
                [['credits' => 3], ['credits' => 3], ['credits' => 3], ['credits' => 3], ['credits' => 3]],
                15,
            ],
            'overloaded (>20)'         => [
                [['credits' => 6], ['credits' => 6], ['credits' => 6], ['credits' => 6]],
                24,
            ],
            'exactly 20 credits'       => [
                [['credits' => 4], ['credits' => 4], ['credits' => 4], ['credits' => 4], ['credits' => 4]],
                20,
            ],
        ];
    }

    // ── Overload detection ────────────────────────────────────

    /**
     * @dataProvider overloadProvider
     */
    public function testOverloadDetection(int $totalCredits, bool $isOverloaded): void
    {
        $this->assertSame(
            $isOverloaded,
            $totalCredits > 20,
            "totalCredits=$totalCredits overloaded phải là " . ($isOverloaded ? 'true' : 'false')
        );
    }

    public static function overloadProvider(): array
    {
        return [
            '0 credits → not overloaded'  => [0,  false],
            '20 credits → not overloaded' => [20, false],
            '21 credits → overloaded'     => [21, true],
            '30 credits → overloaded'     => [30, true],
        ];
    }

    // ── Pass rate calculation ─────────────────────────────────

    /**
     * @dataProvider passRateProvider
     */
    public function testPassRateCalculation(array $scores, ?float $expectedRate): void
    {
        $actual = $this->computePassRate($scores);
        if ($expectedRate === null) {
            $this->assertNull($actual, 'Pass rate phải null khi không có điểm');
        } else {
            $this->assertEqualsWithDelta(
                $expectedRate,
                $actual,
                0.1,
                "Pass rate phải = $expectedRate%"
            );
        }
    }

    public static function passRateProvider(): array
    {
        return [
            'no scores → null'          => [[], null],
            'all pass (10/10)'          => [[10, 9, 8, 7, 6, 5, 5, 5, 5, 5], 100.0],
            'all fail (0/5)'            => [[4, 3, 2, 1, 0], 0.0],
            '5/10 pass = 50%'           => [[10, 9, 8, 7, 6, 4, 3, 2, 1, 0], 50.0],
            '7/10 pass = 70%'           => [[10, 9, 8, 7, 6, 5, 5, 4, 3, 2], 70.0],
            'exactly 5.0 counts as pass'=> [[5.0], 100.0],
            '4.9 counts as fail'        => [[4.9], 0.0],
        ];
    }

    // ── Pass rate below 70% detection ────────────────────────

    /**
     * @dataProvider lowPassRateProvider
     */
    public function testLowPassRateDetection(?float $passRate, bool $isLow): void
    {
        $this->assertSame(
            $isLow,
            $passRate !== null && $passRate < 70.0,
            "passRate=$passRate isLow phải là " . ($isLow ? 'true' : 'false')
        );
    }

    public static function lowPassRateProvider(): array
    {
        return [
            'null → not low'    => [null,  false],
            '0% → low'          => [0.0,   true],
            '69.9% → low'       => [69.9,  true],
            '70% → not low'     => [70.0,  false],
            '70.1% → not low'   => [70.1,  false],
            '100% → not low'    => [100.0, false],
        ];
    }

    // ── Helper methods ────────────────────────────────────────

    private function computeTeachingLoad(array $sections): int
    {
        return array_sum(array_column($sections, 'credits'));
    }

    private function computePassRate(array $scores): ?float
    {
        if (empty($scores)) return null;
        $pass = count(array_filter($scores, fn($s) => (float)$s >= 5.0));
        return round($pass / count($scores) * 100, 1);
    }
}
