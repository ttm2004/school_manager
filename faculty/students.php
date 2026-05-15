<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Danh sách Sinh viên';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

$flash = getFlash();

// ── GET filters ───────────────────────────────────────────────
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$majorId    = (int)($_GET['major_id'] ?? 0);
$status     = trim($_GET['status'] ?? '');
$year       = (int)($_GET['year'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$filterMode = (($_GET['mode'] ?? 'system') === 'test') ? 'test' : 'system';

$allowedStatuses = ['đang học', 'bảo lưu', 'thôi học', 'tốt nghiệp'];

// ── Lấy danh sách ngành thuộc khoa ───────────────────────────
$majors = [];
$stmtMajors = $conn->prepare("SELECT id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC");
$stmtMajors->bind_param('i', $facultyId);
$stmtMajors->execute();
$majors = $stmtMajors->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMajors->close();

// ── Summary badges: đếm theo academic_status ─────────────────
$statusCounts = [];
$stmtStatus = $conn->prepare(
    "SELECT s.academic_status, COUNT(*) AS c
     FROM students s
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     WHERE m.faculty_id = ? AND s.data_mode = ?
     GROUP BY s.academic_status"
);
$stmtStatus->bind_param('is', $facultyId, $filterMode);
$stmtStatus->execute();
$statusResult = $stmtStatus->get_result();
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['academic_status']] = (int)$row['c'];
}
$stmtStatus->close();

// ── Build WHERE ───────────────────────────────────────────────
$whereParts = ['m.faculty_id = ?', 's.data_mode = ?'];
$bindTypes  = 'is';
$bindValues = [$facultyId, $filterMode];

if ($majorId > 0) {
    $whereParts[] = 'cl.major_id = ?';
    $bindTypes   .= 'i';
    $bindValues[] = $majorId;
}
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $whereParts[] = 's.academic_status = ?';
    $bindTypes   .= 's';
    $bindValues[] = $status;
}
if ($year > 0) {
    $whereParts[] = 's.enrollment_year = ?';
    $bindTypes   .= 'i';
    $bindValues[] = $year;
}
if ($search !== '') {
    $whereParts[] = '(u.full_name LIKE ? OR s.student_code LIKE ?)';
    $bindTypes   .= 'ss';
    $like = '%' . $search . '%';
    $bindValues[] = $like;
    $bindValues[] = $like;
}

$whereSQL = implode(' AND ', $whereParts);

// ── Count ─────────────────────────────────────────────────────
$stmtCount = $conn->prepare(
    "SELECT COUNT(*) AS c
     FROM students s
     JOIN users u ON s.user_id = u.id
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     WHERE {$whereSQL}"
);
$stmtCount->bind_param($bindTypes, ...$bindValues);
$stmtCount->execute();
$totalRecords = (int)($stmtCount->get_result()->fetch_assoc()['c'] ?? 0);
$stmtCount->close();

$pag = paginate($totalRecords, $page, $perPage);

// ── Fetch students ────────────────────────────────────────────
$stmtData = $conn->prepare(
    "SELECT s.id, s.student_code, s.academic_status, s.enrollment_year,
            u.full_name, u.email,
            m.major_name,
            c.class_name
     FROM students s
     JOIN users u ON s.user_id = u.id
     JOIN classes c ON s.class_id = c.id
     JOIN majors m ON c.major_id = m.id
     WHERE {$whereSQL}
     ORDER BY u.full_name ASC
     LIMIT ? OFFSET ?"
);
$allTypes  = $bindTypes . 'ii';
$allValues = array_merge($bindValues, [$pag['per_page'], $pag['offset']]);
$stmtData->bind_param($allTypes, ...$allValues);
$stmtData->execute();
$students = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtData->close();

// ── Build query string for pagination ────────────────────────
$qsParts = [];
if ($majorId > 0)  $qsParts[] = 'major_id=' . $majorId;
if ($filterMode !== 'system') $qsParts[] = 'mode=' . urlencode($filterMode);
if ($status !== '') $qsParts[] = 'status=' . urlencode($status);
if ($year > 0)     $qsParts[] = 'year=' . $year;
if ($search !== '') $qsParts[] = 'search=' . urlencode($search);
$queryString = implode('&', $qsParts);

// ── Lấy danh sách năm nhập học ────────────────────────────────
$years = [];
$stmtYears = $conn->prepare(
    "SELECT DISTINCT s.enrollment_year FROM students s
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     WHERE m.faculty_id = ? AND s.data_mode = ? AND s.enrollment_year IS NOT NULL ORDER BY s.enrollment_year DESC"
);
$stmtYears->bind_param('is', $facultyId, $filterMode);
$stmtYears->execute();
$yearsResult = $stmtYears->get_result();
while ($row = $yearsResult->fetch_assoc()) {
    $years[] = (int)$row['enrollment_year'];
}
$stmtYears->close();

$statusBadgeMap = [
    'đang học'  => 'success',
    'bảo lưu'   => 'warning',
    'thôi học'  => 'danger',
    'tốt nghiệp'=> 'info',
];

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
                <i class="bi bi-people-fill me-2 text-navy" aria-hidden="true"></i>Danh sách Sinh viên
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
                Tổng: <?php echo number_format(array_sum($statusCounts)); ?> SV
            </span>
            <?php foreach ($allowedStatuses as $st): ?>
            <?php $cnt = $statusCounts[$st] ?? 0; if ($cnt === 0) continue; ?>
            <span class="badge bg-<?php echo $statusBadgeMap[$st] ?? 'secondary'; ?> fs-6">
                <?php echo htmlspecialchars($st); ?>: <?php echo $cnt; ?>
            </span>
            <?php endforeach; ?>
        </div>

        <!-- Filter form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="students.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="search" class="form-label">Tìm kiếm</label>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="Tên hoặc mã SV..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="mode" class="form-label">Chế độ</label>
                        <select id="mode" name="mode" class="form-select">
                            <option value="system" <?php echo $filterMode === 'system' ? 'selected' : ''; ?>>Dữ liệu thật</option>
                            <option value="test" <?php echo $filterMode === 'test' ? 'selected' : ''; ?>>Test / Demo</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="major_id" class="form-label">Ngành</label>
                        <select id="major_id" name="major_id" class="form-select">
                            <option value="0">-- Tất cả --</option>
                            <?php foreach ($majors as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"
                                <?php echo $majorId === (int)$m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['major_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="status" class="form-label">Trạng thái</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($allowedStatuses as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>"
                                <?php echo $status === $st ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="year" class="form-label">Năm nhập học</label>
                        <select id="year" name="year" class="form-select">
                            <option value="0">-- Tất cả --</option>
                            <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="submit" class="btn btn-navy w-100" aria-label="Lọc danh sách sinh viên">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Lọc
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-people-fill me-2" aria-hidden="true"></i>
                    Danh sách Sinh viên
                    <span class="badge bg-light text-dark ms-2"><?php echo number_format($totalRecords); ?></span>
                </span>
                <a href="/university/faculty/export.php?type=students&<?php echo htmlspecialchars($queryString); ?>"
                   class="btn btn-sm btn-outline-success" aria-label="Xuất danh sách sinh viên ra CSV">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>Xuất CSV
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã SV</th>
                            <th>Họ tên</th>
                            <th>Ngành</th>
                            <th>Lớp</th>
                            <th>Trạng thái</th>
                            <th class="text-center">Năm nhập học</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Không tìm thấy sinh viên nào.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($students as $sv): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($sv['student_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($sv['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($sv['major_name']); ?></td>
                            <td><?php echo htmlspecialchars($sv['class_name'] ?? '—'); ?></td>
                            <td>
                                <?php $badge = $statusBadgeMap[$sv['academic_status']] ?? 'secondary'; ?>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo htmlspecialchars($sv['academic_status']); ?>
                                </span>
                            </td>
                            <td class="text-center"><?php echo htmlspecialchars($sv['enrollment_year'] ?? '—'); ?></td>
                            <td>
                                <a href="student_detail.php?id=<?php echo (int)$sv['id']; ?>"
                                   class="btn btn-sm btn-outline-navy"
                                   aria-label="Xem chi tiết sinh viên <?php echo htmlspecialchars($sv['full_name']); ?>">
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
                    / <?php echo number_format($pag['total']); ?> sinh viên
                </small>
                <?php echo renderPagination($pag, $queryString); ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<?php include 'includes/footer.php'; ?>
