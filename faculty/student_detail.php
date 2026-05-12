<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Hồ sơ Sinh viên';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

$studentId = (int)($_GET['id'] ?? 0);
if ($studentId <= 0) {
    header('Location: students.php');
    exit();
}

// Kiểm tra sinh viên thuộc khoa
$stmtChk = $conn->prepare(
    "SELECT s.id FROM students s JOIN classes cl ON s.class_id = cl.id JOIN majors m ON cl.major_id = m.id WHERE s.id = ? AND m.faculty_id = ? LIMIT 1"
);
$stmtChk->bind_param('ii', $studentId, $facultyId);
$stmtChk->execute();
if ($stmtChk->get_result()->num_rows === 0) {
    $stmtChk->close();
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền xem thông tin sinh viên thuộc khoa khác.'];
    header('Location: students.php');
    exit();
}
$stmtChk->close();

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'add_warning_note') {
        if (!isFacultyManager()) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
            header('Location: student_detail.php?id=' . $studentId);
            exit();
        }
        $warningType = trim($_POST['warning_type'] ?? '');
        $note        = trim($_POST['note'] ?? '');
        $allowedTypes = ['gpa', 'credits', 'retake', 'manual'];

        if (!in_array($warningType, $allowedTypes, true) || $note === '') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: student_detail.php?id=' . $studentId);
            exit();
        }

        $stmtWarn = $conn->prepare(
            "INSERT INTO student_warnings (student_id, faculty_id, warning_type, note, created_by) VALUES (?, ?, ?, ?, ?)"
        );
        $stmtWarn->bind_param('iissi', $studentId, $facultyId, $warningType, $note, $userId);
        $stmtWarn->execute();
        $newId = (int)$conn->insert_id;
        $stmtWarn->close();

        logAudit($conn, $userId, 'create', 'faculty', 'student_warnings', $newId, null,
            json_encode(['student_id' => $studentId, 'warning_type' => $warningType, 'note' => $note]), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã thêm ghi chú cảnh báo.'];
        header('Location: student_detail.php?id=' . $studentId);
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hành động không hợp lệ.'];
    header('Location: student_detail.php?id=' . $studentId);
    exit();
}

// ── GET: Lấy thông tin SV ─────────────────────────────────────
$stmtSV = $conn->prepare(
    "SELECT s.id, s.student_code, s.academic_status, s.enrollment_year,
            u.full_name, u.email, u.phone, u.username, u.status AS user_status,
            m.major_name, m.major_code,
            c.class_name
     FROM students s
     JOIN users u ON s.user_id = u.id
     JOIN classes c ON s.class_id = c.id
     JOIN majors m ON c.major_id = m.id
     WHERE s.id = ? AND m.faculty_id = ?
     LIMIT 1"
);
$stmtSV->bind_param('ii', $studentId, $facultyId);
$stmtSV->execute();
$student = $stmtSV->get_result()->fetch_assoc();
$stmtSV->close();

if (!$student) {
    header('Location: students.php');
    exit();
}

$pageTitle = 'Hồ sơ: ' . $student['full_name'];

// ── GPA & completed credits ───────────────────────────────────
$gpa = calculateStudentGPA($conn, $studentId);

$stmtCredits = $conn->prepare(
    "SELECT COALESCE(SUM(s.credits), 0) AS total_credits
     FROM grades g
     JOIN student_subjects ss ON g.student_subject_id = ss.id
     JOIN course_sections cs ON ss.course_section_id = cs.id
     JOIN subjects s ON cs.subject_id = s.id
     WHERE ss.student_id = ? AND g.final_score >= 5.0"
);
$stmtCredits->bind_param('i', $studentId);
$stmtCredits->execute();
$completedCredits = (int)($stmtCredits->get_result()->fetch_assoc()['total_credits'] ?? 0);
$stmtCredits->close();

// ── Academic warnings ─────────────────────────────────────────
$warnings = getAcademicWarnings($conn, $studentId);

// ── Lớp HP đăng ký học kỳ hiện tại ──────────────────────────
$activeSemester = getActiveSemester($conn);
$activeSemId    = $activeSemester ? (int)$activeSemester['id'] : 0;
$currentSections = [];

if ($activeSemId > 0) {
    $stmtSecs = $conn->prepare(
        "SELECT cs.section_code, s.subject_name, s.credits, cs.status,
                g.midterm_score, g.final_score, g.total_score AS gpa_score
         FROM grades g
         JOIN student_subjects ss ON g.student_subject_id = ss.id
         JOIN course_sections cs ON ss.course_section_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         WHERE ss.student_id = ? AND cs.semester_id = ?
         ORDER BY s.subject_name ASC"
    );
    $stmtSecs->bind_param('ii', $studentId, $activeSemId);
    $stmtSecs->execute();
    $currentSections = $stmtSecs->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSecs->close();
}

// ── Warning notes ─────────────────────────────────────────────
$stmtNotes = $conn->prepare(
    "SELECT sw.*, u.full_name AS created_by_name
     FROM student_warnings sw
     JOIN users u ON sw.created_by = u.id
     WHERE sw.student_id = ? AND sw.faculty_id = ?
     ORDER BY sw.created_at DESC
     LIMIT 10"
);
$stmtNotes->bind_param('ii', $studentId, $facultyId);
$stmtNotes->execute();
$warningNotes = $stmtNotes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtNotes->close();

$flash = getFlash();

$warningTypeLabels = [
    'gpa'     => ['danger', 'GPA thấp'],
    'credits' => ['warning', 'Tỷ lệ trượt cao'],
    'retake'  => ['warning', 'Học lại nhiều lần'],
    'manual'  => ['info', 'Ghi chú thủ công'],
];

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
                <i class="bi bi-person-fill me-2 text-navy" aria-hidden="true"></i>Hồ sơ Sinh viên
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="students.php" class="btn btn-sm btn-outline-secondary">
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

        <!-- Academic warning badges -->
        <?php if (!empty($warnings)): ?>
        <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
            <strong>Cảnh báo học vụ:</strong>
            <?php foreach ($warnings as $w): ?>
            <?php $wl = $warningTypeLabels[$w] ?? ['secondary', $w]; ?>
            <span class="badge bg-<?php echo $wl[0]; ?>"><?php echo htmlspecialchars($wl[1]); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile -->
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
                            <h5 class="mt-2 mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                            <code class="text-muted"><?php echo htmlspecialchars($student['student_code']); ?></code>
                        </div>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th class="text-muted" style="width:45%">Ngành</th>
                                <td><?php echo htmlspecialchars($student['major_name']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Lớp</th>
                                <td><?php echo htmlspecialchars($student['class_name'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Trạng thái</th>
                                <td>
                                    <?php $badge = $statusBadgeMap[$student['academic_status']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?php echo $badge; ?>">
                                        <?php echo htmlspecialchars($student['academic_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Năm nhập học</th>
                                <td><?php echo htmlspecialchars($student['enrollment_year'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Email</th>
                                <td><?php echo htmlspecialchars($student['email'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">SĐT</th>
                                <td><?php echo htmlspecialchars($student['phone'] ?? '—'); ?></td>
                            </tr>
                        </table>

                        <!-- GPA & Credits -->
                        <hr>
                        <div class="row text-center g-2">
                            <div class="col-6">
                                <div class="fw-bold fs-4 <?php echo ($gpa !== null && $gpa < 4.0) ? 'text-danger' : 'text-navy'; ?>">
                                    <?php echo $gpa !== null ? number_format($gpa, 2) : '—'; ?>
                                </div>
                                <div class="text-muted small">GPA tích lũy (thang 10)</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold fs-4 text-navy"><?php echo $completedCredits; ?></div>
                                <div class="text-muted small">Tín chỉ đã hoàn thành</div>
                            </div>
                        </div>
                        <?php if ($gpa !== null && $gpa < 4.0): ?>
                        <div class="mt-2">
                            <span class="badge bg-danger w-100">
                                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Cảnh báo học vụ (GPA &lt; 4.0)
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right column -->
            <div class="col-lg-8">
                <!-- Current semester sections -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-journal-text me-2" aria-hidden="true"></i>
                        Lớp học phần học kỳ hiện tại
                        <?php if ($activeSemester): ?>
                        <span class="text-muted small ms-2"><?php echo htmlspecialchars($activeSemester['semester_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$activeSemester): ?>
                        <p class="text-muted mb-0">Không có học kỳ đang hoạt động.</p>
                        <?php elseif (empty($currentSections)): ?>
                        <p class="text-muted mb-0">Chưa đăng ký lớp học phần nào trong học kỳ này.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Mã lớp</th>
                                        <th>Môn học</th>
                                        <th class="text-center">TC</th>
                                        <th class="text-center">Giữa kỳ</th>
                                        <th class="text-center">Cuối kỳ</th>
                                        <th class="text-center">GPA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentSections as $sec): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($sec['section_code']); ?></code></td>
                                        <td><?php echo htmlspecialchars($sec['subject_name']); ?></td>
                                        <td class="text-center"><?php echo (int)$sec['credits']; ?></td>
                                        <td class="text-center"><?php echo $sec['midterm_score'] !== null ? number_format((float)$sec['midterm_score'], 1) : '—'; ?></td>
                                        <td class="text-center">
                                            <?php if ($sec['final_score'] !== null): ?>
                                            <span class="<?php echo (float)$sec['final_score'] < 5.0 ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo number_format((float)$sec['final_score'], 1); ?>
                                            </span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $sec['gpa_score'] !== null ? number_format((float)$sec['gpa_score'], 2) : '—'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Warning notes -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-journal-text me-2" aria-hidden="true"></i>Ghi chú can thiệp học vụ
                        </span>
                        <?php if (isFacultyManager()): ?>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#addNoteModal"
                                aria-label="Thêm ghi chú cảnh báo">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Thêm ghi chú
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($warningNotes)): ?>
                        <p class="text-muted mb-0">Chưa có ghi chú can thiệp nào.</p>
                        <?php else: ?>
                        <?php foreach ($warningNotes as $note): ?>
                        <?php $wl = $warningTypeLabels[$note['warning_type']] ?? ['secondary', $note['warning_type']]; ?>
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge bg-<?php echo $wl[0]; ?>"><?php echo htmlspecialchars($wl[1]); ?></span>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($note['created_by_name']); ?>
                                    — <?php echo htmlspecialchars($note['created_at']); ?>
                                </small>
                            </div>
                            <p class="mb-0 small"><?php echo htmlspecialchars($note['note']); ?></p>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<!-- Add Warning Note Modal -->
<?php if (isFacultyManager()): ?>
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="student_detail.php?id=<?php echo $studentId; ?>">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add_warning_note">
                <div class="modal-header">
                    <h5 class="modal-title" id="addNoteModalLabel">Thêm ghi chú can thiệp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="warning_type" class="form-label">Loại cảnh báo <span class="text-danger">*</span></label>
                        <select id="warning_type" name="warning_type" class="form-select" required>
                            <option value="gpa">GPA thấp</option>
                            <option value="credits">Tỷ lệ trượt cao</option>
                            <option value="retake">Học lại nhiều lần</option>
                            <option value="manual">Ghi chú thủ công</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="note_content" class="form-label">Nội dung ghi chú <span class="text-danger">*</span></label>
                        <textarea id="note_content" name="note" class="form-control" rows="4" required
                                  placeholder="Nhập nội dung can thiệp, hướng dẫn, hoặc ghi chú..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>Lưu ghi chú
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
