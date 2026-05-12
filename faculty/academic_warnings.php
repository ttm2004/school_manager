<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Cảnh báo Học vụ';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

$flash   = getFlash();
$majorId = (int)($_GET['major_id'] ?? 0);
$type    = trim($_GET['type'] ?? ''); // gpa | credits | retake | all

// ── Lấy danh sách ngành ───────────────────────────────────────
$majors = [];
$stmtMajors = $conn->prepare("SELECT id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC");
$stmtMajors->bind_param('i', $facultyId);
$stmtMajors->execute();
$majors = $stmtMajors->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMajors->close();

// ── SV có GPA < 4.0 ───────────────────────────────────────────
$gpaWarnStudents = [];
$stmtGPA = $conn->prepare(
    "SELECT s.id, s.student_code, u.full_name, m.major_name, m.id AS major_id,
            AVG(g.total_score) AS avg_gpa
     FROM students s
     JOIN users u ON s.user_id = u.id
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     JOIN student_subjects ss ON ss.student_id = s.id
     JOIN grades g ON g.student_subject_id = ss.id
     WHERE m.faculty_id = ? AND g.total_score IS NOT NULL
     GROUP BY s.id, s.student_code, u.full_name, m.major_name, m.id
     HAVING AVG(g.total_score) < 4.0
     ORDER BY AVG(g.total_score) ASC"
);
$stmtGPA->bind_param('i', $facultyId);
$stmtGPA->execute();
$gpaWarnStudents = $stmtGPA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtGPA->close();

// ── SV có tỷ lệ trượt > 30% học kỳ active ────────────────────
$creditsWarnStudents = [];
$activeSemester = getActiveSemester($conn);
$activeSemId    = $activeSemester ? (int)$activeSemester['id'] : 0;

if ($activeSemId > 0) {
    $stmtCred = $conn->prepare(
        "SELECT s.id, s.student_code, u.full_name, m.major_name, m.id AS major_id,
                COUNT(g.id) AS total,
                SUM(CASE WHEN g.final_score < 5.0 THEN 1 ELSE 0 END) AS failed
         FROM students s
         JOIN users u ON s.user_id = u.id
         JOIN classes cl ON s.class_id = cl.id
         JOIN majors m ON cl.major_id = m.id
         JOIN student_subjects ss ON ss.student_id = s.id
         JOIN grades g ON g.student_subject_id = ss.id
         JOIN course_sections cs ON ss.course_section_id = cs.id
         WHERE m.faculty_id = ? AND cs.semester_id = ?
         GROUP BY s.id, s.student_code, u.full_name, m.major_name, m.id
         HAVING COUNT(g.id) > 0
            AND (SUM(CASE WHEN g.final_score < 5.0 THEN 1 ELSE 0 END) / COUNT(g.id)) > 0.3
         ORDER BY (SUM(CASE WHEN g.final_score < 5.0 THEN 1 ELSE 0 END) / COUNT(g.id)) DESC"
    );
    $stmtCred->bind_param('ii', $facultyId, $activeSemId);
    $stmtCred->execute();
    $creditsWarnStudents = $stmtCred->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCred->close();
}

// ── SV học lại > 2 lần ────────────────────────────────────────
$retakeWarnStudents = [];
$stmtRetake = $conn->prepare(
    "SELECT s.id, s.student_code, u.full_name, m.major_name, m.id AS major_id,
            COUNT(DISTINCT cs.subject_id) AS retake_subjects
     FROM students s
     JOIN users u ON s.user_id = u.id
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     JOIN student_subjects ss ON ss.student_id = s.id
     JOIN grades g ON g.student_subject_id = ss.id
     JOIN course_sections cs ON ss.course_section_id = cs.id
     WHERE m.faculty_id = ?
     GROUP BY s.id, s.student_code, u.full_name, m.major_name, m.id, cs.subject_id
     HAVING COUNT(g.id) > 2"
);
$stmtRetake->bind_param('i', $facultyId);
$stmtRetake->execute();
$retakeWarnStudents = $stmtRetake->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtRetake->close();

// ── Cảnh báo 4: Chậm tiến độ tốt nghiệp ─────────────────────
// SV năm 4+ (enrollment_year <= năm hiện tại - 3) mà tín chỉ tích lũy < 90
$slowGradStudents = [];
$chkEnrollCol = $conn->query("SHOW COLUMNS FROM `students` LIKE 'enrollment_year'");
if ($chkEnrollCol && $chkEnrollCol->num_rows > 0) {
    $slowGradYear = (int)date('Y') - 3;
    $stmtSlow = $conn->prepare(
        "SELECT s.id, s.student_code, u.full_name, m.major_name, m.id AS major_id,
                s.enrollment_year,
                COALESCE(SUM(subj.credits), 0) AS earned_credits
         FROM students s
         JOIN users u ON s.user_id = u.id
         JOIN classes cl ON s.class_id = cl.id
         JOIN majors m ON cl.major_id = m.id
         LEFT JOIN student_subjects ss ON ss.student_id = s.id AND ss.status = 'completed'
         LEFT JOIN course_sections cs ON ss.course_section_id = cs.id
         LEFT JOIN subjects subj ON cs.subject_id = subj.id
         LEFT JOIN grades g ON g.student_subject_id = ss.id AND g.final_score >= 5.0
         WHERE m.faculty_id = ?
           AND s.academic_status = 'Đang học'
           AND s.enrollment_year IS NOT NULL
           AND s.enrollment_year <= ?
         GROUP BY s.id, s.student_code, u.full_name, m.major_name, m.id, s.enrollment_year
         HAVING earned_credits < 90
         ORDER BY s.enrollment_year ASC, earned_credits ASC"
    );
    $stmtSlow->bind_param('ii', $facultyId, $slowGradYear);
    $stmtSlow->execute();
    $slowGradStudents = $stmtSlow->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSlow->close();
}

// ── Cảnh báo 5: Thiếu chuẩn ngoại ngữ ───────────────────────
$noEnglishStudents = [];
$chkEngCol = $conn->query("SHOW COLUMNS FROM `students` LIKE 'english_cert'");
if ($chkEngCol && $chkEngCol->num_rows > 0) {
    $stmtEng = $conn->prepare(
        "SELECT s.id, s.student_code, u.full_name, m.major_name, m.id AS major_id,
                s.enrollment_year, s.english_cert, s.english_cert_score
         FROM students s
         JOIN users u ON s.user_id = u.id
         JOIN classes cl ON s.class_id = cl.id
         JOIN majors m ON cl.major_id = m.id
         WHERE m.faculty_id = ?
           AND s.academic_status = 'Đang học'
           AND (s.english_cert IS NULL OR s.english_cert = '')
         ORDER BY u.full_name ASC"
    );
    $stmtEng->bind_param('i', $facultyId);
    $stmtEng->execute();
    $noEnglishStudents = $stmtEng->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtEng->close();
}

// ── Cảnh báo 6: Nguy cơ thôi học (GPA < 2.0 liên tiếp) ──────
$dropoutRiskStudents = [];
$stmtDrop = $conn->prepare(
    "SELECT s.id, s.student_code, u.full_name, m.major_name, m.id AS major_id,
            AVG(g.total_score) AS avg_gpa,
            COUNT(DISTINCT ss.id) AS total_subjects,
            SUM(CASE WHEN g.final_score < 5.0 THEN 1 ELSE 0 END) AS failed_count
     FROM students s
     JOIN users u ON s.user_id = u.id
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     JOIN student_subjects ss ON ss.student_id = s.id
     JOIN grades g ON g.student_subject_id = ss.id
     WHERE m.faculty_id = ?
       AND s.academic_status = 'Đang học'
       AND g.total_score IS NOT NULL
     GROUP BY s.id, s.student_code, u.full_name, m.major_name, m.id
     HAVING AVG(g.total_score) < 2.0
       AND SUM(CASE WHEN g.final_score < 5.0 THEN 1 ELSE 0 END) >= 3
     ORDER BY AVG(g.total_score) ASC"
);
$stmtDrop->bind_param('i', $facultyId);
$stmtDrop->execute();
$dropoutRiskStudents = $stmtDrop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtDrop->close();

// ── Cảnh báo 7: Đủ điều kiện tốt nghiệp ─────────────────────
$readyToGradStudents = [];
$chkGradReq = $conn->query("SHOW TABLES LIKE 'graduation_requirements'");
if ($chkGradReq && $chkGradReq->num_rows > 0) {
    $stmtGrad = $conn->prepare(
        "SELECT s.id, s.student_code, u.full_name, m.major_name, m.id AS major_id,
                s.enrollment_year, s.english_cert,
                COALESCE(SUM(CASE WHEN g.final_score >= 5.0 THEN subj.credits ELSE 0 END), 0) AS earned_credits,
                AVG(g.total_score) AS avg_gpa,
                gr.min_credits, gr.min_gpa, gr.require_english, gr.min_english_level
         FROM students s
         JOIN users u ON s.user_id = u.id
         JOIN classes cl ON s.class_id = cl.id
         JOIN majors m ON cl.major_id = m.id
         JOIN graduation_requirements gr ON gr.major_id = m.id
         LEFT JOIN student_subjects ss ON ss.student_id = s.id
         LEFT JOIN course_sections cs ON ss.course_section_id = cs.id
         LEFT JOIN subjects subj ON cs.subject_id = subj.id
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE m.faculty_id = ?
           AND s.academic_status = 'Đang học'
         GROUP BY s.id, s.student_code, u.full_name, m.major_name, m.id,
                  s.enrollment_year, s.english_cert,
                  gr.min_credits, gr.min_gpa, gr.require_english, gr.min_english_level
         HAVING earned_credits >= gr.min_credits
            AND avg_gpa >= gr.min_gpa
         ORDER BY u.full_name ASC"
    );
    $stmtGrad->bind_param('i', $facultyId);
    $stmtGrad->execute();
    $readyToGradStudents = $stmtGrad->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtGrad->close();
}

// ── Summary by major ──────────────────────────────────────────
// Gộp tất cả SV cảnh báo (unique)
$allWarnIds = array_unique(array_merge(
    array_column($gpaWarnStudents, 'id'),
    array_column($creditsWarnStudents, 'id'),
    array_column($retakeWarnStudents, 'id'),
    array_column($slowGradStudents, 'id'),
    array_column($dropoutRiskStudents, 'id')
));

// Đếm theo ngành
$warnByMajor = [];
foreach ($majors as $m) {
    $warnByMajor[$m['id']] = ['major_name' => $m['major_name'], 'count' => 0];
}
// Gộp từ tất cả loại
$allWarnStudents = [];
foreach ($gpaWarnStudents as $sv) {
    $allWarnStudents[$sv['id']] = $sv;
    $allWarnStudents[$sv['id']]['types'][] = 'gpa';
}
foreach ($creditsWarnStudents as $sv) {
    if (!isset($allWarnStudents[$sv['id']])) {
        $allWarnStudents[$sv['id']] = $sv;
        $allWarnStudents[$sv['id']]['types'] = [];
    }
    $allWarnStudents[$sv['id']]['types'][] = 'credits';
}
foreach ($retakeWarnStudents as $sv) {
    if (!isset($allWarnStudents[$sv['id']])) {
        $allWarnStudents[$sv['id']] = $sv;
        $allWarnStudents[$sv['id']]['types'] = [];
    }
    $allWarnStudents[$sv['id']]['types'][] = 'retake';
}
foreach ($slowGradStudents as $sv) {
    if (!isset($allWarnStudents[$sv['id']])) {
        $allWarnStudents[$sv['id']] = $sv;
        $allWarnStudents[$sv['id']]['types'] = [];
    }
    $allWarnStudents[$sv['id']]['types'][] = 'slow_grad';
}
foreach ($dropoutRiskStudents as $sv) {
    if (!isset($allWarnStudents[$sv['id']])) {
        $allWarnStudents[$sv['id']] = $sv;
        $allWarnStudents[$sv['id']]['types'] = [];
    }
    $allWarnStudents[$sv['id']]['types'][] = 'dropout_risk';
}
foreach ($noEnglishStudents as $sv) {
    if (!isset($allWarnStudents[$sv['id']])) {
        $allWarnStudents[$sv['id']] = $sv;
        $allWarnStudents[$sv['id']]['types'] = [];
    }
    $allWarnStudents[$sv['id']]['types'][] = 'no_english';
}

foreach ($allWarnStudents as $sv) {
    $mid = $sv['major_id'];
    if (isset($warnByMajor[$mid])) {
        $warnByMajor[$mid]['count']++;
    }
}

// ── Filter by major & type ────────────────────────────────────
$displayStudents = $allWarnStudents;
if ($majorId > 0) {
    $displayStudents = array_filter($displayStudents, fn($sv) => (int)$sv['major_id'] === $majorId);
}
if ($type !== '' && in_array($type, ['gpa', 'credits', 'retake', 'slow_grad', 'dropout_risk', 'no_english'], true)) {
    $displayStudents = array_filter($displayStudents, fn($sv) => in_array($type, $sv['types'] ?? [], true));
}

$warningTypeLabels = [
    'gpa'          => ['danger',    'GPA thấp'],
    'credits'      => ['warning',   'Tỷ lệ trượt cao'],
    'retake'       => ['warning',   'Học lại nhiều lần'],
    'slow_grad'    => ['danger',    'Chậm tiến độ TN'],
    'dropout_risk' => ['danger',    'Nguy cơ thôi học'],
    'no_english'   => ['secondary', 'Thiếu chuẩn NN'],
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
                <i class="bi bi-exclamation-triangle-fill me-2 text-danger" aria-hidden="true"></i>Cảnh báo Học vụ
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

        <!-- Summary stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-1">
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo count($allWarnStudents); ?></div>
                    <div class="stat-label">Tổng cảnh báo</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-2">
                    <div class="stat-icon"><i class="bi bi-graph-down" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo count($gpaWarnStudents); ?></div>
                    <div class="stat-label">GPA thấp</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-3">
                    <div class="stat-icon"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo count($creditsWarnStudents); ?></div>
                    <div class="stat-label">Tỷ lệ trượt cao</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-4">
                    <div class="stat-icon"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo count($retakeWarnStudents); ?></div>
                    <div class="stat-label">Học lại nhiều</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin" style="background:linear-gradient(135deg,#dc3545,#c82333)">
                    <div class="stat-icon text-white"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2 text-white"><?php echo count($slowGradStudents); ?></div>
                    <div class="stat-label text-white">Chậm TN</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin" style="background:linear-gradient(135deg,#6f42c1,#5a32a3)">
                    <div class="stat-icon text-white"><i class="bi bi-person-x-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2 text-white"><?php echo count($dropoutRiskStudents); ?></div>
                    <div class="stat-label text-white">Nguy cơ thôi học</div>
                </div>
            </div>
        </div>

        <!-- Đủ điều kiện tốt nghiệp -->
        <?php if (!empty($readyToGradStudents)): ?>
        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-mortarboard-fill me-2" aria-hidden="true"></i>
                Sinh viên đủ điều kiện tốt nghiệp
                <span class="badge bg-white text-success ms-2"><?php echo count($readyToGradStudents); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã SV</th><th>Họ tên</th><th>Ngành</th>
                            <th class="text-center">TC tích lũy</th>
                            <th class="text-center">GPA</th>
                            <th>Ngoại ngữ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($readyToGradStudents as $sv): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($sv['student_code']); ?></code></td>
                        <td><?php echo htmlspecialchars($sv['full_name']); ?></td>
                        <td class="small"><?php echo htmlspecialchars($sv['major_name']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-success"><?php echo (int)$sv['earned_credits']; ?>/<?php echo (int)$sv['min_credits']; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success"><?php echo number_format((float)$sv['avg_gpa'], 2); ?></span>
                        </td>
                        <td>
                            <?php if ($sv['require_english']): ?>
                            <?php if ($sv['english_cert']): ?>
                            <span class="badge bg-success"><?php echo htmlspecialchars($sv['english_cert']); ?></span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">Chưa có</span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted small">Không yêu cầu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="student_detail.php?id=<?php echo (int)$sv['id']; ?>"
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary by major -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-bar-chart-fill me-2" aria-hidden="true"></i>Cảnh báo theo ngành
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Ngành</th>
                            <th class="text-center">Số SV cảnh báo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warnByMajor as $mid => $mData): ?>
                        <?php if ($mData['count'] === 0) continue; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mData['major_name']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-danger"><?php echo $mData['count']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (array_sum(array_column($warnByMajor, 'count')) === 0): ?>
                        <tr><td colspan="2" class="text-center text-muted">Không có cảnh báo nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="academic_warnings.php" class="row g-3 align-items-end">
                    <div class="col-6 col-md-4">
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
                    <div class="col-6 col-md-3">
                        <label for="type" class="form-label">Loại cảnh báo</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <option value="gpa" <?php echo $type === 'gpa' ? 'selected' : ''; ?>>GPA thấp</option>
                            <option value="credits" <?php echo $type === 'credits' ? 'selected' : ''; ?>>Tỷ lệ trượt cao</option>
                            <option value="retake" <?php echo $type === 'retake' ? 'selected' : ''; ?>>Học lại nhiều lần</option>
                            <option value="slow_grad" <?php echo $type === 'slow_grad' ? 'selected' : ''; ?>>Chậm tiến độ tốt nghiệp</option>
                            <option value="dropout_risk" <?php echo $type === 'dropout_risk' ? 'selected' : ''; ?>>Nguy cơ thôi học</option>
                            <option value="no_english" <?php echo $type === 'no_english' ? 'selected' : ''; ?>>Thiếu chuẩn ngoại ngữ</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Lọc danh sách cảnh báo">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Lọc
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student list -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-people-fill me-2" aria-hidden="true"></i>
                Danh sách Sinh viên cảnh báo
                <span class="badge bg-danger ms-2"><?php echo count($displayStudents); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã SV</th>
                            <th>Họ tên</th>
                            <th>Ngành</th>
                            <th>Loại cảnh báo</th>
                            <th class="text-center">GPA</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($displayStudents)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2" aria-hidden="true"></i>
                                Không có sinh viên cảnh báo nào.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($displayStudents as $sv): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($sv['student_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($sv['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($sv['major_name']); ?></td>
                            <td>
                                <?php foreach ($sv['types'] ?? [] as $wt): ?>
                                <?php $wl = $warningTypeLabels[$wt] ?? ['secondary', $wt]; ?>
                                <span class="badge bg-<?php echo $wl[0]; ?> me-1"><?php echo htmlspecialchars($wl[1]); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $gpaVal = isset($sv['avg_gpa']) ? round((float)$sv['avg_gpa'], 2) : null;
                                if ($gpaVal !== null):
                                ?>
                                <span class="<?php echo $gpaVal < 4.0 ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo number_format($gpaVal, 2); ?>
                                </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
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
        </div>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<?php include 'includes/footer.php'; ?>
