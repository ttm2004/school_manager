<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Danh sách Giảng viên';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào. Vui lòng liên hệ quản trị viên.'];
    header('Location: /university/login.php');
    exit();
}

$allowedDegrees = ['Cử nhân', 'Thạc sĩ', 'Tiến sĩ', 'PGS.TS', 'GS.TS'];

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xác minh CSRF token cho tất cả POST requests
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.'];
        header('Location: teachers.php');
        exit();
    }
    $action = trim($_POST['action'] ?? '');

    // update_profile — faculty_manager only
    if ($action === 'update_profile') {
        if (!isFacultyManager()) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
            header('Location: teachers.php');
            exit();
        }
        $teacherId     = (int)($_POST['teacher_id'] ?? 0);
        $degree        = trim($_POST['degree'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');

        if ($teacherId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: teachers.php');
            exit();
        }
        if (!in_array($degree, $allowedDegrees, true)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Học vị không hợp lệ.'];
            header('Location: teachers.php?id=' . $teacherId);
            exit();
        }
        if (!assertFacultyOwnership($conn, 'teachers', $teacherId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền chỉnh sửa giảng viên thuộc khoa khác.'];
            header('Location: teachers.php');
            exit();
        }

        // Lấy dữ liệu cũ để audit
        $stmtOld = $conn->prepare("SELECT t.degree, t.specialization, u.phone, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ? LIMIT 1");
        $stmtOld->bind_param('i', $teacherId);
        $stmtOld->execute();
        $oldRow = $stmtOld->get_result()->fetch_assoc();
        $stmtOld->close();

        // UPDATE teachers
        $stmtT = $conn->prepare("UPDATE teachers SET degree = ?, specialization = ? WHERE id = ? AND faculty_id = ?");
        $stmtT->bind_param('ssii', $degree, $specialization, $teacherId, $facultyId);
        $stmtT->execute();
        $stmtT->close();

        // UPDATE users (phone, email) via teacher.user_id
        $stmtU = $conn->prepare("UPDATE users u JOIN teachers t ON u.id = t.user_id SET u.phone = ?, u.email = ? WHERE t.id = ? AND t.faculty_id = ?");
        $stmtU->bind_param('ssii', $phone, $email, $teacherId, $facultyId);
        $stmtU->execute();
        $stmtU->close();

        $oldData = json_encode($oldRow);
        $newData = json_encode(['degree' => $degree, 'specialization' => $specialization, 'phone' => $phone, 'email' => $email]);
        logAudit($conn, $userId, 'update', 'faculty', 'teachers', $teacherId, $oldData, $newData, $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật hồ sơ giảng viên thành công.'];
        header('Location: teacher_detail.php?id=' . $teacherId);
        exit();
    }

    // assign_department — faculty_manager only
    if ($action === 'assign_department') {
        if (!isFacultyManager()) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
            header('Location: teachers.php');
            exit();
        }
        $teacherId    = (int)($_POST['teacher_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0); // 0 = bỏ phân bộ môn

        if ($teacherId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: teachers.php');
            exit();
        }
        if (!assertFacultyOwnership($conn, 'teachers', $teacherId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền chỉnh sửa giảng viên thuộc khoa khác.'];
            header('Location: teachers.php');
            exit();
        }

        // Validate department thuộc faculty nếu có
        if ($departmentId > 0) {
            $stmtDept = $conn->prepare("SELECT id FROM departments WHERE id = ? AND faculty_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmtDept->bind_param('ii', $departmentId, $facultyId);
            $stmtDept->execute();
            if ($stmtDept->get_result()->num_rows === 0) {
                $stmtDept->close();
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bộ môn không hợp lệ hoặc không thuộc khoa này.'];
                header('Location: teachers.php');
                exit();
            }
            $stmtDept->close();
        }

        $deptIdParam = $departmentId > 0 ? $departmentId : null;
        $stmtAssign = $conn->prepare("UPDATE teachers SET department_id = ? WHERE id = ? AND faculty_id = ?");
        $stmtAssign->bind_param('iii', $deptIdParam, $teacherId, $facultyId);
        $stmtAssign->execute();
        $stmtAssign->close();

        logAudit($conn, $userId, 'update', 'faculty', 'teachers', $teacherId, null, json_encode(['department_id' => $deptIdParam]), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Phân bộ môn thành công.'];
        header('Location: teachers.php');
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hành động không hợp lệ.'];
    header('Location: teachers.php');
    exit();
}

// ── GET Handler ───────────────────────────────────────────────
$flash    = getFlash();
$viewMode = trim($_GET['view'] ?? 'list'); // list | by_dept
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$degree   = trim($_GET['degree'] ?? '');
$search   = trim($_GET['search'] ?? '');
$deptFilter = (int)($_GET['dept'] ?? 0);

// ── Kiểm tra cột department_id có tồn tại chưa ───────────────
$hasDeptColumn = false;
$chkCol = $conn->query("SHOW COLUMNS FROM `teachers` LIKE 'department_id'");
if ($chkCol && $chkCol->num_rows > 0) {
    $hasDeptColumn = true;
}

// Lấy danh sách bộ môn để filter (chỉ khi cột đã tồn tại)
$departments = [];
if ($hasDeptColumn) {
    $stmtDepts = $conn->prepare("SELECT id, department_name FROM departments WHERE faculty_id = ? AND deleted_at IS NULL ORDER BY department_name");
    $stmtDepts->bind_param('i', $facultyId);
    $stmtDepts->execute();
    $deptResult = $stmtDepts->get_result();
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }
    $stmtDepts->close();
}

// ── Summary badges: đếm theo degree ──────────────────────────
$degreeCounts = [];
$stmtDeg = $conn->prepare(
    "SELECT degree, COUNT(*) AS c FROM teachers WHERE faculty_id = ? GROUP BY degree"
);
$stmtDeg->bind_param('i', $facultyId);
$stmtDeg->execute();
$degResult = $stmtDeg->get_result();
while ($row = $degResult->fetch_assoc()) {
    $degreeCounts[$row['degree']] = (int)$row['c'];
}
$stmtDeg->close();

// ── Build WHERE clause ────────────────────────────────────────
$whereParts = ['t.faculty_id = ?'];
$bindTypes  = 'i';
$bindValues = [$facultyId];

if ($degree !== '' && in_array($degree, $allowedDegrees, true)) {
    $whereParts[] = 't.degree = ?';
    $bindTypes   .= 's';
    $bindValues[] = $degree;
}
if ($search !== '') {
    $whereParts[] = '(u.full_name LIKE ? OR t.teacher_code LIKE ?)';
    $bindTypes   .= 'ss';
    $likeSearch   = '%' . $search . '%';
    $bindValues[] = $likeSearch;
    $bindValues[] = $likeSearch;
}
if ($deptFilter > 0) {
    $whereParts[] = 't.department_id = ?';
    $bindTypes   .= 'i';
    $bindValues[] = $deptFilter;
}

$whereSQL = implode(' AND ', $whereParts);

// ── Count total ───────────────────────────────────────────────
$countSQL  = "SELECT COUNT(*) AS c FROM teachers t JOIN users u ON t.user_id = u.id WHERE {$whereSQL}";
$stmtCount = $conn->prepare($countSQL);
$stmtCount->bind_param($bindTypes, ...$bindValues);
$stmtCount->execute();
$totalRecords = (int)($stmtCount->get_result()->fetch_assoc()['c'] ?? 0);
$stmtCount->close();

$pag = paginate($totalRecords, $page, $perPage);

// ── Fetch teachers ────────────────────────────────────────────
$dataSQL = "SELECT t.id, t.teacher_code, t.degree, t.specialization, t.department_id,
                   u.full_name, u.email, u.phone,
                   d.department_name
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE {$whereSQL}
            ORDER BY u.full_name ASC
            LIMIT ? OFFSET ?";
$stmtData = $conn->prepare($dataSQL);
$allTypes  = $bindTypes . 'ii';
$allValues = array_merge($bindValues, [$pag['per_page'], $pag['offset']]);
$stmtData->bind_param($allTypes, ...$allValues);
$stmtData->execute();
$teachers = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtData->close();

// ── Build query string for pagination ────────────────────────
$qsParts = [];
if ($degree !== '')    $qsParts[] = 'degree=' . urlencode($degree);
if ($search !== '')    $qsParts[] = 'search=' . urlencode($search);
if ($deptFilter > 0)   $qsParts[] = 'dept=' . $deptFilter;
if ($viewMode !== 'list') $qsParts[] = 'view=' . urlencode($viewMode);
$queryString = implode('&', $qsParts);

// ── By-department view data ───────────────────────────────────
$teachersByDept = [];
if ($viewMode === 'by_dept') {
    // Lấy tất cả GV nhóm theo bộ môn
    $stmtByDept = $conn->prepare(
        "SELECT t.id, t.teacher_code, t.degree, t.specialization, t.department_id,
                u.full_name, u.email, u.phone,
                d.department_name
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN departments d ON t.department_id = d.id
         WHERE t.faculty_id = ?
         ORDER BY d.department_name ASC, u.full_name ASC"
    );
    $stmtByDept->bind_param('i', $facultyId);
    $stmtByDept->execute();
    $allTeachers = $stmtByDept->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtByDept->close();

    foreach ($allTeachers as $t) {
        $key = $t['department_id'] ? ($t['department_name'] ?? 'Không rõ') : '__none__';
        $teachersByDept[$key][] = $t;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <!-- Topbar -->
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mở/đóng menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-person-badge-fill me-2 text-navy" aria-hidden="true"></i>Danh sách Giảng viên
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">
                <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
            </span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Đăng xuất
            </a>
        </div>
    </div>

    <div class="admin-content">

        <!-- Flash -->
        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php endif; ?>

        <!-- Summary badges -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <span class="badge bg-secondary fs-6">
                <i class="bi bi-people-fill me-1" aria-hidden="true"></i>
                Tổng: <?php echo number_format($totalRecords); ?> GV
            </span>
            <?php foreach ($allowedDegrees as $deg): ?>
            <?php $cnt = $degreeCounts[$deg] ?? 0; if ($cnt === 0) continue; ?>
            <span class="badge bg-navy fs-6"><?php echo htmlspecialchars($deg); ?>: <?php echo $cnt; ?></span>
            <?php endforeach; ?>
        </div>

        <!-- Filter form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="teachers.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="search" class="form-label">Tìm kiếm</label>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="Tên hoặc mã GV..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="degree" class="form-label">Học vị</label>
                        <select id="degree" name="degree" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($allowedDegrees as $deg): ?>
                            <option value="<?php echo htmlspecialchars($deg); ?>"
                                <?php echo $degree === $deg ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($deg); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="dept" class="form-label">Bộ môn</label>
                        <select id="dept" name="dept" class="form-select">
                            <option value="0">-- Tất cả --</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>"
                                <?php echo $deptFilter === (int)$d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="view" class="form-label">Chế độ xem</label>
                        <select id="view" name="view" class="form-select">
                            <option value="list" <?php echo $viewMode === 'list' ? 'selected' : ''; ?>>Danh sách</option>
                            <option value="by_dept" <?php echo $viewMode === 'by_dept' ? 'selected' : ''; ?>>Theo bộ môn</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <button type="submit" class="btn btn-navy w-100" aria-label="Lọc danh sách">
                            <i class="bi bi-search" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($viewMode === 'by_dept'): ?>
        <!-- ── By-department view ── -->
        <?php foreach ($teachersByDept as $deptName => $deptTeachers): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-diagram-3-fill me-2" aria-hidden="true"></i>
                <?php echo $deptName === '__none__' ? '<em>Chưa phân bộ môn</em>' : htmlspecialchars($deptName); ?>
                <span class="badge bg-light text-dark ms-2"><?php echo count($deptTeachers); ?> GV</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã GV</th>
                            <th>Họ tên</th>
                            <th>Học vị</th>
                            <th>Chuyên ngành</th>
                            <th>Email</th>
                            <th>SĐT</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deptTeachers as $t): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($t['teacher_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                            <td>
                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($t['degree'] ?? '—'); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($t['specialization'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($t['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($t['phone'] ?? '—'); ?></td>
                            <td>
                                <a href="teacher_detail.php?id=<?php echo (int)$t['id']; ?>"
                                   class="btn btn-sm btn-outline-navy" aria-label="Xem chi tiết giảng viên <?php echo htmlspecialchars($t['full_name']); ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($teachersByDept)): ?>
        <div class="alert alert-info">Không có giảng viên nào.</div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ── List view ── -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-person-badge-fill me-2" aria-hidden="true"></i>
                    Danh sách Giảng viên
                    <span class="badge bg-light text-dark ms-2"><?php echo number_format($totalRecords); ?></span>
                </span>
                <a href="/university/faculty/export.php?type=teachers" class="btn btn-sm btn-outline-success" aria-label="Xuất danh sách giảng viên ra CSV">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>Xuất CSV
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã GV</th>
                            <th>Họ tên</th>
                            <th>Học vị</th>
                            <th>Chuyên ngành</th>
                            <th>Bộ môn</th>
                            <th>Email</th>
                            <th>SĐT</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Không tìm thấy giảng viên nào.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($teachers as $t): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($t['teacher_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                            <td>
                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($t['degree'] ?? '—'); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($t['specialization'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($t['department_name'] ?? '<em class="text-muted">Chưa phân</em>'); ?></td>
                            <td><?php echo htmlspecialchars($t['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($t['phone'] ?? '—'); ?></td>
                            <td>
                                <a href="teacher_detail.php?id=<?php echo (int)$t['id']; ?>"
                                   class="btn btn-sm btn-outline-navy" aria-label="Xem chi tiết giảng viên <?php echo htmlspecialchars($t['full_name']); ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pag['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Hiển thị <?php echo $pag['offset'] + 1; ?>–<?php echo min($pag['offset'] + $pag['per_page'], $pag['total']); ?>
                    / <?php echo number_format($pag['total']); ?> giảng viên
                </small>
                <?php echo renderPagination($pag, $queryString); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<?php include 'includes/footer.php'; ?>
