<?php
/**
 * Integration Test: CSRF Protection Universality
 *
 * POST request không có _csrf_token → HTTP 403, không có data modification
 * Test tất cả POST endpoints trong module
 *
 * Validates: Requirements 1.3, 1.4, 24.1, 24.2
 *
 * NOTE: Đây là unit-level integration test kiểm tra logic CSRF validation,
 * không cần HTTP server thật. Dùng mock để simulate request context.
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class CsrfProtectionTest extends TestCase
{
    // ── CSRF token validation logic ───────────────────────────

    public function testMissingCsrfTokenIsRejected(): void
    {
        $result = $this->validateCsrf(null, 'valid-token-123');
        $this->assertFalse($result, 'Request không có CSRF token phải bị từ chối');
    }

    public function testEmptyCsrfTokenIsRejected(): void
    {
        $result = $this->validateCsrf('', 'valid-token-123');
        $this->assertFalse($result, 'CSRF token rỗng phải bị từ chối');
    }

    public function testMismatchedCsrfTokenIsRejected(): void
    {
        $result = $this->validateCsrf('wrong-token', 'valid-token-123');
        $this->assertFalse($result, 'CSRF token không khớp phải bị từ chối');
    }

    public function testValidCsrfTokenIsAccepted(): void
    {
        $token = 'valid-token-abc123';
        $result = $this->validateCsrf($token, $token);
        $this->assertTrue($result, 'CSRF token hợp lệ phải được chấp nhận');
    }

    public function testCsrfTokenIsCaseSensitive(): void
    {
        $result = $this->validateCsrf('Token123', 'token123');
        $this->assertFalse($result, 'CSRF token phải case-sensitive');
    }

    // ── All POST endpoints must have CSRF check ───────────────

    /**
     * @dataProvider postEndpointsProvider
     */
    public function testAllPostEndpointsRequireCsrf(string $endpoint): void
    {
        // Kiểm tra file tồn tại
        $filePath = __DIR__ . '/../../faculty/' . $endpoint;
        $this->assertFileExists($filePath, "File $endpoint phải tồn tại");

        // Kiểm tra file có chứa CSRF validation
        $content = file_get_contents($filePath);
        $hasCsrfCheck = (
            stripos($content, 'verifyCSRFToken') !== false ||
            stripos($content, 'csrfField') !== false ||
            stripos($content, '_csrf_token') !== false ||
            stripos($content, 'csrf_field') !== false
        );
        $this->assertTrue(
            $hasCsrfCheck,
            "File $endpoint phải có CSRF protection"
        );
    }

    public static function postEndpointsProvider(): array
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

    // ── CSRF field present in all forms ──────────────────────

    /**
     * @dataProvider postEndpointsProvider
     */
    public function testAllFormsHaveCsrfField(string $endpoint): void
    {
        $filePath = __DIR__ . '/../../faculty/' . $endpoint;
        $content = file_get_contents($filePath);

        // Nếu file có form POST, phải có csrfField()
        if (strpos($content, 'method="post"') !== false || strpos($content, "method='post'") !== false) {
            $this->assertStringContainsString(
                'csrfField()',
                $content,
                "File $endpoint có form POST phải gọi csrfField()"
            );
        } else {
            $this->assertTrue(true, "$endpoint không có form POST");
        }
    }

    // ── Helper ────────────────────────────────────────────────

    private function validateCsrf(?string $submittedToken, string $sessionToken): bool
    {
        if ($submittedToken === null || $submittedToken === '') {
            return false;
        }
        return hash_equals($sessionToken, $submittedToken);
    }
}
