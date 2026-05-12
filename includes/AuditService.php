<?php
/**
 * AuditService — Enterprise audit logging
 *
 * Dùng:
 *   AuditService::log($conn, 'update', 'course_section', 42, $old, $new);
 *   AuditService::logAuth($conn, 'login_success', $userId);
 *
 * Schema bảng audit_logs (chuẩn):
 *   id, user_id, user_role, action, entity_type, entity_id,
 *   old_data (JSON), new_data (JSON),
 *   ip, user_agent, request_id, module, created_at
 */

class AuditService
{
    private static ?string $requestId = null;

    // ── Log thao tác ─────────────────────────────────────────

    public static function log(
        mysqli  $conn,
        string  $action,      // create|update|delete|view|export|approve|reject|login|logout
        string  $entityType,  // course_section|grade|user|semester|...
        int     $entityId,
        mixed   $oldData  = null,
        mixed   $newData  = null,
        string  $module   = 'system'
    ): void {
        $userId    = (int)($_SESSION['user_id'] ?? 0);
        $userRole  = self::getCurrentRole();
        $ip        = self::getClientIp();
        $ua        = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $requestId = self::getRequestId();

        $oldJson = $oldData !== null ? (is_string($oldData) ? $oldData : json_encode($oldData, JSON_UNESCAPED_UNICODE)) : null;
        $newJson = $newData !== null ? (is_string($newData) ? $newData : json_encode($newData, JSON_UNESCAPED_UNICODE)) : null;

        // Thử ghi vào audit_logs chuẩn trước
        $chk = $conn->query("SHOW TABLES LIKE 'audit_logs'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = $conn->prepare(
                "INSERT INTO audit_logs
                 (user_id, user_role, action, entity_type, entity_id,
                  old_data, new_data, ip, user_agent, request_id, module)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            );
            if ($stmt) {
                $stmt->bind_param(
                    'isssiissss s',
                    $userId, $userRole, $action, $entityType, $entityId,
                    $oldJson, $newJson, $ip, $ua, $requestId, $module
                );
                $stmt->execute();
                $stmt->close();
                return;
            }
        }

        // Fallback: ghi vào faculty_audit_logs nếu có
        $chk2 = $conn->query("SHOW TABLES LIKE 'faculty_audit_logs'");
        if ($chk2 && $chk2->num_rows > 0) {
            $stmt = $conn->prepare(
                "INSERT INTO faculty_audit_logs
                 (user_id, actor_role, action_type, module, table_name, record_id, old_data, new_data, ip_address)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            if ($stmt) {
                $stmt->bind_param(
                    'isssissss',
                    $userId, $userRole, $action, $module, $entityType, $entityId,
                    $oldJson, $newJson, $ip
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // ── Log auth events ───────────────────────────────────────

    public static function logAuth(
        mysqli $conn,
        string $event,   // login_success|login_failed|logout|password_change
        int    $userId,
        string $detail = ''
    ): void {
        self::log($conn, $event, 'user', $userId, null, $detail ?: null, 'auth');
    }

    // ── Log bulk operations ───────────────────────────────────

    public static function logBulk(
        mysqli $conn,
        string $action,
        string $entityType,
        array  $entityIds,
        string $module = 'system'
    ): void {
        self::log($conn, $action, $entityType, 0,
            null,
            json_encode(['ids' => $entityIds, 'count' => count($entityIds)]),
            $module
        );
    }

    // ── Lấy audit history cho 1 entity ───────────────────────

    public static function getHistory(
        mysqli $conn,
        string $entityType,
        int    $entityId,
        int    $limit = 20
    ): array {
        // Thử audit_logs chuẩn
        $chk = $conn->query("SHOW TABLES LIKE 'audit_logs'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = $conn->prepare(
                "SELECT al.*, u.full_name AS user_name
                 FROM audit_logs al
                 LEFT JOIN users u ON al.user_id = u.id
                 WHERE al.entity_type=? AND al.entity_id=?
                 ORDER BY al.created_at DESC LIMIT ?"
            );
            $stmt->bind_param('sii', $entityType, $entityId, $limit);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }

        // Fallback: faculty_audit_logs
        $chk2 = $conn->query("SHOW TABLES LIKE 'faculty_audit_logs'");
        if ($chk2 && $chk2->num_rows > 0) {
            $stmt = $conn->prepare(
                "SELECT al.*, u.full_name AS user_name
                 FROM faculty_audit_logs al
                 LEFT JOIN users u ON al.user_id = u.id
                 WHERE al.table_name=? AND al.record_id=?
                 ORDER BY al.created_at DESC LIMIT ?"
            );
            $stmt->bind_param('sii', $entityType, $entityId, $limit);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }

        return [];
    }

    // ── Private helpers ───────────────────────────────────────

    private static function getCurrentRole(): string
    {
        $role = $_SESSION['role'] ?? '';
        // Ưu tiên role phòng ban cụ thể nhất
        if (!empty($_SESSION['_user_role_codes'])) {
            $priority = ['faculty_manager', 'academic_manager', 'faculty_staff', 'academic_staff', 'dept_head'];
            foreach ($priority as $p) {
                foreach ($_SESSION['_user_role_codes'] as $code) {
                    if (str_starts_with($code, $p)) return $code;
                }
            }
            return $_SESSION['_user_role_codes'][0] ?? $role;
        }
        return $role;
    }

    private static function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    private static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = $_SERVER['HTTP_X_REQUEST_ID']
                ?? substr(bin2hex(random_bytes(8)), 0, 16);
        }
        return self::$requestId;
    }
}
