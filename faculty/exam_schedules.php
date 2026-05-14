<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Lich thi Cuoi ky';
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
$majorFilter    = (int)($_GET['major_id'] ?? 0);
$dateFrom       = trim($_GET['date_from'] ?? '');
$dateTo         = trim($_GET['date_to'] ?? '');
$search         = trim($_GET['search'] ?? '');

// Lay danh sach nganh
$majors = [];
$stmtMajors = $conn->prepare("SELECT id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC");
$stmtMajors->bind_param('i', $facultyId);
$stmtMajors->execute();
$majors = $stmtMajors->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMajors->close();

// Lay lich thi
$examSchedules = [];
$totalExams = 0;

if ($selectedSemId > 0) {
    $whereParts = ['m.faculty_id = ?', 'cs.semester_id = ?'];
    $bindTypes  = 'ii';
    $bindValues = [$facultyId, $selectedSemId];

    if ($majorFilter > 0) {
        $whereParts[] = 'm.id = ?';
        $bindTypes   .= 'i';
        $bindValues[] = $majorFilter;
    }
    if ($dateFrom !== '') {
        $whereParts[] = 'fes.exam_date >= ?';
        $bindTypes   .= 's';
        $bindValues[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $whereParts[] = 'fes.exam_date <= ?';
        $bindTypes   .= 's';
        $bindValues[] = $dateTo;
    }
    if ($search !== '') {
        $whereParts[] = 's.subject_name LIKE ?';
        $bindTypes   .= 's';
        $bindValues[] = '%' . $search . '%';
    }

    $whereSQL = implode(' AND ', $whereParts);

    $stmtExams = $conn->prepare(
        "SELECT fes.id, fes.exam_date, fes.start_time AS exam_time_start, fes.end_time AS exam_time_end, fes.room,
                cs.section_code,
                s.subject_name,
                u.full_name AS teacher_name,
                COUNT(DISTINCT ss.student_id) AS enrolled_count
         FROM final_exam_schedules fes
         JOIN course_sections cs ON fes.course_section_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id
         WHERE {$whereSQL}
         GROUP BY fes.id, fes.exam_date, fes.start_time, fes.end_time, fes.room,
                  cs.section_code, s.subject_name, teacher_name
         ORDER BY fes.exam_date ASC, fes.start_time ASC"
    );
    $stmtExams->bind_param($bindTypes, ...$bindValues);
    $stmtExams->execute();
    $examSchedules = $stmtExams->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtExams->close();
    $totalExams = count($examSchedules);
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
                <i class="bi bi-calendar-event-fill me-2 text-navy" aria-hidden="true"></i>Lich thi Cuoi ky
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($selectedSemId > 0 && $totalExams > 0): ?>
            <a href="/university/faculty/export.php?type=exams&semester_id=<?php echo $selectedSemId; ?>"
               class="btn btn-sm btn-outline-success" aria-label="Xuat lich thi ra CSV">
                <i class="bi bi-download me-1" aria-hidden="true"></i>Xuat CSV
            </a>
            <?php endif; ?>
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

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="exam_schedules.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
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
                    <div class="col-6 col-md-2">
                        <label for="major_id" class="form-label">Nganh</label>
                        <select id="major_id" name="major_id" class="form-select">
                            <option value="0">-- Tat ca --</option>
                            <?php foreach ($majors as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"
                                <?php echo $majorFilter === (int)$m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['major_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="date_from" class="form-label">Tu ngay</label>
                        <input type="date" id="date_from" name="date_from" class="form-control"
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="date_to" class="form-label">Den ngay</label>
                        <input type="date" id="date_to" name="date_to" class="form-control"
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="search" class="form-label">Tim kiem</label>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="Ten mon hoc..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Loc lich thi">
                            <i class="bi bi-search" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary -->
        <?php if ($selectedSemId > 0): ?>
        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="badge bg-navy fs-6">
                <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
                Tong: <?php echo $totalExams; ?> lich thi
            </span>
        </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar-event-fill me-2" aria-hidden="true"></i>Lich thi Cuoi ky
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mon hoc</th>
                            <th>Ma lop</th>
                            <th>Ngay thi</th>
                            <th>Gio bat dau</th>
                            <th>Gio ket thuc</th>
                            <th>Phong thi</th>
                            <th class="text-center">Si so</th>
                            <th>Giang vien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($examSchedules)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-calendar-x fs-3 d-block mb-2" aria-hidden="true"></i>
                                <?php echo $selectedSemId > 0 ? 'Chua co lich thi cho hoc ky nay.' : 'Vui long chon hoc ky.'; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($examSchedules as $exam): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exam['subject_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($exam['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($exam['exam_date'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($exam['exam_time_start'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($exam['exam_time_end'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($exam['room'] ?? '—'); ?></td>
                            <td class="text-center"><?php echo (int)$exam['enrolled_count']; ?></td>
                            <td><?php echo htmlspecialchars($exam['teacher_name'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot
    </div>
</div>

<?php include 'includes/footer.php'; ?>
