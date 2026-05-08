<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isLoggedIn() && $_SESSION['role'] === 'student';
}

function isTeacher() {
    return isLoggedIn() && $_SESSION['role'] === 'teacher';
}

function isStaff() {
    return isLoggedIn() && $_SESSION['role'] === 'staff';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /university/login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        switch ($_SESSION['role']) {
            case 'admin':   header('Location: /university/admin/');   break;
            case 'student': header('Location: /university/student/'); break;
            case 'teacher': header('Location: /university/teacher/'); break;
            case 'staff':   header('Location: /university/no_access.php'); break;
            default:        header('Location: /university/login.php');
        }
        exit();
    }
}

// ============================================================
// RBAC - Role-Based Access Control
// ============================================================

/**
 * Kiểm tra user có role cụ thể không
 * Admin luôn có tất cả quyền
 */
function hasRole(string $roleCode): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return true;

    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $code = $conn->real_escape_string($roleCode);
    $result = $conn->query("
        SELECT ur.id FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = $uid
          AND r.code = '$code'
          AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        LIMIT 1
    ");
    return $result && $result->num_rows > 0;
}

/**
 * Kiểm tra user có quyền cụ thể trong module không
 * Admin luôn có tất cả quyền
 */
function hasPermission(string $module, string $permission): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return true;

    global $conn;
    $uid  = (int)$_SESSION['user_id'];
    $mod  = $conn->real_escape_string($module);
    $perm = $conn->real_escape_string($permission);
    $result = $conn->query("
        SELECT rp.id FROM role_permissions rp
        JOIN user_roles ur ON rp.role_id = ur.role_id
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = $uid
          AND rp.module = '$mod'
          AND rp.permission = '$perm'
          AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        LIMIT 1
    ");
    return $result && $result->num_rows > 0;
}

/**
 * Lấy tất cả roles của user hiện tại
 */
function getUserRoles(): array {
    if (!isLoggedIn()) return [];
    if ($_SESSION['role'] === 'admin') return [['code'=>'admin','name'=>'Quản trị viên','department'=>'Ban Giám hiệu']];

    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("
        SELECT r.code, r.name, r.department, r.color
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = $uid
          AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY r.department
    ");
    $roles = [];
    if ($result) while ($row = $result->fetch_assoc()) $roles[] = $row;
    return $roles;
}

/**
 * Yêu cầu user phải có ít nhất 1 trong các role được liệt kê
 * Dùng cho các trang module riêng
 */
function requireAnyRole(array $roleCodes): void {
    requireLogin();
    // admin và staff đều có thể vào nếu có role phù hợp
    if (in_array($_SESSION['role'], ['admin', 'staff'])) {
        if ($_SESSION['role'] === 'admin') return; // admin có tất cả
        foreach ($roleCodes as $code) {
            if (hasRole($code)) return;
        }
    }
    // Không có quyền
    http_response_code(403);
    die('
    <div style="font-family:sans-serif;text-align:center;padding:80px 20px;">
        <h2 style="color:#dc3545;">⛔ Không có quyền truy cập</h2>
        <p style="color:#6c757d;">Bạn không có quyền vào trang này. Vui lòng liên hệ quản trị viên.</p>
        <a href="/university/login.php" style="color:#0d6efd;">← Quay lại trang chủ</a>
    </div>');
}

/**
 * Ghi log truy cập — gọi sau khi set session thành công
 * Mỗi session_id chỉ ghi 1 lần (UNIQUE KEY)
 */
function logVisit($conn): void {
    if (!isLoggedIn()) return;
    $sid  = session_id();
    $uid  = (int)$_SESSION['user_id'];
    $role = $conn->real_escape_string($_SESSION['role'] ?? '');
    $ip   = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
    $ua   = $conn->real_escape_string(mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500));
    // INSERT IGNORE — nếu session đã tồn tại thì chỉ update last_seen
    $conn->query("INSERT INTO visit_logs (user_id, role, session_id, ip, user_agent)
        VALUES ($uid, '$role', '$sid', '$ip', '$ua')
        ON DUPLICATE KEY UPDATE last_seen=NOW(), user_id=$uid, role='$role'");
}

/**
 * Lấy thống kê truy cập theo phân quyền của user hiện tại.
 * Chỉ đếm session có last_seen trong vòng ONLINE_MINUTES phút gần nhất
 * (= trình duyệt/thiết bị đang thực sự sử dụng hệ thống).
 */
function getVisitStats($conn): array {
    if (!isLoggedIn()) return [];

    // Ngưỡng "đang online" — session active trong 15 phút gần nhất
    $ONLINE_MINUTES = 15;
    $onlineCond     = "last_seen >= NOW() - INTERVAL $ONLINE_MINUTES MINUTE";

    $role   = $_SESSION['role'] ?? '';
    $userId = (int)$_SESSION['user_id'];

    // Helper: đếm session online với điều kiện bổ sung
    $count = function(string $extra = '') use ($conn, $onlineCond): int {
        $where = $extra ? "$onlineCond AND ($extra)" : $onlineCond;
        $r = $conn->query("SELECT COUNT(*) c FROM visit_logs WHERE $where");
        return $r ? (int)$r->fetch_assoc()['c'] : 0;
    };

    // ── Admin chung — xem tất cả role ──────────────────────
    if ($role === 'admin') {
        return [
            'level'   => 'admin',
            'total'   => $count(),
            'by_role' => [
                'admin'   => $count("role='admin'"),
                'teacher' => $count("role='teacher'"),
                'student' => $count("role='student'"),
                'staff'   => $count("role='staff'"),
            ],
        ];
    }

    // ── Staff — xem tổng + SV + GV (không xem admin) ───────
    if ($role === 'staff') {
        return [
            'level'    => 'staff',
            'total'    => $count("role IN ('student','teacher','staff')"),
            'students' => $count("role='student'"),
            'teachers' => $count("role='teacher'"),
        ];
    }

    // ── Teacher / Student — chỉ xem tổng + SV + GV ─────────
    return [
        'level'    => $role,
        'total'    => $count("role IN ('student','teacher')"),
        'students' => $count("role='student'"),
        'teachers' => $count("role='teacher'"),
    ];
}
// ============================================================
// PRG HELPERS — Post/Redirect/Get pattern
// ============================================================

/**
 * Lấy flash message từ session (xóa sau khi đọc)
 */
function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
    return null;
}

/**
 * Kiểm tra sinh viên có nợ học phí không
 * Trả về true nếu có hóa đơn unpaid/partial/overdue
 */
function hasTuitionDebt(int $studentId): bool {
    global $conn;
    // Kiểm tra bảng tồn tại trước
    $tbl = $conn->query("SHOW TABLES LIKE 'tuition_invoices'");
    if (!$tbl || $tbl->num_rows === 0) return false;
    $res = $conn->prepare("SELECT id FROM tuition_invoices WHERE student_id=? AND status IN ('unpaid','partial','overdue') LIMIT 1");
    if (!$res) return false;
    $res->bind_param('i', $studentId);
    $res->execute();
    $has = $res->get_result()->num_rows > 0;
    $res->close();
    return $has;
}

function cacheUserRoles(): void {
    if (!isLoggedIn() || isset($_SESSION['_roles_cached'])) return;
    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("
        SELECT r.code FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = $uid AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
    ");
    $_SESSION['_user_role_codes'] = [];
    if ($result) while ($row = $result->fetch_assoc()) $_SESSION['_user_role_codes'][] = $row['code'];
    $_SESSION['_roles_cached'] = true;
}

// ============================================================
// ADMISSION ROUND HELPERS — Kiểm tra thời gian đợt tuyển sinh
// ============================================================

/**
 * Lấy đợt tuyển sinh đang active (ưu tiên năm hiện tại)
 * Trả về array hoặc null nếu không có
 */
function getActiveRound(): ?array {
    global $conn;
    $result = $conn->query("
        SELECT * FROM admission_rounds
        WHERE status NOT IN ('draft','completed')
        ORDER BY year DESC LIMIT 1
    ");
    if (!$result || $result->num_rows === 0) return null;
    return $result->fetch_assoc();
}

/**
 * Lấy trạng thái thời gian hiện tại của đợt tuyển sinh
 * Ưu tiên status được set thủ công, fallback về check thời gian
 */
function getRoundPhase(): string {
    $round = getActiveRound();
    if (!$round) return 'no_round';

    // Ưu tiên status được set thủ công bởi admin
    $statusMap = [
        'open'          => 'reg_open',
        'reviewing'     => 'reviewing',
        'enrolling'     => 'enrolling',
        'supplementary' => 'supp_reg_open',
        'completed'     => 'completed',
    ];
    if (isset($statusMap[$round['status']])) {
        return $statusMap[$round['status']];
    }

    // Fallback: tính theo thời gian thực
    $now = time();

    if ($round['status'] === 'completed') return 'completed';

    // Đợt bổ sung
    if ($round['supp_reg_start']) {
        $suppRegStart = strtotime($round['supp_reg_start']);
        $suppRegEnd   = strtotime($round['supp_reg_end']);
        $suppRevEnd   = strtotime($round['supp_review_end']);
        $suppEnroll   = strtotime($round['supp_enroll_deadline']);

        if ($now >= $suppRegStart && $now <= $suppRegEnd)  return 'supp_reg_open';
        if ($now > $suppRegEnd   && $now <= $suppRevEnd)   return 'supp_reviewing';
        if ($now > $suppRevEnd   && $now <= $suppEnroll)   return 'supp_enrolling';
        if ($now > $suppEnroll)                            return 'completed';
    }

    // Đợt chính
    $regStart       = strtotime($round['reg_start']);
    $regEnd         = strtotime($round['reg_end']);
    $revEnd         = strtotime($round['review_end']);
    $enrollDeadline = strtotime($round['enroll_deadline']);

    if ($now < $regStart)                              return 'before_reg';
    if ($now >= $regStart && $now <= $regEnd)          return 'reg_open';
    if ($now > $regEnd   && $now <= $revEnd)           return 'reviewing';
    if ($now > $revEnd   && $now <= $enrollDeadline)   return 'enrolling';
    if ($now > $enrollDeadline)                        return 'after_enroll';

    return 'no_round';
}

/**
 * Kiểm tra có đang trong giai đoạn xét tuyển không
 * (reviewing hoặc supp_reviewing)
 * Trong giai đoạn này: khóa tác vụ thủ công của nhân viên
 */
function isReviewingPhase(): bool {
    $phase = getRoundPhase();
    return in_array($phase, ['reviewing', 'supp_reviewing']);
}

/**
 * Kiểm tra form đăng ký công khai có được mở không
 */
function isRegistrationOpen(): bool {
    $phase = getRoundPhase();
    return in_array($phase, ['reg_open', 'supp_reg_open']);
}

/**
 * Kiểm tra có phải đợt bổ sung không
 */
function isSupplementaryPhase(): bool {
    $phase = getRoundPhase();
    return in_array($phase, ['supp_reg_open', 'supp_reviewing', 'supp_enrolling']);
}

/**
 * Lấy thông báo trạng thái hiện tại để hiển thị cho nhân viên
 */
function getRoundStatusMessage(): array {
    $round = getActiveRound();
    $phase = getRoundPhase();

    $messages = [
        'no_round'       => ['warning', 'Chưa có đợt tuyển sinh nào được cấu hình.'],
        'before_reg'     => ['info',    'Chưa đến thời gian nhận hồ sơ.'],
        'reg_open'       => ['success', 'Đang trong thời gian nhận hồ sơ chính thức.'],
        'reviewing'      => ['danger',  '⚠️ Đang trong giai đoạn XÉT TUYỂN — Các tác vụ thủ công bị tạm khóa.'],
        'enrolling'      => ['success', 'Đang trong thời gian làm thủ tục nhập học.'],
        'after_enroll'   => ['warning', 'Đã hết hạn nhập học chính thức. Đang chờ mở đợt bổ sung.'],
        'supp_reg_open'  => ['info',    'Đang trong thời gian nhận hồ sơ BỔ SUNG.'],
        'supp_reviewing' => ['danger',  '⚠️ Đang trong giai đoạn XÉT TUYỂN BỔ SUNG — Các tác vụ thủ công bị tạm khóa.'],
        'supp_enrolling' => ['success', 'Đang trong thời gian nhập học bổ sung.'],
        'completed'      => ['secondary','Đợt tuyển sinh đã hoàn tất.'],
    ];

    return $messages[$phase] ?? ['secondary', 'Không xác định.'];
}

/**
 * Kiểm tra sinh viên có nợ học phí không
 * Trả về true nếu có hóa đơn unpaid/partial/overdue
 */
function hasDebt(int $studentId, $conn): bool {
    $r = $conn->query("SELECT id FROM tuition_invoices WHERE student_id=$studentId AND status IN ('unpaid','partial','overdue') LIMIT 1");
    return $r && $r->num_rows > 0;
}
