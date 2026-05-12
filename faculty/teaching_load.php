<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Khối lượng Giảng dạy';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

// ── Lấy danh sách học kỳ ─────────────────────────────────────
$semesters = [];
$stmtSem = $conn->prepare("SELECT id, semester_name, status FROM semesters ORDER BY id DESC");
$stmtSem->execute();
$semResult = $stmtSem->get_result();
while ($row = $semResult->fetch_assoc()) {
    $semesters[] = $row;
}
$stmtSem->close();

// Học kỳ được chọn
$activeSemester = getActiveSemester($conn);
$selectedSemId  = (int)($_GET['semester_id'] ?? ($activeSemester['id'] ?? 0));
if ($selectedSemId <= 0 && !empty($semesters)) {
    $selectedSemId = (int)$semesters[0]['id'];
}

// ── Lấy teaching load ────────────────────────────────────────
$loadData = [];
$totalFacultyCredits = 0;

if ($selectedSemId > 0) {
    $stmtLoad = $conn->prepare(
        "SELECT t.id, t.teacher_code, t.degree,
                u.full_name,
                COALESCE(SUM(s.credits), 0) AS total_credits,
                COUNT(cs.id) AS total_sections
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN course_sections cs ON cs.teacher_id = t.id
             AND cs.semester_id = ?
             AND cs.status IN ('open','closed')
         LEFT JOIN subjects s ON cs.subject_id = s.id
         WHERE t.faculty_id = ?
         GROUP BY t.id, t.teacher_code, t.degree, u.full_name
         ORDER BY total_credits DESC, u.full_name ASC"
    );
    $stmtLoad->bind_param('ii', $selectedSemId, $facultyId);
    $stmtLoad->execute();
    $loadResult = $stmtLoad->get_result();
    while ($row = $loadResult->fetch_assoc()) {
        $loadData[] = $row;
        $totalFacultyCredits += (int)$row['total_credits'];
    }
    $stmtLoad->close();

    // Lấy danh sách lớp HP cho từng GV
    if (!empty($loadData)) {
        $teacherIds = array_column($loadData, 'id');
        $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
        $types = str_repeat('i', count($teacherIds)) . 'i';
        $params = array_merge($teacherIds, [$selectedSemId]);

        $stmtSecs = $conn->prepare(
            "SELECT cs.teacher_id, cs.section_code, s.subject_name, s.credits, cs.status
             FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             WHERE cs.teacher_id IN ({$placeholders})
               AND cs.semester_id = ?
               AND cs.status IN ('open','closed')
             ORDER BY s.subject_name ASC"
        );
        $stmtSecs->bind_param($types, ...$params);
        $stmtSecs->execute();
        $secsResult = $stmtSecs->get_result();
        $sectionsByTeacher = [];
        while ($sec = $secsResult->fetch_assoc()) {
            $sectionsByTeacher[$sec['teacher_id']][] = $sec;
        }
        $stmtSecs->close();

        // Gắn sections vào loadData
        foreach ($loadData as &$t) {
            $t['sections'] = $sectionsByTeacher[$t['id']] ?? [];
        }
        unset($t);
    }
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
                <i class="bi bi-bar-chart-fill me-2 text-navy" aria-hidden="true"></i>Khối lượng Giảng dạy
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

        <!-- Semester selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="teaching_load.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="semester_id" class="form-label">Học kỳ</label>
                        <select id="semester_id" name="semester_id" class="form-select">
                            <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo (int)$sem['id']; ?>"
                                <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name']); ?>
                                <?php if ($sem['status'] === 'active'): ?>(Hiện tại)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedSemId > 0 && !empty($loadData)): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-table me-2" aria-hidden="true"></i>Khối lượng giảng dạy
                </span>
                <span class="badge bg-navy">
                    Tổng toàn khoa: <?php echo number_format($totalFacultyCredits); ?> tín chỉ
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã GV</th>
                            <th>Họ tên</th>
                            <th>Học vị</th>
                            <th class="text-center">Tổng TC</th>
                            <th class="text-center">Số lớp</th>
                            <th>Danh sách lớp HP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loadData as $t): ?>
                        <?php
                        $credits = (int)$t['total_credits'];
                        $rowClass = '';
                        if ($credits > 20) $rowClass = 'table-danger';
                        elseif ($credits === 0) $rowClass = 'table-light';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><code><?php echo htmlspecialchars($t['teacher_code']); ?></code></td>
                            <td>
                                <a href="teacher_detail.php?id=<?php echo (int)$t['id']; ?>">
                                    <?php echo htmlspecialchars($t['full_name']); ?>
                                </a>
                            </td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($t['degree'] ?? '—'); ?></span></td>
                            <td class="text-center fw-bold">
                                <?php if ($credits > 20): ?>
                                <span class="text-danger">
                                    <?php echo $credits; ?>
                                    <i class="bi bi-exclamation-triangle-fill ms-1" aria-label="Quá tải" title="Quá tải (>20 TC)"></i>
                                </span>
                                <?php elseif ($credits === 0): ?>
                                <span class="text-muted">
                                    0
                                    <i class="bi bi-info-circle ms-1" aria-label="Chưa phân công" title="Chưa được phân công"></i>
                                </span>
                                <?php else: ?>
                                <?php echo $credits; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo (int)$t['total_sections']; ?></td>
                            <td>
                                <?php if (!empty($t['sections'])): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($t['sections'] as $sec): ?>
                                    <span class="badge bg-light text-dark border" title="<?php echo htmlspecialchars($sec['subject_name']); ?> (<?php echo (int)$sec['credits']; ?> TC)">
                                        <?php echo htmlspecialchars($sec['section_code']); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="3">Tổng cộng toàn khoa</td>
                            <td class="text-center"><?php echo number_format($totalFacultyCredits); ?></td>
                            <td class="text-center">
                                <?php echo array_sum(array_column($loadData, 'total_sections')); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-3 d-flex gap-3 flex-wrap">
            <span class="badge bg-danger">
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Quá tải (&gt;20 TC)
            </span>
            <span class="badge bg-light text-muted border">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Chưa phân công (0 TC)
            </span>
        </div>

        <?php elseif ($selectedSemId > 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
            Không có dữ liệu giảng viên cho học kỳ này.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
            Vui lòng chọn học kỳ để xem khối lượng giảng dạy.
        </div>
        <?php endif; ?>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<?php include 'includes/footer.php'; ?>
