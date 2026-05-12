<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Phân quyền Giảng viên';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if (!isFacultyManager()) {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ Trưởng khoa mới có quyền phân quyền giảng viên.'];
    header('Location: /university/faculty/index.php');
    exit();
}
if ($facultyId <= 0) {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

// Thông tin khoa
$stmtFac = $conn->prepare("SELECT faculty_name FROM faculties WHERE id = ? LIMIT 1");
$stmtFac->bind_param('i', $facultyId);
$stmtFac->execute();
$faculty     = $stmtFac->get_result()->fetch_assoc();
$stmtFac->close();
$facultyName = $faculty['faculty_name'] ?? 'Khoa/Viện';

// ── Roles được phép cấp (chỉ faculty_staff) ──────────────────
$allowedRoles = ['faculty_staff', "faculty_staff_{$facultyId}"];

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // ── grant_role: cấp quyền faculty_staff cho GV trong khoa ──
    if ($action === 'grant_role') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $roleCode  = trim($_POST['role_code'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $expiresAt = trim($_POST['expires_at'] ?? '') ?: null;

        // Validate role được phép cấp
        if (!in_array($roleCode, $allowedRoles, true)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Quyền không hợp lệ. Chỉ được cấp quyền faculty_staff.'];
            header('Location: staff_roles.php');
            exit();
        }

        // Validate teacher thuộc khoa này
        if (!assertFacultyOwnership($conn, 'teachers', $teacherId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Giảng viên không thuộc khoa của bạn.'];
            header('Location: staff_roles.php');
            exit();
        }

        // Lấy user_id của teacher
        $stmtT = $conn->prepare("SELECT user_id, full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ? LIMIT 1");
        $stmtT->bind_param('i', $teacherId);
        $stmtT->execute();
        $teacherRow = $stmtT->get_result()->fetch_assoc();
        $stmtT->close();

        if (!$teacherRow) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không tìm thấy giảng viên.'];
            header('Location: staff_roles.php');
            exit();
        }
        $targetUserId = (int)$teacherRow['user_id'];

        // Lấy role_id từ code
        $stmtR = $conn->prepare("SELECT id FROM roles WHERE code = ? AND is_active = 1 LIMIT 1");
        $stmtR->bind_param('s', $roleCode);
        $stmtR->execute();
        $roleRow = $stmtR->get_result()->fetch_assoc();
        $stmtR->close();

        if (!$roleRow) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Quyền không tồn tại trong hệ thống.'];
            header('Location: staff_roles.php');
            exit();
        }
        $roleId = (int)$roleRow['id'];

        // INSERT user_roles
        if ($expiresAt) {
            $stmtI = $conn->prepare(
                "INSERT INTO user_roles (user_id, role_id, granted_by, note, expires_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by), note=VALUES(note),
                 expires_at=VALUES(expires_at), granted_at=NOW()"
            );
            $stmtI->bind_param('iiiss', $targetUserId, $roleId, $userId, $note, $expiresAt);
        } else {
            $stmtI = $conn->prepare(
                "INSERT INTO user_roles (user_id, role_id, granted_by, note)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by), note=VALUES(note),
                 expires_at=NULL, granted_at=NOW()"
            );
            $stmtI->bind_param('iiis', $targetUserId, $roleId, $userId, $note);
        }

        if ($stmtI->execute()) {
            logAudit($conn, $userId, 'create', 'faculty', 'user_roles', $targetUserId,
                null,
                json_encode(['role_code' => $roleCode, 'teacher_id' => $teacherId, 'note' => $note]),
                $ip
            );
            $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã cấp quyền ' . htmlspecialchars($roleCode) . ' cho ' . htmlspecialchars($teacherRow['full_name']) . '.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi khi cấp quyền: ' . $conn->error];
        }
        $stmtI->close();
        header('Location: staff_roles.php?selected=' . $teacherId);
        exit();
    }

    // ── revoke_role: thu hồi quyền faculty_staff ──────────────
    if ($action === 'revoke_role') {
        $urId = (int)($_POST['ur_id'] ?? 0);

        if ($urId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: staff_roles.php');
            exit();
        }

        // Kiểm tra: chỉ thu hồi được role faculty_staff/faculty_staff_X
        // và role phải thuộc GV trong khoa này
        $stmtChk = $conn->prepare(
            "SELECT ur.id, ur.user_id, r.code, t.id AS teacher_id, u.full_name
             FROM user_roles ur
             JOIN roles r ON ur.role_id = r.id
             JOIN users u ON ur.user_id = u.id
             LEFT JOIN teachers t ON t.user_id = ur.user_id AND t.faculty_id = ?
             WHERE ur.id = ? LIMIT 1"
        );
        $stmtChk->bind_param('ii', $facultyId, $urId);
        $stmtChk->execute();
        $urRow = $stmtChk->get_result()->fetch_assoc();
        $stmtChk->close();

        if (!$urRow) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không tìm thấy quyền cần thu hồi.'];
            header('Location: staff_roles.php');
            exit();
        }

        // Chỉ thu hồi được faculty_staff hoặc faculty_staff_{facultyId}
        if (!in_array($urRow['code'], $allowedRoles, true)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không thể thu hồi quyền này. Chỉ Admin mới có thể thu hồi quyền ' . htmlspecialchars($urRow['code']) . '.'];
            header('Location: staff_roles.php');
            exit();
        }

        // Kiểm tra GV phải thuộc khoa này
        if (!$urRow['teacher_id']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Giảng viên không thuộc khoa của bạn.'];
            header('Location: staff_roles.php');
            exit();
        }

        $stmtDel = $conn->prepare("DELETE FROM user_roles WHERE id = ?");
        $stmtDel->bind_param('i', $urId);
        if ($stmtDel->execute()) {
            logAudit($conn, $userId, 'delete', 'faculty', 'user_roles', (int)$urRow['user_id'],
                json_encode(['role_code' => $urRow['code'], 'ur_id' => $urId]),
                null,
                $ip
            );
            $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã thu hồi quyền ' . htmlspecialchars($urRow['code']) . ' của ' . htmlspecialchars($urRow['full_name']) . '.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi khi thu hồi quyền.'];
        }
        $stmtDel->close();
        $teacherIdRedirect = (int)($_POST['teacher_id'] ?? 0);
        header('Location: staff_roles.php' . ($teacherIdRedirect ? '?selected=' . $teacherIdRedirect : ''));
        exit();
    }
}

// ── Flash message ─────────────────────────────────────────────
$flash = getFlash();

// ── GET: selected teacher ─────────────────────────────────────
$selectedTeacherId = (int)($_GET['selected'] ?? 0);

// ── GET: search & pagination ──────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Đếm tổng GV trong khoa (có filter search)
$countSql = "SELECT COUNT(*) AS c
             FROM teachers t
             JOIN users u ON t.user_id = u.id
             WHERE t.faculty_id = ?";
$countParams = [$facultyId];
$countTypes  = 'i';

if ($search !== '') {
    $countSql   .= " AND (u.full_name LIKE ? OR t.teacher_code LIKE ? OR u.email LIKE ?)";
    $like        = "%{$search}%";
    $countParams  = array_merge($countParams, [$like, $like, $like]);
    $countTypes  .= 'sss';
}

$stmtCnt = $conn->prepare($countSql);
$stmtCnt->bind_param($countTypes, ...$countParams);
$stmtCnt->execute();
$totalTeachers = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
$stmtCnt->close();

$pag = paginate($totalTeachers, $page, $perPage);

// Lấy danh sách GV
$listSql = "SELECT t.id AS teacher_id, t.teacher_code, t.degree, t.specialization,
                   u.id AS user_id, u.full_name, u.email, u.status
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            WHERE t.faculty_id = ?";
$listParams = [$facultyId];
$listTypes  = 'i';

if ($search !== '') {
    $listSql   .= " AND (u.full_name LIKE ? OR t.teacher_code LIKE ? OR u.email LIKE ?)";
    $like       = "%{$search}%";
    $listParams  = array_merge($listParams, [$like, $like, $like]);
    $listTypes  .= 'sss';
}
$listSql .= " ORDER BY u.full_name ASC LIMIT ? OFFSET ?";
$listParams[] = $pag['per_page'];
$listParams[] = $pag['offset'];
$listTypes   .= 'ii';

$stmtList = $conn->prepare($listSql);
$stmtList->bind_param($listTypes, ...$listParams);
$stmtList->execute();
$teachers = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtList->close();

// ── Lấy tất cả user_roles của GV trong khoa (để hiển thị badge) ──
// Map: user_id => [role_codes]
$userRolesMap = [];
if (!empty($teachers)) {
    $userIds = array_column($teachers, 'user_id');
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmtUR = $conn->prepare(
        "SELECT ur.user_id, r.code, r.name, ur.id AS ur_id, ur.expires_at
         FROM user_roles ur
         JOIN roles r ON ur.role_id = r.id
         WHERE ur.user_id IN ({$placeholders})
           AND r.is_active = 1
           AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
         ORDER BY r.code"
    );
    $types = str_repeat('i', count($userIds));
    $stmtUR->bind_param($types, ...$userIds);
    $stmtUR->execute();
    $urRows = $stmtUR->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtUR->close();
    foreach ($urRows as $ur) {
        $userRolesMap[$ur['user_id']][] = $ur;
    }
}

// ── Lấy chi tiết GV được chọn (panel phải) ───────────────────
$selectedTeacher = null;
$selectedUserRoles = [];
if ($selectedTeacherId > 0) {
    $stmtSel = $conn->prepare(
        "SELECT t.id AS teacher_id, t.teacher_code, t.degree, t.specialization,
                u.id AS user_id, u.full_name, u.email, u.phone, u.status
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         WHERE t.id = ? AND t.faculty_id = ?
         LIMIT 1"
    );
    $stmtSel->bind_param('ii', $selectedTeacherId, $facultyId);
    $stmtSel->execute();
    $selectedTeacher = $stmtSel->get_result()->fetch_assoc();
    $stmtSel->close();

    if ($selectedTeacher) {
        $selUserId = (int)$selectedTeacher['user_id'];
        $stmtSelUR = $conn->prepare(
            "SELECT ur.id AS ur_id, r.code, r.name, r.color, ur.granted_at, ur.expires_at, ur.note,
                    ug.full_name AS granted_by_name
             FROM user_roles ur
             JOIN roles r ON ur.role_id = r.id
             LEFT JOIN users ug ON ur.granted_by = ug.id
             WHERE ur.user_id = ?
               AND r.is_active = 1
               AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
             ORDER BY ur.granted_at DESC"
        );
        $stmtSelUR->bind_param('i', $selUserId);
        $stmtSelUR->execute();
        $selectedUserRoles = $stmtSelUR->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtSelUR->close();
    }
}

// ── Helper: phân loại badge GV ────────────────────────────────
function getTeacherBadge(array $userRoles, int $facultyId): array
{
    $codes = array_column($userRoles, 'code');
    foreach ($codes as $code) {
        if ($code === 'faculty_manager' || preg_match('/^faculty_manager_\d+$/', $code)) {
            return ['label' => 'Trưởng khoa', 'class' => 'bg-danger'];
        }
    }
    foreach ($codes as $code) {
        if ($code === 'faculty_staff' || $code === "faculty_staff_{$facultyId}") {
            return ['label' => 'Thư ký khoa', 'class' => 'bg-primary'];
        }
    }
    return ['label' => 'Giảng viên thường', 'class' => 'bg-secondary'];
}

// Build query string for pagination (without page=)
$qsArr = [];
if ($search !== '') $qsArr[] = 'q=' . urlencode($search);
if ($selectedTeacherId > 0) $qsArr[] = 'selected=' . $selectedTeacherId;
$qs = implode('&', $qsArr);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mở/đóng menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-shield-lock-fill me-2 text-navy" aria-hidden="true"></i>
                <?php echo htmlspecialchars($pageTitle); ?>
            </span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border">
                <i class="bi bi-building me-1" aria-hidden="true"></i>
                <?php echo htmlspecialchars($facultyName); ?>
            </span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Đăng xuất
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-3" role="alert">
            <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2" aria-hidden="true"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php endif; ?>

        <!-- Info banner -->
        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="note">
            <i class="bi bi-info-circle-fill mt-1 flex-shrink-0" aria-hidden="true"></i>
            <div>
                <strong>Lưu ý:</strong> Bạn chỉ có thể cấp/thu hồi quyền <code>faculty_staff</code> cho giảng viên thuộc
                <strong><?php echo htmlspecialchars($facultyName); ?></strong>.
                Quyền <code>faculty_manager</code> do Admin hệ thống quản lý.
            </div>
        </div>

        <div class="row g-3">

            <!-- ══ CỘT TRÁI: Danh sách giảng viên ══ -->
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-people-fill me-2" aria-hidden="true"></i>
                            Giảng viên trong khoa
                            <span class="badge bg-light text-dark ms-1"><?php echo number_format($totalTeachers); ?></span>
                        </span>
                    </div>

                    <!-- Search -->
                    <div class="card-body pb-0">
                        <form method="get" action="staff_roles.php" class="d-flex gap-2" role="search">
                            <input type="text" name="q" class="form-control form-control-sm"
                                   placeholder="Tìm theo tên, mã GV, email..."
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   aria-label="Tìm kiếm giảng viên">
                            <button type="submit" class="btn btn-sm btn-outline-primary" aria-label="Tìm kiếm">
                                <i class="bi bi-search" aria-hidden="true"></i>
                            </button>
                            <?php if ($search !== ''): ?>
                            <a href="staff_roles.php" class="btn btn-sm btn-outline-secondary" aria-label="Xóa bộ lọc">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Teacher list -->
                    <div class="list-group list-group-flush mt-2" style="max-height:520px;overflow-y:auto;" role="list">
                        <?php if (empty($teachers)): ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="bi bi-person-x fs-3 d-block mb-2" aria-hidden="true"></i>
                            <?php echo $search !== '' ? 'Không tìm thấy giảng viên phù hợp.' : 'Chưa có giảng viên nào trong khoa.'; ?>
                        </div>
                        <?php else: ?>
                        <?php foreach ($teachers as $t): ?>
                        <?php
                            $tUserRoles = $userRolesMap[$t['user_id']] ?? [];
                            $badge      = getTeacherBadge($tUserRoles, $facultyId);
                            $isSelected = ($selectedTeacherId === (int)$t['teacher_id']);
                            $isManager  = $badge['label'] === 'Trưởng khoa';
                        ?>
                        <a href="staff_roles.php?selected=<?php echo (int)$t['teacher_id']; ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>"
                           class="list-group-item list-group-item-action <?php echo $isSelected ? 'active' : ''; ?>"
                           role="listitem"
                           aria-current="<?php echo $isSelected ? 'true' : 'false'; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-2">
                                    <div class="fw-semibold">
                                        <?php echo htmlspecialchars($t['full_name']); ?>
                                    </div>
                                    <small class="<?php echo $isSelected ? 'text-white-50' : 'text-muted'; ?>">
                                        <i class="bi bi-person-badge me-1" aria-hidden="true"></i><?php echo htmlspecialchars($t['teacher_code']); ?>
                                        <?php if ($t['degree']): ?>
                                        &nbsp;·&nbsp;<?php echo htmlspecialchars($t['degree']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="badge <?php echo $badge['class']; ?> flex-shrink-0">
                                    <?php echo htmlspecialchars($badge['label']); ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($pag['total_pages'] > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted">
                            <?php echo ($pag['offset'] + 1); ?>–<?php echo min($pag['offset'] + $pag['per_page'], $pag['total']); ?>
                            / <?php echo number_format($pag['total']); ?>
                        </small>
                        <?php echo renderPagination($pag, $qs); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ CỘT PHẢI: Chi tiết & phân quyền ══ -->
            <div class="col-lg-7">
                <?php if (!$selectedTeacher): ?>
                <!-- Placeholder khi chưa chọn GV -->
                <div class="card h-100">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted py-5">
                        <i class="bi bi-person-circle fs-1 mb-3" aria-hidden="true"></i>
                        <p class="mb-0">Chọn một giảng viên ở cột trái để xem chi tiết và phân quyền.</p>
                    </div>
                </div>

                <?php else: ?>
                <?php
                    $selBadge     = getTeacherBadge($selectedUserRoles, $facultyId);
                    $isSelManager = $selBadge['label'] === 'Trưởng khoa';
                    $selUserId    = (int)$selectedTeacher['user_id'];
                    // Kiểm tra đã có faculty_staff chưa
                    $hasFacultyStaff = false;
                    foreach ($selectedUserRoles as $sur) {
                        if ($sur['code'] === 'faculty_staff' || $sur['code'] === "faculty_staff_{$facultyId}") {
                            $hasFacultyStaff = true;
                            break;
                        }
                    }
                ?>

                <!-- Thông tin GV -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-person-fill me-2" aria-hidden="true"></i>
                            Thông tin giảng viên
                        </span>
                        <span class="badge <?php echo $selBadge['class']; ?>">
                            <?php echo htmlspecialchars($selBadge['label']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Họ và tên</small>
                                <strong><?php echo htmlspecialchars($selectedTeacher['full_name']); ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Mã giảng viên</small>
                                <code><?php echo htmlspecialchars($selectedTeacher['teacher_code']); ?></code>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Học vị</small>
                                <?php echo htmlspecialchars($selectedTeacher['degree'] ?: '—'); ?>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Chuyên ngành</small>
                                <?php echo htmlspecialchars($selectedTeacher['specialization'] ?: '—'); ?>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Email</small>
                                <?php echo htmlspecialchars($selectedTeacher['email'] ?: '—'); ?>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Điện thoại</small>
                                <?php echo htmlspecialchars($selectedTeacher['phone'] ?: '—'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form cấp quyền (chỉ hiện nếu không phải Trưởng khoa và chưa có faculty_staff) -->
                <?php if (!$isSelManager): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="bi bi-shield-plus me-2" aria-hidden="true"></i>
                        Cấp quyền Thư ký khoa
                    </div>
                    <div class="card-body">
                        <?php if ($hasFacultyStaff): ?>
                        <div class="alert alert-success mb-0 d-flex align-items-center gap-2" role="status">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            Giảng viên này đã có quyền <strong>Thư ký khoa</strong>.
                            Bạn có thể thu hồi quyền ở bảng bên dưới.
                        </div>
                        <?php else: ?>
                        <form method="post" action="staff_roles.php" id="grantForm">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="grant_role">
                            <input type="hidden" name="teacher_id" value="<?php echo (int)$selectedTeacher['teacher_id']; ?>">

                            <div class="mb-3">
                                <label for="role_code" class="form-label">
                                    Quyền cấp <span class="text-danger">*</span>
                                </label>
                                <select id="role_code" name="role_code" class="form-select" required
                                        aria-describedby="roleHelp">
                                    <option value="faculty_staff">faculty_staff — Thư ký khoa (toàn hệ thống)</option>
                                    <option value="faculty_staff_<?php echo $facultyId; ?>" selected>
                                        faculty_staff_<?php echo $facultyId; ?> — Thư ký <?php echo htmlspecialchars($facultyName); ?>
                                    </option>
                                </select>
                                <div id="roleHelp" class="form-text">
                                    Khuyến nghị dùng <code>faculty_staff_<?php echo $facultyId; ?></code> để giới hạn phạm vi khoa.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="expires_at" class="form-label">Hết hạn</label>
                                <input type="date" id="expires_at" name="expires_at" class="form-control"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       aria-describedby="expiresHelp">
                                <div id="expiresHelp" class="form-text">Để trống nếu không giới hạn thời gian.</div>
                            </div>

                            <div class="mb-3">
                                <label for="grant_note" class="form-label">Ghi chú</label>
                                <input type="text" id="grant_note" name="note" class="form-control"
                                       maxlength="255"
                                       placeholder="VD: Kiêm nhiệm thư ký khoa từ HK1/2025...">
                            </div>

                            <button type="submit" class="btn btn-primary w-100"
                                    onclick="return confirm('Xác nhận cấp quyền Thư ký khoa cho <?php echo addslashes(htmlspecialchars($selectedTeacher['full_name'])); ?>?')">
                                <i class="bi bi-shield-plus me-2" aria-hidden="true"></i>
                                Cấp quyền Thư ký khoa
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="note">
                    <i class="bi bi-shield-exclamation flex-shrink-0" aria-hidden="true"></i>
                    <div>
                        Giảng viên này là <strong>Trưởng khoa</strong>. Quyền Trưởng khoa do Admin hệ thống quản lý,
                        bạn không thể thay đổi.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bảng quyền hiện có -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-list-check me-2" aria-hidden="true"></i>
                        Quyền đang được gán
                        <span class="badge bg-light text-dark ms-1"><?php echo count($selectedUserRoles); ?></span>
                    </div>
                    <?php if (empty($selectedUserRoles)): ?>
                    <div class="card-body text-center text-muted py-4">
                        <i class="bi bi-shield-slash fs-3 d-block mb-2" aria-hidden="true"></i>
                        Giảng viên chưa có quyền đặc biệt nào.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" aria-label="Danh sách quyền của giảng viên">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Quyền</th>
                                    <th scope="col">Cấp bởi</th>
                                    <th scope="col">Ngày cấp</th>
                                    <th scope="col">Hết hạn</th>
                                    <th scope="col" class="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($selectedUserRoles as $sur):
                                $canRevoke = in_array($sur['code'], $allowedRoles, true);
                                $isExpired = $sur['expires_at'] && strtotime($sur['expires_at']) < time();
                            ?>
                            <tr class="<?php echo $isExpired ? 'opacity-50' : ''; ?>">
                                <td>
                                    <span class="badge" style="background:<?php echo htmlspecialchars($sur['color']); ?>">
                                        <?php echo htmlspecialchars($sur['code']); ?>
                                    </span>
                                    <div class="text-muted" style="font-size:.75rem">
                                        <?php echo htmlspecialchars($sur['name']); ?>
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <?php echo htmlspecialchars($sur['granted_by_name'] ?? 'System'); ?>
                                </td>
                                <td class="small text-muted">
                                    <?php echo $sur['granted_at'] ? date('d/m/Y', strtotime($sur['granted_at'])) : '—'; ?>
                                </td>
                                <td class="small">
                                    <?php if ($sur['expires_at']): ?>
                                    <span class="<?php echo $isExpired ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                        <?php echo date('d/m/Y', strtotime($sur['expires_at'])); ?>
                                        <?php if ($isExpired): ?><br><span class="badge bg-danger">Hết hạn</span><?php endif; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-success">Vĩnh viễn</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($canRevoke): ?>
                                    <form method="post" action="staff_roles.php" class="d-inline"
                                          onsubmit="return confirm('Thu hồi quyền <?php echo addslashes($sur['code']); ?> của giảng viên này?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="revoke_role">
                                        <input type="hidden" name="ur_id" value="<?php echo (int)$sur['ur_id']; ?>">
                                        <input type="hidden" name="teacher_id" value="<?php echo (int)$selectedTeacher['teacher_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                aria-label="Thu hồi quyền <?php echo htmlspecialchars($sur['code']); ?>">
                                            <i class="bi bi-x-circle" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted" title="Quyền này do Admin quản lý">
                                        <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <?php endif; /* end selectedTeacher */ ?>
            </div><!-- /.col-lg-7 -->

        </div><!-- /.row -->

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<?php include 'includes/footer.php'; ?>
