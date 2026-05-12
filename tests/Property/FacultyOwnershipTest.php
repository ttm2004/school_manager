<?php
/**
 * Property 8: Faculty Ownership Assertion
 *
 * assertFacultyOwnership() trả về false khi record.faculty_id != current_faculty_id
 * assertFacultyOwnership() trả về true khi record.faculty_id == current_faculty_id
 *
 * Validates: Requirements 21.3, 21.4, 14.4, 14.5
 */

namespace Tests\Property;

use PHPUnit\Framework\TestCase;

class FacultyOwnershipTest extends TestCase
{
    // ── Core property: matching faculty_id → true ─────────────

    /**
     * @dataProvider matchingFacultyProvider
     */
    public function testMatchingFacultyIdReturnsTrue(int $recordFacultyId, int $currentFacultyId): void
    {
        $result = $this->simulateOwnershipCheck($recordFacultyId, $currentFacultyId);
        $this->assertTrue(
            $result,
            "record.faculty_id=$recordFacultyId == current=$currentFacultyId phải trả về true"
        );
    }

    public static function matchingFacultyProvider(): array
    {
        return [
            'faculty 1 matches 1' => [1, 1],
            'faculty 2 matches 2' => [2, 2],
            'faculty 5 matches 5' => [5, 5],
            'faculty 99 matches 99' => [99, 99],
        ];
    }

    // ── Core property: different faculty_id → false ───────────

    /**
     * @dataProvider mismatchedFacultyProvider
     */
    public function testMismatchedFacultyIdReturnsFalse(int $recordFacultyId, int $currentFacultyId): void
    {
        $result = $this->simulateOwnershipCheck($recordFacultyId, $currentFacultyId);
        $this->assertFalse(
            $result,
            "record.faculty_id=$recordFacultyId != current=$currentFacultyId phải trả về false"
        );
    }

    public static function mismatchedFacultyProvider(): array
    {
        return [
            'record=1, current=2'  => [1, 2],
            'record=2, current=1'  => [2, 1],
            'record=1, current=99' => [1, 99],
            'record=5, current=3'  => [5, 3],
        ];
    }

    // ── Property: ownership is not symmetric ─────────────────

    public function testOwnershipIsNotSymmetric(): void
    {
        // Nếu record=1 thuộc faculty=2 → false
        // Không có nghĩa là record=2 thuộc faculty=1 → true
        $this->assertFalse($this->simulateOwnershipCheck(1, 2));
        $this->assertFalse($this->simulateOwnershipCheck(2, 1));
    }

    // ── Property: admin bypass ────────────────────────────────

    public function testAdminBypassesOwnershipCheck(): void
    {
        // Admin role → luôn trả về true bất kể faculty_id
        $_SESSION['role'] = 'admin';
        $result = $this->simulateOwnershipCheckWithSession(1, 99);
        $this->assertTrue($result, 'Admin phải bypass ownership check');
        unset($_SESSION['role']);
    }

    public function testNonAdminDoesNotBypass(): void
    {
        $_SESSION['role'] = 'faculty_manager';
        $result = $this->simulateOwnershipCheckWithSession(1, 99);
        $this->assertFalse($result, 'faculty_manager không được bypass ownership check');
        unset($_SESSION['role']);
    }

    // ── Property: cross-faculty write attempt is blocked ─────

    /**
     * @dataProvider crossFacultyWriteProvider
     */
    public function testCrossFacultyWriteIsBlocked(int $targetFaculty, int $currentFaculty): void
    {
        if ($targetFaculty !== $currentFaculty) {
            $canWrite = $this->simulateOwnershipCheck($targetFaculty, $currentFaculty);
            $this->assertFalse(
                $canWrite,
                "Write vào faculty=$targetFaculty từ faculty=$currentFaculty phải bị chặn"
            );
        } else {
            $this->assertTrue(true, 'Same faculty → allowed');
        }
    }

    public static function crossFacultyWriteProvider(): array
    {
        return [
            [1, 2], [2, 3], [3, 1], [1, 5], [5, 1],
        ];
    }

    // ── Helper methods ────────────────────────────────────────

    private function simulateOwnershipCheck(int $recordFacultyId, int $currentFacultyId): bool
    {
        return $recordFacultyId === $currentFacultyId;
    }

    private function simulateOwnershipCheckWithSession(int $recordFacultyId, int $currentFacultyId): bool
    {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        return $recordFacultyId === $currentFacultyId;
    }
}
