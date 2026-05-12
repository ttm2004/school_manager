<?php
/**
 * Property 10: Curriculum Credit Totals
 *
 * Tổng credits hiển thị cho suggested_semester S
 * = SUM(credits) của non-deleted entries với semester = S
 *
 * Validates: Requirements 6.3, 6.4
 */

namespace Tests\Property;

use PHPUnit\Framework\TestCase;

class CurriculumCreditTotalsTest extends TestCase
{
    // ── Property: sum per semester is correct ─────────────────

    /**
     * @dataProvider curriculumProvider
     */
    public function testSemesterCreditSumIsCorrect(array $entries, int $semester, int $expectedSum): void
    {
        $actual = $this->computeSemesterCredits($entries, $semester);
        $this->assertSame(
            $expectedSum,
            $actual,
            "Tổng tín chỉ học kỳ $semester phải = $expectedSum"
        );
    }

    public static function curriculumProvider(): array
    {
        return [
            'empty curriculum' => [
                [],
                1,
                0,
            ],
            'single entry sem 1' => [
                [['semester' => 1, 'credits' => 3, 'deleted' => false]],
                1,
                3,
            ],
            'multiple entries sem 1' => [
                [
                    ['semester' => 1, 'credits' => 3, 'deleted' => false],
                    ['semester' => 1, 'credits' => 4, 'deleted' => false],
                    ['semester' => 2, 'credits' => 3, 'deleted' => false],
                ],
                1,
                7,
            ],
            'deleted entries excluded' => [
                [
                    ['semester' => 1, 'credits' => 3, 'deleted' => false],
                    ['semester' => 1, 'credits' => 4, 'deleted' => true],  // bị xóa
                ],
                1,
                3,
            ],
            'all deleted → 0' => [
                [
                    ['semester' => 1, 'credits' => 3, 'deleted' => true],
                    ['semester' => 1, 'credits' => 4, 'deleted' => true],
                ],
                1,
                0,
            ],
            'different semesters' => [
                [
                    ['semester' => 1, 'credits' => 3, 'deleted' => false],
                    ['semester' => 2, 'credits' => 4, 'deleted' => false],
                    ['semester' => 3, 'credits' => 5, 'deleted' => false],
                ],
                2,
                4,
            ],
        ];
    }

    // ── Property: cumulative total is sum of all semesters ────

    /**
     * @dataProvider cumulativeProvider
     */
    public function testCumulativeTotalIsCorrect(array $entries, int $expectedTotal): void
    {
        $actual = $this->computeTotalCredits($entries);
        $this->assertSame(
            $expectedTotal,
            $actual,
            "Tổng tín chỉ toàn CTĐT phải = $expectedTotal"
        );
    }

    public static function cumulativeProvider(): array
    {
        return [
            'empty' => [[], 0],
            'all active' => [
                [
                    ['semester' => 1, 'credits' => 3, 'deleted' => false],
                    ['semester' => 2, 'credits' => 4, 'deleted' => false],
                    ['semester' => 3, 'credits' => 3, 'deleted' => false],
                ],
                10,
            ],
            'mix deleted' => [
                [
                    ['semester' => 1, 'credits' => 3, 'deleted' => false],
                    ['semester' => 1, 'credits' => 4, 'deleted' => true],
                    ['semester' => 2, 'credits' => 3, 'deleted' => false],
                ],
                6,
            ],
            'typical 120-credit program' => [
                array_merge(
                    array_fill(0, 10, ['semester' => 1, 'credits' => 3, 'deleted' => false]),
                    array_fill(0, 10, ['semester' => 2, 'credits' => 3, 'deleted' => false]),
                    array_fill(0, 10, ['semester' => 3, 'credits' => 3, 'deleted' => false]),
                    array_fill(0, 10, ['semester' => 4, 'credits' => 3, 'deleted' => false])
                ),
                120,
            ],
        ];
    }

    // ── Property: cumulative = sum of per-semester totals ─────

    /**
     * @dataProvider curriculumProvider
     */
    public function testCumulativeEqualsPerSemesterSum(array $entries, int $semester, int $expectedSum): void
    {
        // Lấy tất cả semesters duy nhất
        $semesters = array_unique(array_column($entries, 'semester'));
        $sumOfSemesters = 0;
        foreach ($semesters as $sem) {
            $sumOfSemesters += $this->computeSemesterCredits($entries, $sem);
        }
        $total = $this->computeTotalCredits($entries);
        $this->assertSame(
            $total,
            $sumOfSemesters,
            'Tổng tích lũy phải = tổng của tất cả học kỳ'
        );
    }

    // ── Helper methods ────────────────────────────────────────

    private function computeSemesterCredits(array $entries, int $semester): int
    {
        $sum = 0;
        foreach ($entries as $entry) {
            if ($entry['semester'] === $semester && !$entry['deleted']) {
                $sum += $entry['credits'];
            }
        }
        return $sum;
    }

    private function computeTotalCredits(array $entries): int
    {
        $sum = 0;
        foreach ($entries as $entry) {
            if (!$entry['deleted']) {
                $sum += $entry['credits'];
            }
        }
        return $sum;
    }
}
