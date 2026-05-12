<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CSRF PROTECTION
// ============================================================

/**
 * Tạo hoặc lấy CSRF token cho session hiện tại
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Xác minh CSRF token từ POST request
 * Dùng hash_equals để chống timing attack
 */
function verifyCSRFToken(string $token): bool {
    if (empty($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Kiểm tra CSRF và dừng nếu không hợp lệ (dùng trong xử lý POST)
 */
function requireCSRF(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCSRFToken($token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.']));
    }
}

/**
 * In hidden input CSRF token vào form HTML
 */
function csrfField(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// ============================================================
// RATE LIMITING — chống brute force login
// ============================================================

/**
 * Kiểm tra và ghi nhận số lần thử đăng nhập thất bại theo IP
 * Trả về true nếu bị chặn (quá giới hạn)
 */
function isRateLimited(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $sessionKey = '_rl_' . md5($key);
    $now = time();

    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 0, 'window_start' => $now];
    }

    // Reset nếu đã qua cửa sổ thời gian
    if ($now - $_SESSION[$sessionKey]['window_start'] > $windowSeconds) {
        $_SESSION[$sessionKey] = ['count' => 0, 'window_start' => $now];
    }

    return $_SESSION[$sessionKey]['count'] >= $maxAttempts;
}

/**
 * Tăng bộ đếm thất bại
 */
function incrementRateLimit(string $key): void {
    $sessionKey = '_rl_' . md5($key);
    if (isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey]['count']++;
    }
}

/**
 * Reset bộ đếm sau khi đăng nhập thành công
 */
function resetRateLimit(string $key): void {
    $sessionKey = '_rl_' . md5($key);
    unset($_SESSION[$sessionKey]);
}

/**
 * Lấy số giây còn lại trước khi hết block
 */
function getRateLimitRemaining(string $key, int $windowSeconds = 300): int {
    $sessionKey = '_rl_' . md5($key);
    if (!isset($_SESSION[$sessionKey])) return 0;
    $elapsed = time() - $_SESSION[$sessionKey]['window_start'];
    return max(0, $windowSeconds - $elapsed);
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
    // Xác minh CSRF token cho tất cả POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!verifyCSRFToken($token)) {
            http_response_code(403);
            // Nếu là AJAX request, trả về JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.']));
            }
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.'];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit();
        }
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

    // Dùng cache trong session nếu đã load
    if (!empty($_SESSION['_roles_cached']) && isset($_SESSION['_user_role_codes'])) {
        return in_array($roleCode, $_SESSION['_user_role_codes'], true);
    }

    global $conn;
    $uid = (int)$_SESSION['user_id'];
    // Dùng prepared statement thay vì real_escape_string
    $stmt = $conn->prepare("
        SELECT ur.id FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ?
          AND r.code = ?
          AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        LIMIT 1
    ");
    if (!$stmt) return false;
    $stmt->bind_param('is', $uid, $roleCode);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}

/**
 * Kiểm tra user có quyền cụ thể trong module không.
 * Admin luôn có tất cả quyền.
 * Hỗ trợ cả permission code mới (permissions table) và code cũ (role_permissions).
 */
function hasPermission(string $module, string $permission): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return true;

    global $conn;
    $uid = (int)$_SESSION['user_id'];

    // Thử permission code mới: 'module.action' format
    $fullCode = str_contains($permission, '.') ? $permission : "$module.$permission";
    $chkPerm = $conn->query("SHOW TABLES LIKE 'permissions'");
    if ($chkPerm && $chkPerm->num_rows > 0) {
        $stmt = $conn->prepare(
            "SELECT p.id FROM permissions p
             JOIN role_permissions rp ON rp.permission = p.code
             JOIN user_roles ur ON ur.role_id = rp.role_id
             JOIN roles r ON ur.role_id = r.id
             WHERE ur.user_id = ?
               AND p.code = ?
               AND r.is_active = 1
               AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('is', $uid, $fullCode);
            $stmt->execute();
            $has = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($has) return true;
        }
    }

    // Fallback: permission code cũ (string trong role_permissions.permission)
    $stmt = $conn->prepare(
        "SELECT rp.id FROM role_permissions rp
         JOIN user_roles ur ON rp.role_id = ur.role_id
         JOIN roles r ON ur.role_id = r.id
         WHERE ur.user_id = ?
           AND rp.module = ?
           AND rp.permission = ?
           AND r.is_active = 1
           AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
         LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param('iss', $uid, $module, $permission);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}

/**
 * Lấy tất cả roles của user hiện tại
 */
function getUserRoles(): array {
    if (!isLoggedIn()) return [];
    if ($_SESSION['role'] === 'admin') return [['code'=>'admin','name'=>'Quản trị viên','department'=>'Ban Giám hiệu']];

    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT r.code, r.name, r.department, r.color
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ?
          AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY r.department
    ");
    $roles = [];
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $roles[] = $row;
        $stmt->close();
    }
    return $roles;
}

/**
 * Yêu cầu user phải có ít nhất 1 trong các role được liệt kê
 * Dùng cho các trang module riêng
 */
/**
 * Yêu cầu user phải có ít nhất 1 trong các role được liệt kê.
 * Ưu tiên kiểm tra _active_role từ session (khi user đã chọn role).
 * Hỗ trợ wildcard: 'faculty_manager' khớp cả 'faculty_manager_1', 'faculty_manager_2'...
 */
function requireAnyRole(array $roleCodes): void {
    requireLogin();

    // Admin luôn pass
    if ($_SESSION['role'] === 'admin') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if (!verifyCSRFToken($token)) {
                http_response_code(403);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    die(json_encode(['success' => false, 'message' => 'CSRF invalid.']));
                }
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yêu cầu không hợp lệ.'];
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit();
            }
        }
        return;
    }

    // Kiểm tra _active_role trước (user đã chọn role từ màn hình chọn)
    $activeRole = $_SESSION['_active_role'] ?? '';
    if ($activeRole) {
        foreach ($roleCodes as $code) {
            // Exact match
            if ($activeRole === $code) {
                self_csrf_check();
                return;
            }
            // Prefix match: 'faculty_manager' khớp 'faculty_manager_1'
            if (str_starts_with($activeRole, $code)) {
                self_csrf_check();
                return;
            }
            // Reverse: code là prefix của activeRole
            if (str_starts_with($code, 'faculty_') && str_starts_with($activeRole, 'faculty_')) {
                // faculty_manager khớp faculty_manager_2
                $baseCode = preg_replace('/_\d+$/', '', $activeRole);
                if ($baseCode === $code) {
                    self_csrf_check();
                    return;
                }
            }
        }
    }

    // Fallback: kiểm tra qua DB (cho staff role hoặc khi không có _active_role)
    if (in_array($_SESSION['role'], ['staff', 'teacher'])) {
        // Mở rộng roleCodes với pattern khoa cụ thể
        $expandedCodes = [];
        foreach ($roleCodes as $code) {
            $expandedCodes[] = $code;
            if (in_array($code, ['faculty_manager', 'faculty_staff', 'dept_head'], true)) {
                if (!empty($_SESSION['_user_role_codes'])) {
                    foreach ($_SESSION['_user_role_codes'] as $userCode) {
                        if (str_starts_with($userCode, $code . '_')) {
                            $expandedCodes[] = $userCode;
                        }
                    }
                } else {
                    global $conn;
                    $uid  = (int)$_SESSION['user_id'];
                    $like = $code . '_%';
                    $res  = $conn->prepare(
                        "SELECT r.code FROM user_roles ur JOIN roles r ON ur.role_id=r.id
                         WHERE ur.user_id=? AND r.code LIKE ? AND r.is_active=1
                           AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
                    );
                    if ($res) {
                        $res->bind_param('is', $uid, $like);
                        $res->execute();
                        $result = $res->get_result();
                        while ($row = $result->fetch_assoc()) $expandedCodes[] = $row['code'];
                        $res->close();
                    }
                }
            }
        }

        foreach ($expandedCodes as $code) {
            if (hasRole($code)) {
                self_csrf_check();
                return;
            }
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
 * Helper: kiểm tra CSRF cho POST request
 */
function self_csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!verifyCSRFToken($token)) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'message' => 'CSRF invalid.']));
            }
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yêu cầu không hợp lệ.'];
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit();
        }
    }
}
/**
 * Ghi log truy cập — gọi sau khi set session thành công
 * Mỗi session_id chỉ ghi 1 lần (UNIQUE KEY)
 */
function logVisit($conn): void {
    if (!isLoggedIn()) return;
    $sid  = session_id();
    $uid  = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua   = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    // Dùng prepared statement thay vì real_escape_string
    $stmt = $conn->prepare("INSERT INTO visit_logs (user_id, role, session_id, ip, user_agent)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE last_seen=NOW(), user_id=?, role=?");
    if ($stmt) {
        $stmt->bind_param('issssss', $uid, $role, $sid, $ip, $ua, $uid, $role);
        $stmt->execute();
        $stmt->close();
    }
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

    $role   = $_SESSION['role'] ?? '';

    // Helper: đếm session online với role cụ thể (dùng prepared statement)
    $countByRole = function(string $filterRole = '') use ($conn, $ONLINE_MINUTES): int {
        if ($filterRole !== '') {
            $stmt = $conn->prepare("SELECT COUNT(*) c FROM visit_logs WHERE last_seen >= NOW() - INTERVAL ? MINUTE AND role = ?");
            if (!$stmt) return 0;
            $stmt->bind_param('is', $ONLINE_MINUTES, $filterRole);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) c FROM visit_logs WHERE last_seen >= NOW() - INTERVAL ? MINUTE");
            if (!$stmt) return 0;
            $stmt->bind_param('i', $ONLINE_MINUTES);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($result['c'] ?? 0);
    };

    $countByRoles = function(array $roles) use ($conn, $ONLINE_MINUTES): int {
        if (empty($roles)) return 0;
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $types = 'i' . str_repeat('s', count($roles));
        $stmt = $conn->prepare("SELECT COUNT(*) c FROM visit_logs WHERE last_seen >= NOW() - INTERVAL ? MINUTE AND role IN ($placeholders)");
        if (!$stmt) return 0;
        $params = array_merge([$ONLINE_MINUTES], $roles);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($result['c'] ?? 0);
    };

    // ── Admin chung — xem tất cả role ──────────────────────
    if ($role === 'admin') {
        return [
            'level'   => 'admin',
            'total'   => $countByRole(),
            'by_role' => [
                'admin'   => $countByRole('admin'),
                'teacher' => $countByRole('teacher'),
                'student' => $countByRole('student'),
                'staff'   => $countByRole('staff'),
            ],
        ];
    }

    // ── Staff — xem tổng + SV + GV (không xem admin) ───────
    if ($role === 'staff') {
        return [
            'level'    => 'staff',
            'total'    => $countByRoles(['student', 'teacher', 'staff']),
            'students' => $countByRole('student'),
            'teachers' => $countByRole('teacher'),
        ];
    }

    // ── Teacher / Student — chỉ xem tổng + SV + GV ─────────
    return [
        'level'    => $role,
        'total'    => $countByRoles(['student', 'teacher']),
        'students' => $countByRole('student'),
        'teachers' => $countByRole('teacher'),
    ];
}
// ============================================================
// PRG HELPERS — Post/Redirect/Get pattern
// ============================================================

/**
 * Kiểm tra user có thuộc phòng Kế toán không
 */
function isFinanceStaff(): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return true;
    return hasRole('finance_manager') || hasRole('finance_staff');
}

function isFinanceManager(): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return true;
    return hasRole('finance_manager');
}

/**
 * Kiểm tra user có thuộc phòng Đào tạo không
 */
function isTrainingStaff(): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return true;
    return hasRole('academic_manager') || hasRole('academic_staff');
}

function isTrainingManager(): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return true;
    return hasRole('academic_manager');
}

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
 * Kiểm tra sinh viên có bị khóa chức năng do nợ học phí không.
 * Điều kiện khóa: có hóa đơn đã published + quá hạn + chưa đóng đủ
 * Trả về ['locked'=>bool, 'message'=>string, 'period_title'=>string]
 */
function getTuitionLockStatus(int $studentId): array {
    global $conn;
    $tbl = $conn->query("SHOW TABLES LIKE 'tuition_invoices'");
    if (!$tbl || $tbl->num_rows === 0) return ['locked' => false, 'message' => '', 'period_title' => ''];

    // Kiểm tra schema mới có period_id không
    $chkCol = $conn->query("SHOW COLUMNS FROM `tuition_invoices` LIKE 'period_id'");
    if (!$chkCol || $chkCol->num_rows === 0) return ['locked' => false, 'message' => '', 'period_title' => ''];

    // Hóa đơn đã published (status=unpaid/partial/overdue) + đợt thu đã quá hạn
    $res = $conn->prepare("
        SELECT ti.id, tp.title, tp.due_date, ti.net_amount, ti.paid_amount
        FROM tuition_invoices ti
        JOIN tuition_periods tp ON ti.period_id = tp.id
        WHERE ti.student_id = ?
          AND ti.status IN ('unpaid','partial','overdue')
          AND tp.status IN ('published','closed')
          AND tp.due_date < CURDATE()
        LIMIT 1
    ");
    if (!$res) return ['locked' => false, 'message' => '', 'period_title' => ''];
    $res->bind_param('i', $studentId);
    $res->execute();
    $row = $res->get_result()->fetch_assoc();
    $res->close();

    if ($row) {
        $remaining = number_format($row['net_amount'] - $row['paid_amount'], 0, ',', '.');
        return [
            'locked'       => true,
            'message'      => "Bạn đang nợ học phí {$remaining} ₫ ({$row['title']}). Vui lòng đóng học phí để tiếp tục sử dụng hệ thống.",
            'period_title' => $row['title'],
        ];
    }
    return ['locked' => false, 'message' => '', 'period_title' => ''];
}

/**
 * Kiểm tra nhanh sinh viên có bị khóa không (dùng trong register_subject, grades, timetable)
 */
function isTuitionLocked(int $studentId): bool {
    return getTuitionLockStatus($studentId)['locked'];
}

/**
 * Kiểm tra sinh viên có nợ học phí không (bao gồm cả chưa quá hạn)
 */
function hasTuitionDebt(int $studentId): bool {
    global $conn;
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

/**
 * Kiểm tra user hiện tại có thể chuyển vai trò không.
 * Điều kiện: có từ 2 role trở lên trong user_roles,
 * hoặc là teacher có ít nhất 1 dept role (vì teacher luôn có thể về portal GV).
 */
function canSwitchRole(): bool {
    if (!isLoggedIn()) return false;
    if ($_SESSION['role'] === 'admin') return false; // admin không cần chuyển

    // Dùng cache nếu có
    if (isset($_SESSION['_can_switch_role'])) {
        return (bool)$_SESSION['_can_switch_role'];
    }

    global $conn;
    $uid = (int)$_SESSION['user_id'];

    // Đếm số dept roles (loại faculty_lecturer)
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM user_roles ur
         JOIN roles r ON ur.role_id = r.id
         WHERE ur.user_id = ? AND r.is_active = 1
           AND r.code != 'faculty_lecturer'
           AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
    );
    $count = 0;
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
    }

    // Teacher có ít nhất 1 dept role → có thể chuyển (giữa teacher portal và dept module)
    // Bất kỳ user nào có >= 2 dept roles → có thể chuyển
    $can = ($count >= 2) || ($_SESSION['role'] === 'teacher' && $count >= 1);
    $_SESSION['_can_switch_role'] = $can;
    return $can;
}

function cacheUserRoles(): void {
    if (!isLoggedIn() || isset($_SESSION['_roles_cached'])) return;
    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT r.code FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
    ");
    $_SESSION['_user_role_codes'] = [];
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $_SESSION['_user_role_codes'][] = $row['code'];
        $stmt->close();
    }
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
    $stmt = $conn->prepare("SELECT id FROM tuition_invoices WHERE student_id=? AND status IN ('unpaid','partial','overdue') LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}
