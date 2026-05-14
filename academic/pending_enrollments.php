<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Cho xu ly dang ky tu dong';
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yeu cau khong hop le.'];
        header('Location: pending_enrollments.php');
        exit();
    }

    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chi Truong phong moi co quyen cap nhat.'];
        header('Location: pending_enrollments.php');
        exit();
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $status = match ($action) {
        'resolve' => 'resolved',
        'ignore' => 'ignored',
        default => '',
    };

    if ($id > 0 && $status !== '') {
        $stmt = $conn->prepare(
            "UPDATE pending_enrollments
             SET status = ?, note = ?, resolved_by = ?, resolved_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->bind_param('ssii', $status, $note, $userId, $id);
        $stmt->execute();
        $_SESSION['_flash'] = $stmt->affected_rows > 0
            ? ['type' => 'success', 'message' => 'Da cap nhat dong cho xu ly.']
            : ['type' => 'warning', 'message' => 'Dong nay da duoc xu ly truoc do.'];
        $stmt->close();
    }

    header('Location: pending_enrollments.php');
    exit();
}

$flash = getFlash();
$status = trim($_GET['status'] ?? 'pending');
$allowedStatuses = ['pending','resolved','ignored','all'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'pending';
}

$where = $status === 'all' ? '1=1' : 'pe.status = ?';
$sql = "
    SELECT pe.*, st.student_code, u.full_name, subj.subject_code, subj.subject_name,
           sm.semester_name, sm.school_year, tc.cohort_code, tc.cohort_name,
           tp.program_name, ru.full_name AS resolved_by_name
    FROM pending_enrollments pe
    JOIN students st ON st.id = pe.student_id
    JOIN users u ON u.id = st.user_id
    JOIN subjects subj ON subj.id = pe.subject_id
    JOIN semesters sm ON sm.id = pe.semester_id
    LEFT JOIN training_cohorts tc ON tc.id = pe.cohort_id
    LEFT JOIN training_programs tp ON tp.id = pe.program_id
    LEFT JOIN users ru ON ru.id = pe.resolved_by
    WHERE $where
    ORDER BY FIELD(pe.status, 'pending','resolved','ignored'), pe.created_at DESC
";
$stmt = $conn->prepare($sql);
if ($status !== 'all') {
    $stmt->bind_param('s', $status);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['pending' => 0, 'resolved' => 0, 'ignored' => 0];
$countResult = $conn->query("SELECT status, COUNT(*) AS c FROM pending_enrollments GROUP BY status");
while ($row = $countResult->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['c'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title"><i class="bi bi-hourglass-split me-2 text-navy"></i>Chờ xử lý đăng ký tự động</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
    </div>

    <div class="admin-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-3">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small">Trạng thái</label>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Chờ xử lý (<?php echo $counts['pending']; ?>)</option>
                            <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Đã xử lý (<?php echo $counts['resolved']; ?>)</option>
                            <option value="ignored" <?php echo $status === 'ignored' ? 'selected' : ''; ?>>Bỏ qua (<?php echo $counts['ignored']; ?>)</option>
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <a href="course_sections.php" class="btn btn-sm btn-navy">
                            <i class="bi bi-plus-circle me-1"></i>Mở lớp học phần
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-2"></i>Danh sách cần xử lý</span>
                <span class="badge bg-light text-dark"><?php echo number_format(count($rows)); ?></span>
            </div>
            <?php if (empty($rows)): ?>
                <div class="card-body text-center text-muted py-4">
                    <i class="bi bi-check-circle fs-2 d-block mb-2"></i>Không có dữ liệu.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sinh viên</th>
                                <th>Môn HK1</th>
                                <th>Học kỳ</th>
                                <th>Khóa/CTĐT</th>
                                <th>Lý do</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $badge = ['pending' => 'warning', 'resolved' => 'success', 'ignored' => 'secondary'][$row['status']] ?? 'secondary';
                            $label = ['pending' => 'Chờ xử lý', 'resolved' => 'Đã xử lý', 'ignored' => 'Bỏ qua'][$row['status']] ?? $row['status'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <code class="small"><?php echo htmlspecialchars($row['student_code']); ?></code>
                                </td>
                                <td>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                    <code class="small"><?php echo htmlspecialchars($row['subject_code']); ?></code>
                                </td>
                                <td class="small">
                                    <?php echo htmlspecialchars($row['semester_name']); ?><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($row['school_year']); ?></span>
                                </td>
                                <td class="small">
                                    <?php echo htmlspecialchars($row['cohort_code'] ?: 'Chưa gắn khóa'); ?><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($row['program_name'] ?: $row['cohort_name'] ?: ''); ?></span>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span>
                                    <?php if ($row['resolved_at']): ?>
                                        <div class="small text-muted mt-1"><?php echo date('d/m/Y H:i', strtotime($row['resolved_at'])); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($row['status'] === 'pending' && isAcademicManager()): ?>
                                        <form method="post" class="d-inline-flex gap-1 align-items-center">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="text" name="note" class="form-control form-control-sm" style="width: 180px" placeholder="Ghi chú">
                                            <button type="submit" name="action" value="resolve" class="btn btn-sm btn-success">Đã xử lý</button>
                                            <button type="submit" name="action" value="ignore" class="btn btn-sm btn-outline-secondary">Bỏ qua</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small text-muted"><?php echo htmlspecialchars($row['note'] ?: $row['resolved_by_name'] ?: ''); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
