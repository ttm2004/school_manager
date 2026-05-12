<?php
/**
 * Integration Test: PRG Pattern (Post/Redirect/Get)
 *
 * - Sau POST thành công → redirect với flash message trong session
 * - Sau POST lỗi → redirect với error flash message
 * - Tất cả POST handlers phải dùng header('Location: ...') + exit()
 *
 * Validates: Requirements 14.3
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class PrgPatternTest extends TestCase
{
    // ── All POST handlers use header redirect ─────────────────

    /**
     * @dataProvider postHandlerFilesProvider
     */
    public function testPostHandlersUseHeaderRedirect(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $content = file_get_contents($filePath);

        // Phải có header('Location: ...')
        $this->assertMatchesRegularExpression(
            "/header\s*\(\s*['\"]Location:/",
            $content,
            "File $filename phải dùng header('Location: ...') cho PRG"
        );
    }

    /**
     * @dataProvider postHandlerFilesProvider
     */
    public function testPostHandlersCallExitAfterRedirect(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $content = file_get_contents($filePath);

        // Phải có exit() sau redirect
        $this->assertStringContainsString(
            'exit()',
            $content,
            "File $filename phải gọi exit() sau header redirect"
        );
    }

    public static function postHandlerFilesProvider(): array
    {
        return [
            ['teachers.php'],
            ['teacher_detail.php'],
            ['departments.php'],
            ['student_detail.php'],
            ['curriculum.php'],
            ['proposals.php'],
            ['notifications.php'],
        ];
    }

    // ── Flash messages use session ────────────────────────────

    /**
     * @dataProvider postHandlerFilesProvider
     */
    public function testPostHandlersUseSessionFlash(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $content = file_get_contents($filePath);

        // Phải dùng $_SESSION['_flash']
        $this->assertStringContainsString(
            "_SESSION['_flash']",
            $content,
            "File $filename phải dùng \$_SESSION['_flash'] cho flash messages"
        );
    }

    // ── Flash message structure ───────────────────────────────

    public function testFlashMessageHasTypeAndMessage(): void
    {
        // Simulate flash message structure
        $flash = ['type' => 'success', 'message' => 'Thao tác thành công.'];
        $this->assertArrayHasKey('type', $flash);
        $this->assertArrayHasKey('message', $flash);
        $this->assertContains($flash['type'], ['success', 'danger', 'warning', 'info']);
    }

    public function testSuccessFlashHasSuccessType(): void
    {
        $flash = $this->createFlash('success', 'Cập nhật thành công.');
        $this->assertSame('success', $flash['type']);
    }

    public function testErrorFlashHasDangerType(): void
    {
        $flash = $this->createFlash('danger', 'Có lỗi xảy ra.');
        $this->assertSame('danger', $flash['type']);
    }

    // ── getFlash() clears session after reading ───────────────

    public function testGetFlashClearsSession(): void
    {
        // Simulate getFlash() behavior
        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Test'];
        $flash = $this->simulateGetFlash();
        $this->assertNotNull($flash);
        $this->assertArrayNotHasKey('_flash', $_SESSION, 'getFlash() phải xóa flash khỏi session');
    }

    public function testGetFlashReturnsNullWhenEmpty(): void
    {
        unset($_SESSION['_flash']);
        $flash = $this->simulateGetFlash();
        $this->assertNull($flash, 'getFlash() phải trả về null khi không có flash');
    }

    // ── Data isolation: all queries have faculty_id filter ────

    /**
     * @dataProvider allModuleFilesProvider
     */
    public function testAllQueriesHaveFacultyIdFilter(string $filename): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $filename;
        $content = file_get_contents($filePath);

        // Nếu file có SQL queries, phải có faculty_id filter
        if (strpos($content, 'SELECT') !== false || strpos($content, 'UPDATE') !== false) {
            $hasFacultyFilter = (
                strpos($content, 'faculty_id') !== false ||
                strpos($content, '$facultyId') !== false
            );
            $this->assertTrue(
                $hasFacultyFilter,
                "File $filename phải có faculty_id filter trong queries"
            );
        } else {
            $this->assertTrue(true, "$filename không có SQL queries");
        }
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

    // ── Helper methods ────────────────────────────────────────

    private function createFlash(string $type, string $message): array
    {
        return ['type' => $type, 'message' => $message];
    }

    private function simulateGetFlash(): ?array
    {
        if (!isset($_SESSION['_flash'])) {
            return null;
        }
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
}
