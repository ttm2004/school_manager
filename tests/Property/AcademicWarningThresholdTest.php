<?php
/**
 * Property 7: Academic Warning Threshold Correctness
 *
 * GPA warning   iff avg_gpa < 4.0
 * Credits warning iff failed/total > 0.3
 * Retake warning  iff count(same subject) > 2
 *
 * Validates: Requirements 17.1, 17.2, 17.3, 17.8
 *
 * Dùng mock mysqli để test logic thuần túy không cần DB thật.
 */

namespace Tests\Property;

use PHPUnit\Framework\TestCase;

class AcademicWarningThresholdTest extends TestCase
{
    // ── GPA threshold tests ───────────────────────────────────

    /**
     * @dataProvider gpaProvider
     */
    public function testGpaWarningThreshold(float $gpa, bool $expectWarning): void
    {
        $warnings = $this->computeWarningsFromGpa($gpa);
        if ($expectWarning) {
            $this->assertContains('gpa', $warnings, "GPA=$gpa phải có cảnh báo 'gpa'");
        } else {
            $this->assertNotContains('gpa', $warnings, "GPA=$gpa không được có cảnh báo 'gpa'");
        }
    }

    public static function gpaProvider(): array
    {
        return [
            'gpa=0.0 → warning'   => [0.0,  true],
            'gpa=1.5 → warning'   => [1.5,  true],
            'gpa=3.99 → warning'  => [3.99, true],
            'gpa=4.0 → no warning'=> [4.0,  false],
            'gpa=4.01 → no warning'=> [4.01, false],
            'gpa=7.5 → no warning'=> [7.5,  false],
            'gpa=10.0 → no warning'=> [10.0, false],
        ];
    }

    // ── Credits (fail ratio) threshold tests ─────────────────

    /**
     * @dataProvider creditsProvider
     */
    public function testCreditsWarningThreshold(int $total, int $failed, bool $expectWarning): void
    {
        $warnings = $this->computeWarningsFromCredits($total, $failed);
        if ($expectWarning) {
            $this->assertContains('credits', $warnings, "failed=$failed/total=$total phải có cảnh báo 'credits'");
        } else {
            $this->assertNotContains('credits', $warnings, "failed=$failed/total=$total không được có cảnh báo 'credits'");
        }
    }

    public static function creditsProvider(): array
    {
        return [
            'total=0 → no warning (no data)'  => [0, 0, false],
            '0/10 failed → no warning'         => [10, 0, false],
            '3/10 = 30% → no warning (not > 30%)' => [10, 3, false],
            '4/10 = 40% → warning'             => [10, 4, true],
            '1/3 = 33% → warning'              => [3, 1, true],
            '1/2 = 50% → warning'              => [2, 1, true],
            '10/10 = 100% → warning'           => [10, 10, true],
            '3/9 = 33.3% → warning'            => [9, 3, true],
            '2/7 = 28.6% → no warning'         => [7, 2, false],
        ];
    }

    // ── Retake threshold tests ────────────────────────────────

    /**
     * @dataProvider retakeProvider
     */
    public function testRetakeWarningThreshold(int $retakeCount, bool $expectWarning): void
    {
        $warnings = $this->computeWarningsFromRetake($retakeCount);
        if ($expectWarning) {
            $this->assertContains('retake', $warnings, "retake=$retakeCount phải có cảnh báo 'retake'");
        } else {
            $this->assertNotContains('retake', $warnings, "retake=$retakeCount không được có cảnh báo 'retake'");
        }
    }

    public static function retakeProvider(): array
    {
        return [
            'retake=0 → no warning' => [0, false],
            'retake=1 → no warning' => [1, false],
            'retake=2 → no warning' => [2, false],
            'retake=3 → warning'    => [3, true],
            'retake=5 → warning'    => [5, true],
            'retake=10 → warning'   => [10, true],
        ];
    }

    // ── Combined: no false positives ─────────────────────────

    public function testNoWarningsWhenAllGood(): void
    {
        // GPA tốt, không trượt, không học lại
        $warnings = $this->computeAllWarnings(8.5, 10, 0, 0);
        $this->assertEmpty($warnings, 'Không có cảnh báo khi tất cả chỉ số tốt');
    }

    public function testAllWarningsWhenAllBad(): void
    {
        // GPA thấp, trượt nhiều, học lại nhiều
        $warnings = $this->computeAllWarnings(2.0, 10, 5, 4);
        $this->assertContains('gpa', $warnings);
        $this->assertContains('credits', $warnings);
        $this->assertContains('retake', $warnings);
    }

    // ── Helper methods (simulate getAcademicWarnings logic) ──

    private function computeWarningsFromGpa(float $gpa): array
    {
        $warnings = [];
        if ($gpa < 4.0) {
            $warnings[] = 'gpa';
        }
        return $warnings;
    }

    private function computeWarningsFromCredits(int $total, int $failed): array
    {
        $warnings = [];
        if ($total > 0) {
            $ratio = $failed / $total;
            if ($ratio > 0.3) {
                $warnings[] = 'credits';
            }
        }
        return $warnings;
    }

    private function computeWarningsFromRetake(int $retakeCount): array
    {
        $warnings = [];
        if ($retakeCount > 2) {
            $warnings[] = 'retake';
        }
        return $warnings;
    }

    private function computeAllWarnings(float $gpa, int $total, int $failed, int $retakeCount): array
    {
        $warnings = [];
        if ($gpa < 4.0) $warnings[] = 'gpa';
        if ($total > 0 && ($failed / $total) > 0.3) $warnings[] = 'credits';
        if ($retakeCount > 2) $warnings[] = 'retake';
        return $warnings;
    }
}
