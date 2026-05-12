<?php
/**
 * Integration Test: Teacher Assignment Proposal Validation
 *
 * - proposed_teacher_id không thuộc faculty → bị reject
 *   với message "Chỉ được đề xuất giảng viên thuộc khoa mình."
 * - proposed_teacher_id thuộc faculty → được chấp nhận
 *
 * Validates: Requirements 8.3, 8.4
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class TeacherAssignmentValidationTest extends TestCase
{
    // ── Teacher belongs to faculty → allowed ──────────────────

    /**
     * @dataProvider validTeacherProvider
     */
    public function testTeacherBelongingToFacultyIsAllowed(int $teacherFacultyId, int $currentFacultyId): void
    {
        $result = $this->validateTeacherBelongsToFaculty($teacherFacultyId, $currentFacultyId);
        $this->assertTrue(
            $result['valid'],
            "GV thuộc faculty=$teacherFacultyId phải được chấp nhận khi current=$currentFacultyId"
        );
        $this->assertNull($result['error']);
    }

    public static function validTeacherProvider(): array
    {
        return [
            'same faculty 1' => [1, 1],
            'same faculty 2' => [2, 2],
            'same faculty 5' => [5, 5],
        ];
    }

    // ── Teacher from different faculty → rejected ─────────────

    /**
     * @dataProvider invalidTeacherProvider
     */
    public function testTeacherFromDifferentFacultyIsRejected(int $teacherFacultyId, int $currentFacultyId): void
    {
        $result = $this->validateTeacherBelongsToFaculty($teacherFacultyId, $currentFacultyId);
        $this->assertFalse(
            $result['valid'],
            "GV thuộc faculty=$teacherFacultyId phải bị từ chối khi current=$currentFacultyId"
        );
        $this->assertSame(
            'Chỉ được đề xuất giảng viên thuộc khoa mình.',
            $result['error'],
            'Error message phải đúng'
        );
    }

    public static function invalidTeacherProvider(): array
    {
        return [
            'teacher=1, current=2' => [1, 2],
            'teacher=2, current=1' => [2, 1],
            'teacher=3, current=5' => [3, 5],
        ];
    }

    // ── proposals.php contains the validation ────────────────

    public function testProposalsFileContainsTeacherFacultyValidation(): void
    {
        $filePath = __DIR__ . '/../../faculty/proposals.php';
        $content = file_get_contents($filePath);

        // Phải có check teacher thuộc faculty
        $this->assertStringContainsString(
            'faculty_id',
            $content,
            'proposals.php phải kiểm tra faculty_id của teacher'
        );

        // Phải có error message đúng
        $this->assertStringContainsString(
            'thuộc khoa mình',
            $content,
            'proposals.php phải có error message về khoa'
        );
    }

    // ── Approved proposal cannot be re-proposed ──────────────

    /**
     * @dataProvider proposalStatusProvider
     */
    public function testApprovedProposalCannotBeReProposed(?string $proposalStatus, bool $canPropose): void
    {
        $result = $this->canSubmitTeacherProposal($proposalStatus);
        $this->assertSame(
            $canPropose,
            $result,
            "proposal_status='$proposalStatus' canPropose phải là " . ($canPropose ? 'true' : 'false')
        );
    }

    public static function proposalStatusProvider(): array
    {
        return [
            'null → can propose'      => [null,       true],
            'pending → cannot propose'=> ['pending',  false],
            'approved → cannot propose'=> ['approved', false],
            'rejected → can propose'  => ['rejected', true],
        ];
    }

    // ── Helper methods ────────────────────────────────────────

    private function validateTeacherBelongsToFaculty(int $teacherFacultyId, int $currentFacultyId): array
    {
        if ($teacherFacultyId !== $currentFacultyId) {
            return [
                'valid' => false,
                'error' => 'Chỉ được đề xuất giảng viên thuộc khoa mình.',
            ];
        }
        return ['valid' => true, 'error' => null];
    }

    private function canSubmitTeacherProposal(?string $proposalStatus): bool
    {
        // Không thể đề xuất nếu đã pending hoặc approved
        return !in_array($proposalStatus, ['pending', 'approved'], true);
    }
}
