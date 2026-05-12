<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);
$pageTitle = 'Dashboard';

$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);

// Nếu không có faculty_id và không phải admin → báo lỗi
if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào. Vui lòng liên hệ quản trị viên.'];
    header('Location: /university/login.php');
    exit();
}

// ── Warning Panel ─────────────────────────────────────────────
$warnings = getDashboardWarnings($conn, $facultyId);

// ── Summary Stats ─────────────────────────────────────────────
// Admin (facultyId=0) → thống kê toàn trường
if ($facultyId > 0) {
    // Tổng GV
    $stmtGV = $conn->prepare("SELECT COUNT(*) AS c FROM teachers WHERE faculty_id = ?");
    $stmtGV->bind_param('i', $facultyId);
    $stmtGV->execute();
    $totalTeachers = (int)($stmtGV->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtGV->close();

    // Tổng SV đang học
    $stmtSV = $conn->prepare(
        "SELECT COUNT(*) AS c FROM students s
         JOIN classes cl ON s.class_id = cl.id
         JOIN majors m ON cl.major_id = m.id
         WHERE m.faculty_id = ? AND s.academic_status = 'đang học'"
    );
    $stmtSV->bind_param('i', $facultyId);
    $stmtSV->execute();
    $totalStudents = (int)($stmtSV->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtSV->close();

    // Tổng ngành
    $stmtMajors = $conn->prepare("SELECT COUNT(*) AS c FROM majors WHERE faculty_id = ?");
    $stmtMajors->bind_param('i', $facultyId);
    $stmtMajors->execute();
    $totalMajors = (int)($stmtMajors->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtMajors->close();

    // Tổng lớp HP đang mở (học kỳ active)
    $stmtCS = $conn->prepare(
        "SELECT COUNT(*) AS c FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id
         JOIN majors m ON cur.major_id = m.id
         WHERE m.faculty_id = ? AND cs.status = 'open'
           AND cs.semester_id = (SELECT id FROM semesters WHERE status='active' LIMIT 1)"
    );
    $stmtCS->bind_param('i', $facultyId);
    $stmtCS->execute();
    $totalSections = (int)($stmtCS->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCS->close();

    // Đề xuất mở lớp đang chờ duyệt (proposal_status = 'pending' trong course_sections)
    $stmtPending = $conn->prepare(
        "SELECT COUNT(*) AS c FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         WHERE m.faculty_id = ? AND cs.proposal_status = 'pending'"
    );
    $stmtPending->bind_param('i', $facultyId);
    $stmtPending->execute();
    $pendingProposals = (int)($stmtPending->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtPending->close();

    // Thông báo gần đây (7 ngày) — notifications là broadcast, đếm chung
    $stmtNotif = $conn->prepare(
        "SELECT COUNT(*) AS c FROM notifications
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $stmtNotif->execute();
    $recentNotifications = (int)($stmtNotif->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtNotif->close();

} else {
    // Admin: toàn trường
    $totalTeachers = (int)($conn->query("SELECT COUNT(*) AS c FROM teachers")->fetch_assoc()['c'] ?? 0);
    $totalStudents = (int)($conn->query("SELECT COUNT(*) AS c FROM students WHERE academic_status = 'đang học'")->fetch_assoc()['c'] ?? 0);
    $totalMajors   = (int)($conn->query("SELECT COUNT(*) AS c FROM majors")->fetch_assoc()['c'] ?? 0);
    $totalSections = (int)($conn->query(
        "SELECT COUNT(*) AS c FROM course_sections
         WHERE status = 'open'
           AND semester_id = (SELECT id FROM semesters WHERE status='active' LIMIT 1)"
    )->fetch_assoc()['c'] ?? 0);
    $pendingProposals    = (int)($conn->query(
        "SELECT COUNT(*) AS c FROM course_sections WHERE proposal_status = 'pending'"
    )->fetch_assoc()['c'] ?? 0);
    $recentNotifications = (int)($conn->query(
        "SELECT COUNT(*) AS c FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetch_assoc()['c'] ?? 0);
}

// ── Flash message ─────────────────────────────────────────────
$flash = getFlash();

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
                <i class="bi bi-speedometer2 me-2 text-navy" aria-hidden="true"></i>Dashboard
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

    <!-- Main Content -->
    <div class="admin-content">

        <!-- Flash Message -->
        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php endif; ?>

        <!-- ── Warning Panel ──────────────────────────────────── -->
        <?php
        $totalWarnings = array_sum($warnings);
        if ($totalWarnings === 0):
        ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5" aria-hidden="true"></i>
            <span>✓ Không có cảnh báo nào. Mọi hoạt động đang diễn ra bình thường.</span>
        </div>
        <?php else: ?>
        <div class="mb-4">
            <h6 class="fw-bold text-danger mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                Cảnh báo cần xử lý (<?php echo $totalWarnings; ?>)
            </h6>
            <div class="row g-3">

                <?php if ($warnings['no_teacher'] > 0): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card border-danger h-100">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="flex-shrink-0 text-danger" style="font-size:2rem;" aria-hidden="true">
                                <i class="bi bi-person-x-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4 text-danger lh-1 mb-1">
                                    <?php echo number_format($warnings['no_teacher']); ?>
                                </div>
                                <div class="text-muted small mb-2">Lớp học phần chưa có giảng viên</div>
                                <a href="/university/faculty/proposals.php?tab=assign"
                                   class="btn btn-sm btn-outline-danger">
                                    Xem chi tiết →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($warnings['no_exam'] > 0): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card border-warning h-100">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="flex-shrink-0 text-warning" style="font-size:2rem;" aria-hidden="true">
                                <i class="bi bi-calendar-x-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4 text-warning lh-1 mb-1">
                                    <?php echo number_format($warnings['no_exam']); ?>
                                </div>
                                <div class="text-muted small mb-2">Lớp học phần chưa có lịch thi</div>
                                <a href="/university/faculty/exam_schedules.php"
                                   class="btn btn-sm btn-outline-warning">
                                    Xem chi tiết →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($warnings['overloaded'] > 0): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card border-warning h-100">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="flex-shrink-0 text-warning" style="font-size:2rem;" aria-hidden="true">
                                <i class="bi bi-bar-chart-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4 text-warning lh-1 mb-1">
                                    <?php echo number_format($warnings['overloaded']); ?>
                                </div>
                                <div class="text-muted small mb-2">Giảng viên quá tải (&gt;20 tín chỉ)</div>
                                <a href="/university/faculty/teaching_load.php"
                                   class="btn btn-sm btn-outline-warning">
                                    Xem chi tiết →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($warnings['academic_warning'] > 0): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card border-danger h-100">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="flex-shrink-0 text-danger" style="font-size:2rem;" aria-hidden="true">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4 text-danger lh-1 mb-1">
                                    <?php echo number_format($warnings['academic_warning']); ?>
                                </div>
                                <div class="text-muted small mb-2">Sinh viên cảnh báo học vụ</div>
                                <a href="/university/faculty/academic_warnings.php"
                                   class="btn btn-sm btn-outline-danger">
                                    Xem chi tiết →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($warnings['curriculum_incomplete'] > 0): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card border-info h-100">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="flex-shrink-0 text-info" style="font-size:2rem;" aria-hidden="true">
                                <i class="bi bi-journal-x"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4 text-info lh-1 mb-1">
                                    <?php echo number_format($warnings['curriculum_incomplete']); ?>
                                </div>
                                <div class="text-muted small mb-2">Ngành thiếu tín chỉ CTĐT (&lt;120TC)</div>
                                <a href="/university/faculty/curriculum.php"
                                   class="btn btn-sm btn-outline-info">
                                    Xem chi tiết →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

        <!-- ── Summary Stats ──────────────────────────────────── -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-2">
                    <div class="stat-icon"><i class="bi bi-person-badge-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalTeachers); ?></div>
                    <div class="stat-label">Giảng viên</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-1">
                    <div class="stat-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalStudents); ?></div>
                    <div class="stat-label">Sinh viên đang học</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-3">
                    <div class="stat-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalMajors); ?></div>
                    <div class="stat-label">Ngành đào tạo</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-4">
                    <div class="stat-icon"><i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalSections); ?></div>
                    <div class="stat-label">Lớp HP đang mở</div>
                </div>
            </div>
        </div>

        <!-- ── Quick Links ────────────────────────────────────── -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-send-fill me-2" aria-hidden="true"></i>Đề xuất đang chờ duyệt
                        </span>
                        <a href="/university/faculty/proposals.php" class="btn btn-sm btn-outline-light">
                            Xem tất cả
                        </a>
                    </div>
                    <div class="card-body d-flex align-items-center gap-4">
                        <div class="text-center">
                            <div class="display-4 fw-bold <?php echo $pendingProposals > 0 ? 'text-warning' : 'text-success'; ?>">
                                <?php echo number_format($pendingProposals); ?>
                            </div>
                            <div class="text-muted small">đề xuất chờ duyệt</div>
                        </div>
                        <div class="flex-grow-1">
                            <?php if ($pendingProposals > 0): ?>
                            <p class="mb-2 text-muted small">
                                Có <strong><?php echo number_format($pendingProposals); ?></strong> đề xuất
                                đang chờ xem xét và phê duyệt.
                            </p>
                            <a href="/university/faculty/proposals.php" class="btn btn-sm btn-warning">
                                <i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>Xử lý ngay
                            </a>
                            <?php else: ?>
                            <p class="mb-0 text-success small">
                                <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
                                Không có đề xuất nào đang chờ duyệt.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-bell-fill me-2" aria-hidden="true"></i>Thông báo gần đây
                        </span>
                        <a href="/university/faculty/notifications.php" class="btn btn-sm btn-outline-light">
                            Xem tất cả
                        </a>
                    </div>
                    <div class="card-body d-flex align-items-center gap-4">
                        <div class="text-center">
                            <div class="display-4 fw-bold <?php echo $recentNotifications > 0 ? 'text-navy' : 'text-muted'; ?>">
                                <?php echo number_format($recentNotifications); ?>
                            </div>
                            <div class="text-muted small">trong 7 ngày qua</div>
                        </div>
                        <div class="flex-grow-1">
                            <?php if ($recentNotifications > 0): ?>
                            <p class="mb-2 text-muted small">
                                Có <strong><?php echo number_format($recentNotifications); ?></strong> thông báo
                                được gửi trong 7 ngày gần nhất.
                            </p>
                            <a href="/university/faculty/notifications.php" class="btn btn-sm btn-navy">
                                <i class="bi bi-bell me-1" aria-hidden="true"></i>Xem thông báo
                            </a>
                            <?php else: ?>
                            <p class="mb-0 text-muted small">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                Chưa có thông báo nào trong 7 ngày qua.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<?php include 'includes/footer.php'; ?>
