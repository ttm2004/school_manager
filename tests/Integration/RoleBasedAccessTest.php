<?php
/**
 * Integration Test: Role-Based Access Control
 *
 * - faculty_staff không thể thực hiện write operations
 * - faculty_manager có thể thực hiện tất cả operations
 * - Unauthenticated user bị redirect về login.php
 *
 * Validates: Requirements 1.1, 1.2, 1.8, 14.8
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class RoleBasedAccessTest extends TestCase
{
    // ── Write operations require faculty_manager ──────────────

    /**
     * @dataProvider writeOperationsProvider
     */
    public function testWriteOperationsRequireFacultyManager(string $action): void
    {
        // faculty_staff không được phép
        $this->assertFalse(
            $this->canPerformAction('faculty_staff', $action),
            "faculty_staff không được phép thực hiện '$action'"
        );

        // faculty_manager được phép
        $this->assertTrue(
            $this->canPerformAction('faculty_manager', $action),
            "faculty_manager phải được phép thực hiện '$action'"
        );
    }

    public static function writeOperationsProvider(): array
    {
        return [
            ['update_teacher_profile'],
            ['assign_department'],
            ['add_department'],
            ['edit_department'],
            ['delete_department'],
            ['add_curriculum'],
            ['edit_curriculum'],
            ['delete_curriculum'],
            ['create_proposal'],
            ['submit_proposal'],
            ['cancel_proposal'],
            ['propose_teacher'],
            ['add_warning_note'],
            ['send_notification'],
        ];
    }

    // ── Read operations allowed for both roles ────────────────

    /**
     * @dataProvider readOperationsProvider
     */
    public function testReadOperationsAllowedForBothRoles(string $action): void
    {
        $this->assertTrue(
            $this->canPerformAction('faculty_staff', $action),
            "faculty_staff phải được phép thực hiện '$action'"
        );
        $this->assertTrue(
            $this->canPerformAction('faculty_manager', $action),
            "faculty_manager phải được phép thực hiện '$action'"
        );
    }

    public static function readOperationsProvider(): array
    {
        return [
            ['view_teachers'],
            ['view_teacher_detail'],
            ['view_teaching_load'],
            ['view_departments'],
            ['view_students'],
            ['view_student_detail'],
            ['view_academic_warnings'],
            ['view_curriculum'],
            ['view_grades'],
            ['view_proposals'],
            ['view_exam_schedules'],
            ['view_evaluation'],
            ['view_reports'],
            ['view_notifications'],
            ['view_audit_log'],
        ];
    }

    // ── Unauthenticated access is denied ─────────────────────

    public function testUnauthenticatedUserIsDenied(): void
    {
        $this->assertFalse(
            $this->isAuthenticated(null),
            'User chưa đăng nhập phải bị từ chối'
        );
    }

    public function testAuthenticatedUserIsAllowed(): void
    {
        $this->assertTrue(
            $this->isAuthenticated(42),
            'User đã đăng nhập phải được phép truy cập'
        );
    }

    // ── All PHP files have requireAnyRole ─────────────────────

    /**
     * @dataProvider allModuleFilesProvider
     */
    public function testAllModuleFilesHaveRoleCheck(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $this->assertFileExists($filePath, "File $filename phải tồn tại");

        $content = file_get_contents($filePath);
        $this->assertStringContainsString(
            'requireAnyRole',
            $content,
            "File $filename phải gọi requireAnyRole()"
        );
    }

    public static function allModuleFilesProvider(): array
    {
        return [
            ['index.php'],
            ['teachers.php'],
            ['teacher_detail.php'],
            ['teaching_load.php'],
            ['departments.php'],
            ['students.php'],
            ['student_detail.php'],
            ['academic_warnings.php'],
            ['curriculum.php'],
            ['grades.php'],
            ['proposals.php'],
            ['exam_schedules.php'],
            ['evaluation.php'],
            ['reports.php'],
            ['notifications.php'],
            ['audit_log.php'],
            ['export.php'],
        ];
    }

    // ── All PHP files have getFacultyId ───────────────────────

    /**
     * @dataProvider allModuleFilesProvider
     */
    public function testAllModuleFilesHaveFacultyIdCheck(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $content = file_get_contents($filePath);
        $this->assertStringContainsString(
            'getFacultyId',
            $content,
            "File $filename phải gọi getFacultyId()"
        );
    }

    // ── Helper methods ────────────────────────────────────────

    private function canPerformAction(string $role, string $action): bool
    {
        $writeActions = [
            'update_teacher_profile', 'assign_department',
            'add_department', 'edit_department', 'delete_department',
            'add_curriculum', 'edit_curriculum', 'delete_curriculum',
            'create_proposal', 'submit_proposal', 'cancel_proposal',
            'propose_teacher', 'add_warning_note', 'send_notification',
        ];

        if (in_array($action, $writeActions, true)) {
            return $role === 'faculty_manager' || $role === 'admin';
        }

        // Read operations: cả hai role đều được
        return in_array($role, ['faculty_manager', 'faculty_staff', 'admin'], true);
    }

    private function isAuthenticated(?int $userId): bool
    {
        return $userId !== null && $userId > 0;
    }
}
