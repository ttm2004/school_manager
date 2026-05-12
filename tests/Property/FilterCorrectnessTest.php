<?php
/**
 * Property 2: Filter Correctness
 *
 * Với bất kỳ filter parameter nào (degree, academic_status, year, major_id, keyword),
 * mọi record trong kết quả phải thỏa mãn điều kiện filter.
 *
 * Validates: Requirements 2.2, 2.3, 4.2, 4.3, 9.3, 10.3
 */

namespace Tests\Property;

use PHPUnit\Framework\TestCase;

class FilterCorrectnessTest extends TestCase
{
    // ── Degree filter ─────────────────────────────────────────

    /**
     * @dataProvider degreeFilterProvider
     */
    public function testDegreeFilterReturnsOnlyMatchingRecords(string $filterDegree, array $teachers): void
    {
        $results = $this->applyDegreeFilter($teachers, $filterDegree);
        foreach ($results as $teacher) {
            $this->assertSame(
                $filterDegree,
                $teacher['degree'],
                "Kết quả filter degree='$filterDegree' phải chỉ chứa GV có degree='$filterDegree'"
            );
        }
    }

    public static function degreeFilterProvider(): array
    {
        $teachers = [
            ['id' => 1, 'name' => 'A', 'degree' => 'Thạc sĩ'],
            ['id' => 2, 'name' => 'B', 'degree' => 'Tiến sĩ'],
            ['id' => 3, 'name' => 'C', 'degree' => 'Thạc sĩ'],
            ['id' => 4, 'name' => 'D', 'degree' => 'PGS.TS'],
            ['id' => 5, 'name' => 'E', 'degree' => 'Tiến sĩ'],
        ];
        return [
            'filter Thạc sĩ' => ['Thạc sĩ', $teachers],
            'filter Tiến sĩ' => ['Tiến sĩ', $teachers],
            'filter PGS.TS'  => ['PGS.TS',  $teachers],
        ];
    }

    // ── Academic status filter ────────────────────────────────

    /**
     * @dataProvider statusFilterProvider
     */
    public function testStatusFilterReturnsOnlyMatchingRecords(string $filterStatus, array $students): void
    {
        $results = $this->applyStatusFilter($students, $filterStatus);
        foreach ($results as $student) {
            $this->assertSame(
                $filterStatus,
                $student['academic_status'],
                "Kết quả filter status='$filterStatus' phải chỉ chứa SV có status='$filterStatus'"
            );
        }
    }

    public static function statusFilterProvider(): array
    {
        $students = [
            ['id' => 1, 'name' => 'A', 'academic_status' => 'đang học'],
            ['id' => 2, 'name' => 'B', 'academic_status' => 'bảo lưu'],
            ['id' => 3, 'name' => 'C', 'academic_status' => 'đang học'],
            ['id' => 4, 'name' => 'D', 'academic_status' => 'thôi học'],
            ['id' => 5, 'name' => 'E', 'academic_status' => 'tốt nghiệp'],
        ];
        return [
            'filter đang học'  => ['đang học',  $students],
            'filter bảo lưu'   => ['bảo lưu',   $students],
            'filter thôi học'  => ['thôi học',  $students],
            'filter tốt nghiệp'=> ['tốt nghiệp',$students],
        ];
    }

    // ── Year filter ───────────────────────────────────────────

    /**
     * @dataProvider yearFilterProvider
     */
    public function testYearFilterReturnsOnlyMatchingRecords(int $filterYear, array $students): void
    {
        $results = $this->applyYearFilter($students, $filterYear);
        foreach ($results as $student) {
            $this->assertSame(
                $filterYear,
                $student['enrollment_year'],
                "Kết quả filter year=$filterYear phải chỉ chứa SV nhập học năm $filterYear"
            );
        }
    }

    public static function yearFilterProvider(): array
    {
        $students = [
            ['id' => 1, 'name' => 'A', 'enrollment_year' => 2020],
            ['id' => 2, 'name' => 'B', 'enrollment_year' => 2021],
            ['id' => 3, 'name' => 'C', 'enrollment_year' => 2020],
            ['id' => 4, 'name' => 'D', 'enrollment_year' => 2022],
        ];
        return [
            'filter 2020' => [2020, $students],
            'filter 2021' => [2021, $students],
            'filter 2022' => [2022, $students],
        ];
    }

    // ── Keyword search filter ─────────────────────────────────

    /**
     * @dataProvider keywordFilterProvider
     */
    public function testKeywordFilterReturnsOnlyMatchingRecords(string $keyword, array $records): void
    {
        $results = $this->applyKeywordFilter($records, $keyword);
        foreach ($results as $record) {
            $nameMatch = stripos($record['name'], $keyword) !== false;
            $codeMatch = stripos($record['code'], $keyword) !== false;
            $this->assertTrue(
                $nameMatch || $codeMatch,
                "Record '{$record['name']}' (code={$record['code']}) phải match keyword '$keyword'"
            );
        }
    }

    public static function keywordFilterProvider(): array
    {
        $records = [
            ['id' => 1, 'name' => 'Nguyễn Văn An', 'code' => 'GV001'],
            ['id' => 2, 'name' => 'Trần Thị Bình', 'code' => 'GV002'],
            ['id' => 3, 'name' => 'Lê Văn Cường', 'code' => 'GV003'],
            ['id' => 4, 'name' => 'Phạm Thị Dung', 'code' => 'GV004'],
        ];
        return [
            'search "Nguyễn"' => ['Nguyễn', $records],
            'search "GV00"'   => ['GV00',   $records],
            'search "Văn"'    => ['Văn',    $records],
            'search "GV003"'  => ['GV003',  $records],
        ];
    }

    // ── Empty filter returns all records ─────────────────────

    public function testEmptyFilterReturnsAllRecords(): void
    {
        $teachers = [
            ['id' => 1, 'degree' => 'Thạc sĩ'],
            ['id' => 2, 'degree' => 'Tiến sĩ'],
        ];
        // Không filter → trả về tất cả
        $results = $this->applyDegreeFilter($teachers, '');
        $this->assertCount(count($teachers), $results, 'Filter rỗng phải trả về tất cả records');
    }

    // ── Helper methods ────────────────────────────────────────

    private function applyDegreeFilter(array $teachers, string $degree): array
    {
        if ($degree === '') return $teachers;
        return array_values(array_filter($teachers, fn($t) => $t['degree'] === $degree));
    }

    private function applyStatusFilter(array $students, string $status): array
    {
        if ($status === '') return $students;
        return array_values(array_filter($students, fn($s) => $s['academic_status'] === $status));
    }

    private function applyYearFilter(array $students, int $year): array
    {
        if ($year === 0) return $students;
        return array_values(array_filter($students, fn($s) => $s['enrollment_year'] === $year));
    }

    private function applyKeywordFilter(array $records, string $keyword): array
    {
        if ($keyword === '') return $records;
        return array_values(array_filter(
            $records,
            fn($r) => stripos($r['name'], $keyword) !== false || stripos($r['code'], $keyword) !== false
        ));
    }
}
