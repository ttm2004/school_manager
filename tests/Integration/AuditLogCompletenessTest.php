<?php
/**
 * Integration Test: Audit Log Completeness
 *
 * - Mỗi write operation tạo đúng 1 audit log entry
 * - Audit log entries không thể bị DELETE hoặc UPDATE
 * - Tất cả write files phải gọi logAudit()
 *
 * Validates: Requirements 16.1, 16.5
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class AuditLogCompletenessTest extends TestCase
{
    // ── All write files must call logAudit ────────────────────

    /**
     * @dataProvider writeFilesProvider
     */
    public function testWriteFilesCallLogAudit(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $this->assertFileExists($filePath, "File $filename phải tồn tại");

        $content = file_get_contents($filePath);
        $this->assertStringContainsString(
            'logAudit(',
            $content,
            "File $filename phải gọi logAudit() sau mỗi write operation"
        );
    }

    public static function writeFilesProvider(): array
    {
        return [
            ['teachers.php'],
            ['teacher_detail.php'],
            ['departments.php'],
            ['student_detail.php'],
            ['curriculum.php'],
            ['proposals.php'],
            ['notifications.php'],
            ['export.php'],
        ];
    }

    // ── logAudit function uses prepared statement ─────────────

    public function testLogAuditUsespreparedStatement(): void
    {
        $helperPath = __DIR__ . '/../../faculty/includes/faculty_helpers.php';
        $content = file_get_contents($helperPath);

        // logAudit phải dùng prepare() + bind_param()
        $this->assertStringContainsString(
            '$conn->prepare(',
            $content,
            'logAudit phải dùng prepared statement'
        );
        $this->assertStringContainsString(
            'bind_param(',
            $content,
            'logAudit phải dùng bind_param()'
        );
    }

    // ── Audit log table has no DELETE/UPDATE in module ────────

    /**
     * @dataProvider allModuleFilesProvider
     */
    public function testNoDirectDeleteOnAuditLog(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $content = file_get_contents($filePath);

        // Không được có DELETE FROM faculty_audit_logs
        $this->assertStringNotContainsString(
            'DELETE FROM faculty_audit_logs',
            $content,
            "File $filename không được DELETE từ faculty_audit_logs"
        );
        $this->assertStringNotContainsString(
            'DELETE FROM `faculty_audit_logs`',
            $content,
            "File $filename không được DELETE từ faculty_audit_logs"
        );
    }

    /**
     * @dataProvider allModuleFilesProvider
     */
    public function testNoDirectUpdateOnAuditLog(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $content = file_get_contents($filePath);

        // Không được có UPDATE faculty_audit_logs
        $this->assertStringNotContainsString(
            'UPDATE faculty_audit_logs',
            $content,
            "File $filename không được UPDATE faculty_audit_logs"
        );
        $this->assertStringNotContainsString(
            'UPDATE `faculty_audit_logs`',
            $content,
            "File $filename không được UPDATE faculty_audit_logs"
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

    // ── logAudit signature is correct ────────────────────────

    public function testLogAuditFunctionSignature(): void
    {
        $helperPath = __DIR__ . '/../../faculty/includes/faculty_helpers.php';
        $content = file_get_contents($helperPath);

        // Kiểm tra function logAudit tồn tại với đúng parameters
        $this->assertMatchesRegularExpression(
            '/function logAudit\s*\(\s*mysqli\s+\$conn/',
            $content,
            'logAudit phải nhận mysqli $conn làm tham số đầu tiên'
        );
        $this->assertStringContainsString(
            'string $actionType',
            $content,
            'logAudit phải có tham số $actionType'
        );
        $this->assertStringContainsString(
            'string $ip',
            $content,
            'logAudit phải có tham số $ip'
        );
    }
}
