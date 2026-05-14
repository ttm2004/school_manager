<?php
/**
 * NotificationService — Abstraction layer cho notifications
 *
 * Dùng:
 *   NotificationService::send($conn, $userId, 'grade_reminder', 'Tiêu đề', 'Nội dung');
 *   NotificationService::sendBulk($conn, $userIds, 'semester_open', ...);
 *   NotificationService::sendToRole($conn, 'teacher', ...);
 *
 * Channels: database (luôn bật), email (nếu cấu hình), push (future)
 */

class NotificationService
{
    // ── Gửi cho 1 user ───────────────────────────────────────

    public static function send(
        mysqli  $conn,
        int     $userId,
        string  $type,
        string  $title,
        string  $content,
        array   $meta    = [],   // ['ref_id'=>1, 'ref_type'=>'course_section']
        array   $channels = ['database']
    ): bool {
        $ok = true;

        if (in_array('database', $channels)) {
            $ok = self::saveToDatabase($conn, $userId, $title, $content, $type, $meta);
        }

        if (in_array('email', $channels) && config('mail.host')) {
            self::sendEmail($conn, $userId, $title, $content);
        }

        // Future: push, websocket, firebase
        // if (in_array('push', $channels)) self::sendPush(...);

        return $ok;
    }

    // ── Gửi cho nhiều user ────────────────────────────────────

    public static function sendBulk(
        mysqli $conn,
        array  $userIds,
        string $type,
        string $title,
        string $content,
        array  $meta     = [],
        array  $channels = ['database']
    ): int {
        $count = 0;
        foreach (array_unique($userIds) as $uid) {
            if (self::send($conn, (int)$uid, $type, $title, $content, $meta, $channels)) {
                $count++;
            }
        }
        return $count;
    }

    // ── Gửi theo role ─────────────────────────────────────────

    public static function sendToRole(
        mysqli $conn,
        string $role,       // 'teacher', 'student', 'staff', hoặc role code
        string $type,
        string $title,
        string $content,
        array  $meta     = [],
        array  $channels = ['database'],
        ?int   $facultyId = null  // null = toàn trường
    ): int {
        $userIds = self::getUserIdsByRole($conn, $role, $facultyId);
        return self::sendBulk($conn, $userIds, $type, $title, $content, $meta, $channels);
    }

    // ── Gửi theo faculty ──────────────────────────────────────

    public static function sendToFaculty(
        mysqli $conn,
        int    $facultyId,
        string $type,
        string $title,
        string $content,
        string $targetGroup = 'all', // 'all' | 'teachers' | 'students'
        array  $channels    = ['database']
    ): int {
        $userIds = [];

        if (in_array($targetGroup, ['all', 'teachers'])) {
            $res = $conn->prepare(
                "SELECT u.id FROM teachers t JOIN users u ON t.user_id=u.id
                 WHERE t.faculty_id=? AND u.status=1"
            );
            $res->bind_param('i', $facultyId);
            $res->execute();
            foreach ($res->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $userIds[] = $r['id'];
            }
            $res->close();
        }

        if (in_array($targetGroup, ['all', 'students'])) {
            $res = $conn->prepare(
                "SELECT u.id FROM students s
                 JOIN users u ON s.user_id=u.id
                 JOIN classes cl ON s.class_id=cl.id
                 JOIN majors m ON cl.major_id=m.id
                 WHERE m.faculty_id=? AND u.status=1 AND s.academic_status='Đang học'"
            );
            $res->bind_param('i', $facultyId);
            $res->execute();
            foreach ($res->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $userIds[] = $r['id'];
            }
            $res->close();
        }

        return self::sendBulk($conn, $userIds, $type, $title, $content, [], $channels);
    }

    // ── Lấy unread count cho user ─────────────────────────────

    public static function getUnreadCount(mysqli $conn, int $userId): int
    {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM system_notifications WHERE user_id=? AND is_read=0"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        return $c;
    }

    // ── Mark as read ──────────────────────────────────────────

    public static function markRead(mysqli $conn, int $userId, ?int $notifId = null): void
    {
        if ($notifId) {
            $stmt = $conn->prepare("UPDATE system_notifications SET is_read=1 WHERE id=? AND user_id=?");
            $stmt->bind_param('ii', $notifId, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE system_notifications SET is_read=1 WHERE user_id=?");
            $stmt->bind_param('i', $userId);
        }
        $stmt->execute();
        $stmt->close();
    }

    // ── Private: lưu vào DB ───────────────────────────────────

    private static function saveToDatabase(
        mysqli $conn,
        int    $userId,
        string $title,
        string $content,
        string $type    = 'general',
        array  $meta    = []
    ): bool {
        // Kiểm tra cột type có tồn tại không (backward compat)
        $hasType = $conn->query("SHOW COLUMNS FROM `system_notifications` LIKE 'type'")->num_rows > 0;

        if ($hasType) {
            $stmt = $conn->prepare(
                "INSERT INTO system_notifications (user_id, title, content, is_read)
                 VALUES (?, ?, ?, 0)"
            );
            $stmt->bind_param('iss', $userId, $title, $content);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO system_notifications (user_id, title, content, is_read)
                 VALUES (?, ?, ?, 0)"
            );
            $stmt->bind_param('iss', $userId, $title, $content);
        }

        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ── Private: gửi email ────────────────────────────────────

    private static function sendEmail(
        mysqli $conn,
        int    $userId,
        string $subject,
        string $body
    ): void {
        // Lấy email của user
        $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id=? AND status=1 LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || empty($user['email'])) return;

        // Dùng PHP mail() hoặc SMTP library
        // TODO: tích hợp PHPMailer khi cần
        $headers  = "From: " . config('mail.name') . " <" . config('mail.from') . ">\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        $htmlBody = "
        <html><body style='font-family:sans-serif;max-width:600px;margin:0 auto'>
            <div style='background:#003087;color:#fff;padding:20px;border-radius:8px 8px 0 0'>
                <h2 style='margin:0'>TDMU — Thông báo</h2>
            </div>
            <div style='padding:20px;border:1px solid #eee;border-top:none;border-radius:0 0 8px 8px'>
                <p>Xin chào <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                <p>" . nl2br(htmlspecialchars($body)) . "</p>
                <hr>
                <p style='color:#999;font-size:.8rem'>Email tự động từ hệ thống TDMU. Vui lòng không reply.</p>
            </div>
        </body></html>";

        @mail($user['email'], $subject, $htmlBody, $headers);
    }

    // ── Private: lấy user IDs theo role ──────────────────────

    private static function getUserIdsByRole(
        mysqli $conn,
        string $role,
        ?int   $facultyId = null
    ): array {
        $userIds = [];

        // Role hệ thống: teacher, student, staff, admin
        if (in_array($role, ['teacher', 'student', 'staff', 'admin'])) {
            $sql = "SELECT id FROM users WHERE role=? AND status=1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $role);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $userIds[] = $r['id'];
            }
            $stmt->close();
        } else {
            // Role code từ bảng roles (academic_manager, faculty_staff...)
            $stmt = $conn->prepare(
                "SELECT u.id FROM user_roles ur
                 JOIN roles r ON ur.role_id=r.id
                 JOIN users u ON ur.user_id=u.id
                 WHERE r.code=? AND r.is_active=1 AND u.status=1
                   AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
            );
            $stmt->bind_param('s', $role);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $userIds[] = $r['id'];
            }
            $stmt->close();
        }

        return $userIds;
    }
}
