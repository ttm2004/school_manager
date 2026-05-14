<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Bao cao Thong ke';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tai khoan chua duoc gan vao khoa nao.'];
    header('Location: /university/login.php');
    exit();
}

$flash = getFlash();

// Lay danh sach hoc ky
$semesters = [];
$stmtSem = $conn->prepare("SELECT id, semester_name, school_year, status FROM semesters ORDER BY school_year DESC, id DESC");
$stmtSem->execute();
$semesters = $stmtSem->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSem->close();

$activeSemester = getActiveSemester($conn);
$selectedSemId  = (int)($_GET['semester_id'] ?? ($activeSemester['id'] ?? 0));

// Section 1: Tong quan
$totalTeachers = 0;
$studentsByStatus = [];
$totalMajors = 0;
$totalActiveSections = 0;

$stmtT = $conn->prepare("SELECT COUNT(*) AS c FROM teachers WHERE faculty_id = ?");
$stmtT->bind_param('i', $facultyId);
$stmtT->execute();
$totalTeachers = (int)($stmtT->get_result()->fetch_assoc()['c'] ?? 0);
$stmtT->close();

$stmtSV = $conn->prepare(
    "SELECT s.academic_status, COUNT(*) AS c FROM students s
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     WHERE m.faculty_id = ? GROUP BY s.academic_status"
);
$stmtSV->bind_param('i', $facultyId);
$stmtSV->execute();
$svResult = $stmtSV->get_result();
while ($row = $svResult->fetch_assoc()) {
    $studentsByStatus[$row['academic_status']] = (int)$row['c'];
}
$stmtSV->close();

$stmtM = $conn->prepare("SELECT COUNT(*) AS c FROM majors WHERE faculty_id = ?");
$stmtM->bind_param('i', $facultyId);
$stmtM->execute();
$totalMajors = (int)($stmtM->get_result()->fetch_assoc()['c'] ?? 0);
$stmtM->close();

if ($selectedSemId > 0) {
    $stmtCS = $conn->prepare(
        "SELECT COUNT(*) AS c FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         WHERE m.faculty_id = ? AND cs.semester_id = ? AND cs.status = 'open'"
    );
    $stmtCS->bind_param('ii', $facultyId, $selectedSemId);
    $stmtCS->execute();
    $totalActiveSections = (int)($stmtCS->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCS->close();
}

// Section 2: GV theo hoc vi
$teachersByDegree = [];
$stmtDeg = $conn->prepare("SELECT degree, COUNT(*) AS c FROM teachers WHERE faculty_id = ? GROUP BY degree ORDER BY degree");
$stmtDeg->bind_param('i', $facultyId);
$stmtDeg->execute();
$degResult = $stmtDeg->get_result();
while ($row = $degResult->fetch_assoc()) {
    $teachersByDegree[$row['degree']] = (int)$row['c'];
}
$stmtDeg->close();

// Section 3: SV theo nganh
$studentsByMajor = [];
$stmtSVMajor = $conn->prepare(
    "SELECT m.major_name, COUNT(s.id) AS c
     FROM students s
     JOIN classes cl ON s.class_id = cl.id
     JOIN majors m ON cl.major_id = m.id
     WHERE m.faculty_id = ? GROUP BY m.id, m.major_name ORDER BY m.major_name"
);
$stmtSVMajor->bind_param('i', $facultyId);
$stmtSVMajor->execute();
$studentsByMajor = $stmtSVMajor->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSVMajor->close();

// Section 4: Lop HP theo trang thai
$sectionsByStatus = [];
if ($selectedSemId > 0) {
    $stmtSecStatus = $conn->prepare(
        "SELECT cs.status, COUNT(*) AS c
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         WHERE m.faculty_id = ? AND cs.semester_id = ?
         GROUP BY cs.status"
    );
    $stmtSecStatus->bind_param('ii', $facultyId, $selectedSemId);
    $stmtSecStatus->execute();
    $secStatusResult = $stmtSecStatus->get_result();
    while ($row = $secStatusResult->fetch_assoc()) {
        $sectionsByStatus[$row['status']] = (int)$row['c'];
    }
    $stmtSecStatus->close();
}

// Section 5: Chat luong dao tao
$avgPassRate = null;
$lowPassSections = 0;
$academicWarnCount = 0;

if ($selectedSemId > 0) {
    $stmtPassRate = $conn->prepare(
        "SELECT AVG(pass_rate) AS avg_rate, SUM(CASE WHEN pass_rate < 70 THEN 1 ELSE 0 END) AS low_count
         FROM (
             SELECT cs.id,
                    CASE WHEN COUNT(g.id) > 0
                         THEN (SUM(CASE WHEN g.final_score >= 5.0 THEN 1 ELSE 0 END) / COUNT(g.id) * 100)
                         ELSE NULL END AS pass_rate
             FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id
             LEFT JOIN grades g ON g.student_subject_id = ss.id
             WHERE m.faculty_id = ? AND cs.semester_id = ?
             GROUP BY cs.id
         ) x WHERE pass_rate IS NOT NULL"
    );
    $stmtPassRate->bind_param('ii', $facultyId, $selectedSemId);
    $stmtPassRate->execute();
    $prRow = $stmtPassRate->get_result()->fetch_assoc();
    $stmtPassRate->close();
    $avgPassRate = $prRow['avg_rate'] !== null ? round((float)$prRow['avg_rate'], 1) : null;
    $lowPassSections = (int)($prRow['low_count'] ?? 0);

    $stmtWarn = $conn->prepare(
        "SELECT COUNT(*) AS c FROM (
             SELECT ss.student_id, AVG(g.total_score) AS avg_gpa
             FROM grades g
             JOIN student_subjects ss ON g.student_subject_id = ss.id
             JOIN students st ON ss.student_id = st.id
             JOIN classes cl ON st.class_id = cl.id
             JOIN majors m ON cl.major_id = m.id
             WHERE m.faculty_id = ? AND g.total_score IS NOT NULL
             GROUP BY ss.student_id
             HAVING avg_gpa < 4.0
         ) x"
    );
    $stmtWarn->bind_param('i', $facultyId);
    $stmtWarn->execute();
    $academicWarnCount = (int)($stmtWarn->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtWarn->close();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mo/dong menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-clipboard-data-fill me-2 text-navy" aria-hidden="true"></i>Bao cao Thong ke
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="/university/faculty/export.php?type=reports&semester_id=<?php echo $selectedSemId; ?>"
               class="btn btn-sm btn-outline-success" aria-label="Xuat bao cao ra CSV">
                <i class="bi bi-download me-1" aria-hidden="true"></i>Xuat CSV
            </a>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Dang xuat
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dong"></button>
        </div>
        <?php endif; ?>

        <!-- Semester selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="reports.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="semester_id" class="form-label">Hoc ky</label>
                        <select id="semester_id" name="semester_id" class="form-select">
                            <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo (int)$sem['id']; ?>"
                                <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' - ' . ($sem['school_year'] ?? '')); ?>
                                <?php if ($sem['status'] === 'active'): ?>(Hien tai)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Xem bao cao">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Section 1: Tong quan -->
        <h5 class="fw-bold mb-3"><i class="bi bi-grid-3x3-gap-fill me-2 text-navy" aria-hidden="true"></i>1. Tong quan</h5>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-2">
                    <div class="stat-icon"><i class="bi bi-person-badge-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalTeachers); ?></div>
                    <div class="stat-label">Giang vien</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-1">
                    <div class="stat-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($studentsByStatus['dang hoc'] ?? 0); ?></div>
                    <div class="stat-label">SV dang hoc</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-3">
                    <div class="stat-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalMajors); ?></div>
                    <div class="stat-label">Nganh dao tao</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-4">
                    <div class="stat-icon"><i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalActiveSections); ?></div>
                    <div class="stat-label">Lop HP dang mo</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Section 2: GV theo hoc vi -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-mortarboard-fill me-2" aria-hidden="true"></i>2. Giang vien theo Hoc vi
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Hoc vi</th><th class="text-center">So luong</th></tr></thead>
                            <tbody>
                                <?php if (empty($teachersByDegree)): ?>
                                <tr><td colspan="2" class="text-center text-muted">Khong co du lieu.</td></tr>
                                <?php else: ?>
                                <?php foreach ($teachersByDegree as $deg => $cnt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($deg ?: '—'); ?></td>
                                    <td class="text-center"><span class="badge bg-navy"><?php echo $cnt; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Section 3: SV theo nganh -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-people-fill me-2" aria-hidden="true"></i>3. Sinh vien theo Nganh
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Nganh</th><th class="text-center">So SV</th></tr></thead>
                            <tbody>
                                <?php if (empty($studentsByMajor)): ?>
                                <tr><td colspan="2" class="text-center text-muted">Khong co du lieu.</td></tr>
                                <?php else: ?>
                                <?php foreach ($studentsByMajor as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['major_name']); ?></td>
                                    <td class="text-center"><span class="badge bg-navy"><?php echo $row['c']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Section 4: Lop HP theo trang thai -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-grid-3x3-gap-fill me-2" aria-hidden="true"></i>4. Lop Hoc phan theo Trang thai
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Trang thai</th><th class="text-center">So lop</th></tr></thead>
                            <tbody>
                                <?php
                                $statusLabels = ['draft'=>'Nhap','proposed'=>'De xuat','open'=>'Dang mo','closed'=>'Da dong','cancelled'=>'Da huy'];
                                $statusColors = ['draft'=>'secondary','proposed'=>'warning','open'=>'success','closed'=>'info','cancelled'=>'dark'];
                                if (empty($sectionsByStatus)):
                                ?>
                                <tr><td colspan="2" class="text-center text-muted">Khong co du lieu.</td></tr>
                                <?php else: ?>
                                <?php foreach ($sectionsByStatus as $st => $cnt): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo $statusColors[$st] ?? 'secondary'; ?>">
                                            <?php echo htmlspecialchars($statusLabels[$st] ?? $st); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $cnt; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Section 5: Chat luong dao tao -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-graph-up me-2" aria-hidden="true"></i>5. Chat luong Dao tao
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th class="text-muted">Ti le dat TB</th>
                                <td class="fw-bold">
                                    <?php echo $avgPassRate !== null ? $avgPassRate . '%' : '—'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Lop co ti le dat &lt;70%</th>
                                <td>
                                    <?php if ($lowPassSections > 0): ?>
                                    <span class="badge bg-danger"><?php echo $lowPassSections; ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-success">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">SV canh bao hoc vu</th>
                                <td>
                                    <?php if ($academicWarnCount > 0): ?>
                                    <span class="badge bg-warning text-dark"><?php echo $academicWarnCount; ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-success">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot
    </div>
</div>

<?php include 'includes/footer.php'; ?>
