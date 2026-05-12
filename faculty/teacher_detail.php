<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Hồ sơ Giảng viên';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

$allowedDegrees = ['Cử nhân', 'Thạc sĩ', 'Tiến sĩ', 'PGS.TS', 'GS.TS'];
$teacherId = (int)($_GET['id'] ?? 0);

if ($teacherId <= 0) {
    header('Location: teachers.php');
    exit();
}

// Kiểm tra ownership
if (!assertFacultyOwnership($conn, 'teachers', $teacherId, $facultyId)) {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền xem thông tin giảng viên thuộc khoa khác.'];
    header('Location: teachers.php');
    exit();
}

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        if (!isFacultyManager()) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
            header('Location: teacher_detail.php?id=' . $teacherId);
            exit();
        }
        $degree         = trim($_POST['degree'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $email          = trim($_POST['email'] ?? '');

        if (!in_array($degree, $allowedDegrees, true)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Học vị không hợp lệ.'];
            header('Location: teacher_detail.php?id=' . $teacherId);
            exit();
        }

        // Lấy dữ liệu cũ
        $stmtOld = $conn->prepare("SELECT t.degree, t.specialization, u.phone, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ? LIMIT 1");
        $stmtOld->bind_param('i', $teacherId);
        $stmtOld->execute();
        $oldRow = $stmtOld->get_result()->fetch_assoc();
        $stmtOld->close();

        $stmtT = $conn->prepare("UPDATE teachers SET degree = ?, specialization = ? WHERE id = ? AND faculty_id = ?");
        $stmtT->bind_param('ssii', $degree, $specialization, $teacherId, $facultyId);
        $stmtT->execute();
        $stmtT->close();

        $stmtU = $conn->prepare("UPDATE users u JOIN teachers t ON u.id = t.user_id SET u.phone = ?, u.email = ? WHERE t.id = ? AND t.faculty_id = ?");
        $stmtU->bind_param('ssii', $phone, $email, $teacherId, $facultyId);
        $stmtU->execute();
        $stmtU->close();

        logAudit($conn, $userId, 'update', 'faculty', 'teachers', $teacherId,
            json_encode($oldRow),
            json_encode(['degree' => $degree, 'specialization' => $specialization, 'phone' => $phone, 'email' => $email]),
            $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật hồ sơ thành công.'];
        header('Location: teacher_detail.php?id=' . $teacherId);
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hành động không hợp lệ.'];
    header('Location: teacher_detail.php?id=' . $teacherId);
    exit();
}

// ── GET: Lấy thông tin GV ─────────────────────────────────────
$stmtTeacher = $conn->prepare(
    "SELECT t.id, t.teacher_code, t.degree, t.specialization, t.department_id,
            u.full_name, u.email, u.phone, u.username, u.status AS user_status,
            d.department_name, f.faculty_name
     FROM teachers t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN departments d ON t.department_id = d.id
     LEFT JOIN faculties f ON t.faculty_id = f.id
     WHERE t.id = ? AND t.faculty_id = ?
     LIMIT 1"
);
$stmtTeacher->bind_param('ii', $teacherId, $facultyId);
$stmtTeacher->execute();
$teacher = $stmtTeacher->get_result()->fetch_assoc();
$stmtTeacher->close();

if (!$teacher) {
    header('Location: teachers.php');
    exit();
}

$pageTitle = 'Hồ sơ: ' . $teacher['full_name'];

// ── Teaching load học kỳ hiện tại ────────────────────────────
$activeSemester = getActiveSemester($conn);
$activeSemId    = $activeSemester ? (int)$activeSemester['id'] : 0;

$teachingLoad   = 0;
$assignedSections = [];

if ($activeSemId > 0) {
    $stmtLoad = $conn->prepare(
        "SELECT SUM(s.credits) AS total_credits
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         WHERE cs.teacher_id = ? AND cs.semester_id = ? AND cs.status IN ('open','closed')"
    );
    $stmtLoad->bind_param('ii', $teacherId, $activeSemId);
    $stmtLoad->execute();
    $loadRow = $stmtLoad->get_result()->fetch_assoc();
    $stmtLoad->close();
    $teachingLoad = (int)($loadRow['total_credits'] ?? 0);

    $stmtSections = $conn->prepare(
        "SELECT cs.id, cs.section_code, s.subject_name, s.credits, cs.status, cs.max_students
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         WHERE cs.teacher_id = ? AND cs.semester_id = ?
         ORDER BY s.subject_name ASC"
    );
    $stmtSections->bind_param('ii', $teacherId, $activeSemId);
    $stmtSections->execute();
    $assignedSections = $stmtSections->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSections->close();
}

$flash = getFlash();

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
                <i class="bi bi-person-badge-fill me-2 text-navy" aria-hidden="true"></i>
                Hồ sơ Giảng viên
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="teachers.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Quay lại
            </a>
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

        <div class="row g-4">
            <!-- ── Profile Card ── -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-person-circle me-2" aria-hidden="true"></i>Thông tin cơ bản
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="rounded-circle bg-navy d-inline-flex align-items-center justify-content-center"
                                 style="width:80px;height:80px;">
                                <i class="bi bi-person-fill text-white fs-2" aria-hidden="true"></i>
                            </div>
                            <h5 class="mt-2 mb-0"><?php echo htmlspecialchars($teacher['full_name']); ?></h5>
                            <code class="text-muted"><?php echo htmlspecialchars($teacher['teacher_code']); ?></code>
                        </div>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th class="text-muted" style="width:40%">Học vị</th>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($teacher['degree'] ?? '—'); ?></span></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Chuyên ngành</th>
                                <td><?php echo htmlspecialchars($teacher['specialization'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Bộ môn</th>
                                <td><?php echo htmlspecialchars($teacher['department_name'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Khoa</th>
                                <td><?php echo htmlspecialchars($teacher['faculty_name'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Email</th>
                                <td><?php echo htmlspecialchars($teacher['email'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">SĐT</th>
                                <td><?php echo htmlspecialchars($teacher['phone'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Tài khoản</th>
                                <td><?php echo htmlspecialchars($teacher['username'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Trạng thái</th>
                                <td>
                                    <?php if (($teacher['user_status'] ?? '') === 'active'): ?>
                                    <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Không hoạt động</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Teaching Load + Edit ── -->
            <div class="col-lg-8">
                <!-- Teaching Load -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-bar-chart-fill me-2" aria-hidden="true"></i>
                        Khối lượng giảng dạy
                        <?php if ($activeSemester): ?>
                        — <span class="text-muted small"><?php echo htmlspecialchars($activeSemester['semester_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$activeSemester): ?>
                        <p class="text-muted mb-0">Không có học kỳ đang hoạt động.</p>
                        <?php else: ?>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="text-center">
                                <div class="display-5 fw-bold <?php echo $teachingLoad > 20 ? 'text-danger' : ($teachingLoad === 0 ? 'text-muted' : 'text-navy'); ?>">
                                    <?php echo $teachingLoad; ?>
                                </div>
                                <div class="text-muted small">tín chỉ</div>
                            </div>
                            <div>
                                <?php if ($teachingLoad > 20): ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Quá tải (&gt;20 TC)
                                </span>
                                <?php elseif ($teachingLoad === 0): ?>
                                <span class="badge bg-secondary">Chưa được phân công</span>
                                <?php else: ?>
                                <span class="badge bg-success">Bình thường</span>
                                <?php endif; ?>
                                <div class="text-muted small mt-1"><?php echo count($assignedSections); ?> lớp học phần</div>
                            </div>
                        </div>

                        <?php if (!empty($assignedSections)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Mã lớp</th>
                                        <th>Môn học</th>
                                        <th>Tín chỉ</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedSections as $sec): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($sec['section_code']); ?></code></td>
                                        <td><?php echo htmlspecialchars($sec['subject_name']); ?></td>
                                        <td><?php echo (int)$sec['credits']; ?></td>
                                        <td>
                                            <?php
                                            $statusMap = ['open' => ['success','Đang mở'], 'closed' => ['secondary','Đã đóng']];
                                            $st = $statusMap[$sec['status']] ?? ['light','—'];
                                            ?>
                                            <span class="badge bg-<?php echo $st[0]; ?>"><?php echo $st[1]; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">Chưa được phân công lớp học phần nào trong học kỳ này.</p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Edit Profile (faculty_manager only) -->
                <?php if (isFacultyManager()): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pencil-fill me-2" aria-hidden="true"></i>Cập nhật hồ sơ
                    </div>
                    <div class="card-body">
                        <form method="post" action="teacher_detail.php?id=<?php echo $teacherId; ?>">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit_degree" class="form-label">Học vị <span class="text-danger">*</span></label>
                                    <select id="edit_degree" name="degree" class="form-select" required>
                                        <?php foreach ($allowedDegrees as $deg): ?>
                                        <option value="<?php echo htmlspecialchars($deg); ?>"
                                            <?php echo ($teacher['degree'] ?? '') === $deg ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($deg); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_specialization" class="form-label">Chuyên ngành</label>
                                    <input type="text" id="edit_specialization" name="specialization" class="form-control"
                                           value="<?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" id="edit_email" name="email" class="form-control"
                                           value="<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_phone" class="form-label">Số điện thoại</label>
                                    <input type="text" id="edit_phone" name="phone" class="form-control"
                                           value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-navy">
                                        <i class="bi bi-save me-1" aria-hidden="true"></i>Lưu thay đổi
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<?php include 'includes/footer.php'; ?>
