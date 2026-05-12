<?php
/**
 * Property 4: Pagination Correctness
 *
 * Với N records và page size P:
 *   - số records/page <= P
 *   - total_pages = ceil(N/P)
 *   - offset của page k = (k-1) * P
 *   - tổng records qua tất cả pages = N
 *
 * Validates: Requirements 22.1, 22.5
 */

namespace Tests\Property;

use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    // ── Property: total_pages = ceil(N / P) ──────────────────

    /**
     * @dataProvider paginationProvider
     */
    public function testTotalPagesIsCeil(int $n, int $p): void
    {
        $result = paginate($n, 1, $p);
        $expected = max(1, (int)ceil($n / $p));
        $this->assertSame(
            $expected,
            $result['total_pages'],
            "total_pages phải = ceil($n / $p) = $expected"
        );
    }

    /**
     * @dataProvider paginationProvider
     */
    public function testOffsetIsCorrect(int $n, int $p): void
    {
        $totalPages = max(1, (int)ceil($n / $p));
        // Kiểm tra offset cho mỗi trang
        for ($page = 1; $page <= min($totalPages, 5); $page++) {
            $result = paginate($n, $page, $p);
            $expectedOffset = ($page - 1) * $p;
            $this->assertSame(
                $expectedOffset,
                $result['offset'],
                "offset trang $page phải = " . $expectedOffset
            );
        }
    }

    /**
     * @dataProvider paginationProvider
     */
    public function testPerPageNeverExceedsLimit(int $n, int $p): void
    {
        $result = paginate($n, 1, $p);
        $this->assertLessThanOrEqual(
            100,
            $result['per_page'],
            'per_page không được vượt quá 100'
        );
        $this->assertGreaterThanOrEqual(
            1,
            $result['per_page'],
            'per_page phải >= 1'
        );
    }

    /**
     * @dataProvider paginationProvider
     */
    public function testCurrentPageClamped(int $n, int $p): void
    {
        $totalPages = max(1, (int)ceil($n / $p));

        // Trang quá lớn → clamp về total_pages
        $result = paginate($n, $totalPages + 100, $p);
        $this->assertSame($totalPages, $result['current_page']);

        // Trang <= 0 → clamp về 1
        $result = paginate($n, 0, $p);
        $this->assertSame(1, $result['current_page']);

        $result = paginate($n, -5, $p);
        $this->assertSame(1, $result['current_page']);
    }

    /**
     * @dataProvider paginationProvider
     */
    public function testTotalIsPreserved(int $n, int $p): void
    {
        $result = paginate($n, 1, $p);
        $this->assertSame($n, $result['total'], 'total phải bằng N');
    }

    // ── Edge cases ────────────────────────────────────────────

    public function testZeroRecords(): void
    {
        $result = paginate(0, 1, 20);
        $this->assertSame(1, $result['total_pages']);
        $this->assertSame(0, $result['offset']);
        $this->assertSame(0, $result['total']);
    }

    public function testExactlyOnePageFull(): void
    {
        $result = paginate(20, 1, 20);
        $this->assertSame(1, $result['total_pages']);
        $this->assertSame(0, $result['offset']);
    }

    public function testExactlyTwoPages(): void
    {
        $result = paginate(21, 1, 20);
        $this->assertSame(2, $result['total_pages']);

        $result2 = paginate(21, 2, 20);
        $this->assertSame(20, $result2['offset']);
    }

    public function testPerPageClampedToMin1(): void
    {
        $result = paginate(100, 1, 0);
        $this->assertSame(1, $result['per_page']);
    }

    public function testPerPageClampedToMax100(): void
    {
        $result = paginate(100, 1, 999);
        $this->assertSame(100, $result['per_page']);
    }

    // ── Data provider: random (N, P) pairs ───────────────────

    public static function paginationProvider(): array
    {
        $cases = [];
        // Deterministic "random" cases để test reproducible
        $seeds = [
            [0, 20], [1, 20], [19, 20], [20, 20], [21, 20],
            [100, 10], [100, 7], [1000, 50], [1000, 100],
            [500, 1], [500, 99], [37, 13], [256, 16],
        ];
        foreach ($seeds as [$n, $p]) {
            $cases["N=$n,P=$p"] = [$n, $p];
        }
        return $cases;
    }
}
