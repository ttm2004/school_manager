<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Ket qua Hoc tap';
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
$stmtSem = $conn->prepare("SELECT id, semester_name, status FROM semesters ORDER BY id DESC");
$stmtSem->execute();
$semesters = $stmtSem->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSem->close();

$activeSemester = getActiveSemester($conn);
$selectedSemId  = (int)($_GET['semester_id'] ?? ($activeSemester['id'] ?? 0));
$selectedSectionId = (int)($_GET['section_id'] ?? 0);

// Lay thong ke theo section
$sectionStats = [];
if ($selectedSemId > 0) {
    $stmtStats = $conn->prepare(
        "SELECT cs.id, cs.section_code, s.subject_name, s.credits,
                CONCAT(u.full_name) AS teacher_name,
                COUNT(g.id) AS total_enrolled,
                SUM(CASE WHEN g.final_score >= 5.0 THEN 1 ELSE 0 END) AS pass_count,
                COUNT(DISTINCT ss.student_id) AS enrolled_count,
                SUM(CASE WHEN g.final_score >= 5.0 THEN 1 ELSE 0 END) AS pass_count,
                SUM(CASE WHEN g.final_score < 5.0 AND g.final_score IS NOT NULL THEN 1 ELSE 0 END) AS fail_count,
                AVG(g.final_score) AS avg_score
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE m.faculty_id = ? AND cs.semester_id = ?
         GROUP BY cs.id, cs.section_code, s.subject_name, s.credits, teacher_name
         ORDER BY s.subject_name ASC"
    );
    $stmtStats->bind_param('ii', $facultyId, $selectedSemId);
    $stmtStats->execute();
    $rows = $stmtStats->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtStats->close();

    foreach ($rows as $row) {
        $total = (int)$row['total_enrolled'];
        $pass  = (int)$row['pass_count'];
        $row['pass_rate'] = $total > 0 ? round($pass / $total * 100, 1) : null;
        $row['avg_score'] = $row['avg_score'] !== null ? round((float)$row['avg_score'], 2) : null;
        $sectionStats[] = $row;
    }
}

// Grade distribution cho section duoc chon
$gradeDistribution = [];
$selectedSection = null;
if ($selectedSectionId > 0) {
    // Kiem tra section thuoc faculty
    $stmtSecChk = $conn->prepare(
        "SELECT cs.id, cs.section_code, s.subject_name
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         WHERE cs.id = ? AND m.faculty_id = ? LIMIT 1"
    );
    $stmtSecChk->bind_param('ii', $selectedSectionId, $facultyId);
    $stmtSecChk->execute();
    $selectedSection = $stmtSecChk->get_result()->fetch_assoc();
    $stmtSecChk->close();

    if ($selectedSection) {
        $ranges = [
            '0-4'  => [0, 4],
            '4-5'  => [4, 5],
            '5-6'  => [5, 6],
            '6-7'  => [6, 7],
            '7-8'  => [7, 8],
            '8-9'  => [8, 9],
            '9-10' => [9, 10],
        ];

        $stmtGrades = $conn->prepare(
            "SELECT g.final_score FROM grades g
             JOIN student_subjects ss ON g.student_subject_id = ss.id
             WHERE ss.course_section_id = ? AND g.final_score IS NOT NULL"
        );
        $stmtGrades->bind_param('i', $selectedSectionId);
        $stmtGrades->execute();
        $allGrades = $stmtGrades->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtGrades->close();

        $totalGrades = count($allGrades);
        foreach ($ranges as $label => [$min, $max]) {
            $count = 0;
            foreach ($allGrades as $g) {
                $score = (float)$g['final_score'];
                if ($label === '9-10') {
                    if ($score >= $min && $score <= $max) $count++;
                } else {
                    if ($score >= $min && $score < $max) $count++;
                }
            }
            $gradeDistribution[$label] = [
                'count' => $count,
                'pct'   => $totalGrades > 0 ? round($count / $totalGrades * 100, 1) : 0,
            ];
        }
    }
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
                <i class="bi bi-graph-up me-2 text-navy" aria-hidden="true"></i>Ket qua Hoc tap
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">
                <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
            </span>
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
                <form method="get" action="grades.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="semester_id" class="form-label">Hoc ky</label>
                        <select id="semester_id" name="semester_id" class="form-select">
                            <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo (int)$sem['id']; ?>"
                                <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name']); ?>
                                <?php if ($sem['status'] === 'active'): ?>(Hien tai)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Xem ket qua hoc tap">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Section stats table -->
        <?php if ($selectedSemId > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-table me-2" aria-hidden="true"></i>Thong ke theo Lop hoc phan
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Ma lop</th>
                            <th>Mon hoc</th>
                            <th>Giang vien</th>
                            <th class="text-center">Si so</th>
                            <th class="text-center">Dat</th>
                            <th class="text-center">Truot</th>
                            <th class="text-center">Ti le dat</th>
                            <th class="text-center">Diem TB</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sectionStats)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Khong co du lieu cho hoc ky nay.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($sectionStats as $sec): ?>
                        <?php
                        $rowClass = '';
                        if ($sec['total_enrolled'] === 0) {
                            // no grades
                        } elseif ($sec['pass_rate'] !== null && $sec['pass_rate'] < 70) {
                            $rowClass = 'table-warning';
                        }
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><code><?php echo htmlspecialchars($sec['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($sec['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($sec['teacher_name'] ?? '—'); ?></td>
                            <td class="text-center"><?php echo (int)$sec['total_enrolled']; ?></td>
                            <td class="text-center text-success"><?php echo (int)$sec['pass_count']; ?></td>
                            <td class="text-center text-danger"><?php echo (int)$sec['fail_count']; ?></td>
                            <td class="text-center">
                                <?php if ($sec['total_enrolled'] === 0): ?>
                                <span class="text-muted small">Chua co diem</span>
                                <?php elseif ($sec['pass_rate'] !== null): ?>
                                <span class="<?php echo $sec['pass_rate'] < 70 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                    <?php echo $sec['pass_rate']; ?>%
                                    <?php if ($sec['pass_rate'] < 70): ?>
                                    <i class="bi bi-exclamation-triangle-fill ms-1" aria-label="Ti le dat thap" title="Ti le dat < 70%"></i>
                                    <?php endif; ?>
                                </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $sec['avg_score'] !== null ? $sec['avg_score'] : '—'; ?></td>
                            <td>
                                <a href="grades.php?semester_id=<?php echo $selectedSemId; ?>&section_id=<?php echo (int)$sec['id']; ?>"
                                   class="btn btn-sm btn-outline-navy"
                                   aria-label="Xem phan phoi diem lop <?php echo htmlspecialchars($sec['section_code']); ?>">
                                    <i class="bi bi-bar-chart" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grade distribution -->
        <?php if ($selectedSection): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart-fill me-2" aria-hidden="true"></i>
                Phan phoi diem — <?php echo htmlspecialchars($selectedSection['section_code']); ?>:
                <?php echo htmlspecialchars($selectedSection['subject_name']); ?>
            </div>
            <div class="card-body">
                <?php if (empty($gradeDistribution)): ?>
                <p class="text-muted mb-0">Chua co du lieu diem.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Khoang diem</th>
                                <th class="text-center">So SV</th>
                                <th class="text-center">Ti le</th>
                                <th>Bieu do</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gradeDistribution as $range => $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($range); ?></td>
                                <td class="text-center"><?php echo $data['count']; ?></td>
                                <td class="text-center"><?php echo $data['pct']; ?>%</td>
                                <td>
                                    <div class="progress" style="height:20px;" role="progressbar"
                                         aria-valuenow="<?php echo $data['pct']; ?>" aria-valuemin="0" aria-valuemax="100"
                                         aria-label="<?php echo htmlspecialchars($range); ?>: <?php echo $data['pct']; ?>%">
                                        <div class="progress-bar bg-navy" style="width:<?php echo $data['pct']; ?>%">
                                            <?php if ($data['pct'] > 5): echo $data['pct'] . '%'; endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot
    </div>
</div>

<?php include 'includes/footer.php'; ?>
