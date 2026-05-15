<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
require_once '../app/Services/AdmissionsEnrollmentService.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Chờ xử lý đăng ký tự động';
$userId = (int)($_SESSION['user_id'] ?? 0);

function pendingEnrollmentRedirect(): string
{
    $params = [];
    foreach (['status', 'mode', 'auto_class_id', 'auto_faculty_id'] as $key) {
        if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
            $params[$key] = trim((string)$_GET[$key]);
        }
    }
    return 'pending_enrollments.php' . ($params ? '?' . http_build_query($params) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yêu cầu không hợp lệ.'];
        header('Location: ' . pendingEnrollmentRedirect());
        exit();
    }

    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ Trưởng phòng mới có quyền cập nhật.'];
        header('Location: ' . pendingEnrollmentRedirect());
        exit();
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'approve_auto_enroll_bulk') {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $_POST['auto_request_ids'] ?? []))));
        $note = trim($_POST['bulk_note'] ?? '');
        $approved = 0;
        $failed = [];

        foreach ($requestIds as $requestId) {
            try {
                AdmissionsEnrollmentService::approveAutoEnrollmentRequest($conn, $requestId, $userId, $note);
                $approved++;
            } catch (Exception $e) {
                $failed[] = '#' . $requestId . ': ' . $e->getMessage();
            }
        }

        if ($approved > 0 && !$failed) {
            $_SESSION['_flash'] = ['type' => 'success', 'message' => "Đã duyệt {$approved} yêu cầu đăng ký HK1."];
        } elseif ($approved > 0) {
            $_SESSION['_flash'] = ['type' => 'warning', 'message' => "Đã duyệt {$approved} yêu cầu. Một số yêu cầu chưa xử lý được: " . implode('; ', array_slice($failed, 0, 3))];
        } else {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $requestIds ? ('Chưa duyệt được yêu cầu nào. ' . implode('; ', array_slice($failed, 0, 3))) : 'Vui lòng chọn ít nhất một yêu cầu để duyệt.'];
        }

        header('Location: ' . pendingEnrollmentRedirect());
        exit();
    }

    $requestId = (int)($_POST['auto_request_id'] ?? 0);
    if ($requestId > 0) {
        $note = trim($_POST['note'] ?? '');
        try {
            if ($action === 'approve_auto_enroll') {
                $result = AdmissionsEnrollmentService::approveAutoEnrollmentRequest($conn, $requestId, $userId, $note);
                $_SESSION['_flash'] = [
                    'type' => 'success',
                    'message' => 'Đã duyệt đăng ký HK1 cho ' . ($result['full_name'] ?? 'sinh viên') . ': ' . (int)($result['enrolled'] ?? 0) . ' môn đã đăng ký, ' . (int)($result['pending'] ?? 0) . ' môn chờ xử lý.'
                ];
            } elseif ($action === 'reject_auto_enroll') {
                AdmissionsEnrollmentService::rejectAutoEnrollmentRequest($conn, $requestId, $userId, $note);
                $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã từ chối yêu cầu đăng ký HK1.'];
            }
        } catch (Exception $e) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }
        header('Location: ' . pendingEnrollmentRedirect());
        exit();
    }

    $id = (int)($_POST['id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($id > 0 && $action === 'retry_pending_enrollment') {
        try {
            $result = AdmissionsEnrollmentService::retryPendingEnrollment($conn, $id, $userId, $note);
            $_SESSION['_flash'] = $result['ok']
                ? ['type' => 'success', 'message' => ($result['full_name'] ?? 'Sinh viên') . ' - ' . ($result['subject_name'] ?? 'môn học') . ': ' . $result['message']]
                : ['type' => 'warning', 'message' => ($result['full_name'] ?? 'Sinh viên') . ' - ' . ($result['subject_name'] ?? 'môn học') . ': ' . $result['message']];
        } catch (Exception $e) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }
        header('Location: ' . pendingEnrollmentRedirect());
        exit();
    }

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
            ? ['type' => 'success', 'message' => 'Đã cập nhật dòng chờ xử lý.']
            : ['type' => 'warning', 'message' => 'Dòng này đã được xử lý trước đó.'];
        $stmt->close();
    }

    header('Location: ' . pendingEnrollmentRedirect());
    exit();
}

$flash = getFlash();
AdmissionsEnrollmentService::ensureAutoEnrollmentRequestSchema($conn);
$status = trim($_GET['status'] ?? 'pending');
$allowedStatuses = ['pending','resolved','ignored','all'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'pending';
}
$filterMode = (($_GET['mode'] ?? 'system') === 'test') ? 'test' : 'system';

$whereParts = ['(pe.data_mode = ? OR (pe.data_mode IS NULL AND st.data_mode = ?))'];
$types = 'ss';
$params = [$filterMode, $filterMode];
if ($status !== 'all') {
    $whereParts[] = 'pe.status = ?';
    $types .= 's';
    $params[] = $status;
}
$where = implode(' AND ', $whereParts);
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
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['pending' => 0, 'resolved' => 0, 'ignored' => 0];
$countStmt = $conn->prepare(
    "SELECT pe.status, COUNT(*) AS c
     FROM pending_enrollments pe
     JOIN students st ON st.id = pe.student_id
     WHERE (pe.data_mode = ? OR (pe.data_mode IS NULL AND st.data_mode = ?))
     GROUP BY pe.status"
);
$countStmt->bind_param('ss', $filterMode, $filterMode);
$countStmt->execute();
$countResult = $countStmt->get_result();
while ($row = $countResult->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['c'];
}
$countStmt->close();

$autoClassId = (int)($_GET['auto_class_id'] ?? 0);
$autoFacultyId = (int)($_GET['auto_faculty_id'] ?? 0);

$autoClasses = [];
$autoClassResult = $conn->query(
    "SELECT DISTINCT c.id, c.class_name, c.class_code
     FROM admission_auto_enrollment_requests aer
     JOIN students st ON st.id = aer.student_id
     JOIN classes c ON c.id = aer.class_id
     WHERE aer.status = 'pending'
       AND st.data_mode = '" . $conn->real_escape_string($filterMode) . "'
     ORDER BY c.class_name, c.class_code"
);
if ($autoClassResult) {
    $autoClasses = $autoClassResult->fetch_all(MYSQLI_ASSOC);
}

$autoFaculties = [];
$autoFacultyResult = $conn->query(
    "SELECT DISTINCT f.id, f.faculty_name
     FROM admission_auto_enrollment_requests aer
     JOIN students st ON st.id = aer.student_id
     JOIN majors m ON m.id = aer.major_id
     JOIN faculties f ON f.id = m.faculty_id
     WHERE aer.status = 'pending'
       AND st.data_mode = '" . $conn->real_escape_string($filterMode) . "'
     ORDER BY f.faculty_name"
);
if ($autoFacultyResult) {
    $autoFaculties = $autoFacultyResult->fetch_all(MYSQLI_ASSOC);
}

$autoRequests = [];
$autoWhere = ["aer.status = 'pending'", "st.data_mode = ?"];
$autoTypes = 's';
$autoParams = [$filterMode];
if ($autoClassId > 0) {
    $autoWhere[] = 'aer.class_id = ?';
    $autoTypes .= 'i';
    $autoParams[] = $autoClassId;
}
if ($autoFacultyId > 0) {
    $autoWhere[] = 'f.id = ?';
    $autoTypes .= 'i';
    $autoParams[] = $autoFacultyId;
}
$autoSql =
    "SELECT aer.*, st.student_code, u.full_name, u.email, c.class_name, c.class_code,
            m.major_name, f.faculty_name, ru.full_name AS requested_by_name, au.full_name AS approved_by_name
     FROM admission_auto_enrollment_requests aer
     JOIN students st ON st.id = aer.student_id
     JOIN users u ON u.id = st.user_id
     JOIN classes c ON c.id = aer.class_id
     JOIN majors m ON m.id = aer.major_id
     LEFT JOIN faculties f ON f.id = m.faculty_id
     LEFT JOIN users ru ON ru.id = aer.requested_by
     LEFT JOIN users au ON au.id = aer.approved_by
     WHERE " . implode(' AND ', $autoWhere) . "
     ORDER BY f.faculty_name, m.major_name, c.class_name, u.full_name";
$autoReqStmt = $conn->prepare($autoSql);
if ($autoReqStmt) {
    if ($autoTypes !== '') {
        $autoReqStmt->bind_param($autoTypes, ...$autoParams);
    }
    $autoReqStmt->execute();
    $autoRequests = $autoReqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $autoReqStmt->close();
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

        <div class="card mb-3" id="autoRequestsCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-check me-2"></i>Yêu cầu duyệt đăng ký môn HK1 sau phân lớp</span>
                <span class="badge bg-warning text-dark"><?php echo number_format(count($autoRequests)); ?> chờ duyệt</span>
            </div>
            <div class="card-body border-bottom">
                <form method="get" class="row g-2 align-items-end" id="autoRequestFilterForm">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold">Chế độ dữ liệu</label>
                        <select name="mode" class="form-select form-select-sm">
                            <option value="system" <?php echo $filterMode === 'system' ? 'selected' : ''; ?>>Dữ liệu thật</option>
                            <option value="test" <?php echo $filterMode === 'test' ? 'selected' : ''; ?>>Test / Demo</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Lọc theo lớp</label>
                        <select name="auto_class_id" class="form-select form-select-sm">
                            <option value="0">Tất cả lớp</option>
                            <?php foreach ($autoClasses as $class): ?>
                                <option value="<?php echo (int)$class['id']; ?>" <?php echo $autoClassId === (int)$class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name'] . (!empty($class['class_code']) ? ' - ' . $class['class_code'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Lọc theo khoa/viện</label>
                        <select name="auto_faculty_id" class="form-select form-select-sm">
                            <option value="0">Tất cả khoa/viện</option>
                            <?php foreach ($autoFaculties as $faculty): ?>
                                <option value="<?php echo (int)$faculty['id']; ?>" <?php echo $autoFacultyId === (int)$faculty['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($faculty['faculty_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-navy">
                            <i class="bi bi-funnel me-1"></i>Lọc
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="pending_enrollments.php?status=<?php echo urlencode($status); ?>&mode=<?php echo urlencode($filterMode); ?>" class="btn btn-sm btn-outline-secondary" id="clearAutoRequestFilters">Bỏ lọc</a>
                    </div>
                </form>
            </div>
            <?php if (empty($autoRequests)): ?>
                <div class="card-body text-center text-muted py-4">
                    <i class="bi bi-check2-circle fs-2 d-block mb-2"></i>Không có yêu cầu đăng ký HK1 nào đang chờ duyệt.
                </div>
            <?php else: ?>
                <?php if (isAcademicManager()): ?>
                    <form method="post" id="bulkAutoEnrollForm" class="card-body border-bottom py-2">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        <input type="hidden" name="action" value="approve_auto_enroll_bulk">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllAutoRequests">
                                <i class="bi bi-check2-square me-1"></i>Chọn tất cả
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAutoRequests">
                                <i class="bi bi-square me-1"></i>Bỏ chọn
                            </button>
                            <input type="text" name="bulk_note" class="form-control form-control-sm" style="max-width: 260px" placeholder="Ghi chú duyệt hàng loạt">
                            <button type="submit" class="btn btn-sm btn-success" id="approveSelectedAutoRequests" disabled>
                                <i class="bi bi-check2-circle me-1"></i>Duyệt các dòng đã chọn
                            </button>
                            <span class="small text-muted" id="selectedAutoRequestCount">Chưa chọn dòng nào</span>
                        </div>
                    </form>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php if (isAcademicManager()): ?>
                                    <th style="width: 44px">
                                        <input class="form-check-input" type="checkbox" id="toggleAutoRequests" aria-label="Chọn tất cả yêu cầu">
                                    </th>
                                <?php endif; ?>
                                <th>Sinh viên</th>
                                <th>Lớp</th>
                                <th>Ngành / Khoa viện</th>
                                <th>Chế độ</th>
                                <th>Người gửi</th>
                                <th class="text-end">Duyệt</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($autoRequests as $req): ?>
                            <tr>
                                <?php if (isAcademicManager()): ?>
                                    <td>
                                        <input class="form-check-input auto-request-check" type="checkbox" name="auto_request_ids[]" value="<?php echo (int)$req['id']; ?>" form="bulkAutoEnrollForm" aria-label="Chọn yêu cầu của <?php echo htmlspecialchars($req['full_name']); ?>">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($req['full_name']); ?></div>
                                    <code class="small"><?php echo htmlspecialchars($req['student_code']); ?></code>
                                    <div class="small text-muted"><?php echo htmlspecialchars($req['email'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($req['class_name']); ?></div>
                                    <code class="small"><?php echo htmlspecialchars($req['class_code'] ?? ''); ?></code>
                                </td>
                                <td class="small">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($req['major_name']); ?></div>
                                    <span class="text-muted"><?php echo htmlspecialchars($req['faculty_name'] ?? 'Chưa gán khoa/viện'); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $req['auto_enroll_mode'] === 'test' ? 'bg-info text-dark' : 'bg-success'; ?>">
                                        <?php echo $req['auto_enroll_mode'] === 'test' ? 'Test/Demo' : 'Dữ liệu thật'; ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?php echo htmlspecialchars($req['requested_by_name'] ?? 'Hệ thống'); ?><br>
                                    <span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if (isAcademicManager()): ?>
                                        <form method="post" class="d-inline-flex gap-1 align-items-center">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                            <input type="hidden" name="auto_request_id" value="<?php echo (int)$req['id']; ?>">
                                            <input type="text" name="note" class="form-control form-control-sm" style="width: 180px" placeholder="Ghi chú">
                                            <button type="submit" name="action" value="approve_auto_enroll" class="btn btn-sm btn-success">
                                                <i class="bi bi-check2 me-1"></i>Duyệt đăng ký HK1
                                            </button>
                                            <button type="submit" name="action" value="reject_auto_enroll" class="btn btn-sm btn-outline-danger">
                                                Từ chối
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small text-muted">Chỉ Trưởng phòng được duyệt.</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="get" class="row g-2 align-items-end">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($filterMode); ?>">
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
                                            <button type="submit" name="action" value="retry_pending_enrollment" class="btn btn-sm btn-primary"
                                                    onclick="return confirm('Kiểm tra lại và đăng ký môn này nếu đã có lớp HP phù hợp?')">
                                                Kiểm tra & đăng ký lại
                                            </button>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    function bindAutoRequestControls(root) {
        root = root || document;
        const checks = Array.from(root.querySelectorAll('.auto-request-check'));
        const toggle = root.querySelector('#toggleAutoRequests');
        const selectAll = root.querySelector('#selectAllAutoRequests');
        const clearAll = root.querySelector('#clearAutoRequests');
        const approveBtn = root.querySelector('#approveSelectedAutoRequests');
        const counter = root.querySelector('#selectedAutoRequestCount');
        const bulkForm = root.querySelector('#bulkAutoEnrollForm');

        function updateBulkState() {
            const selected = checks.filter(function(item) { return item.checked; }).length;
            if (approveBtn) approveBtn.disabled = selected === 0;
            if (counter) counter.textContent = selected > 0 ? ('Đã chọn ' + selected + ' dòng') : 'Chưa chọn dòng nào';
            if (toggle) {
                toggle.checked = checks.length > 0 && selected === checks.length;
                toggle.indeterminate = selected > 0 && selected < checks.length;
            }
        }

        checks.forEach(function(item) {
            item.addEventListener('change', updateBulkState);
        });

        if (toggle) {
            toggle.addEventListener('change', function() {
                checks.forEach(function(item) { item.checked = toggle.checked; });
                updateBulkState();
            });
        }

        if (selectAll) {
            selectAll.addEventListener('click', function() {
                checks.forEach(function(item) { item.checked = true; });
                updateBulkState();
            });
        }

        if (clearAll) {
            clearAll.addEventListener('click', function() {
                checks.forEach(function(item) { item.checked = false; });
                updateBulkState();
            });
        }

        if (bulkForm) {
            bulkForm.addEventListener('submit', function(e) {
                const selected = checks.filter(function(item) { return item.checked; }).length;
                if (selected === 0) {
                    e.preventDefault();
                    updateBulkState();
                    return;
                }
                if (!confirm('Duyệt ' + selected + ' yêu cầu đăng ký HK1 đã chọn?')) {
                    e.preventDefault();
                }
            });
        }

        updateBulkState();
    }

    async function fetchAutoRequests(url, pushState) {
        const card = document.getElementById('autoRequestsCard');
        if (!card) return;

        card.classList.add('opacity-75');
        try {
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const html = await res.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const freshCard = doc.getElementById('autoRequestsCard');
            if (!freshCard) throw new Error('Không tìm thấy dữ liệu lọc.');

            card.innerHTML = freshCard.innerHTML;
            bindAutoRequestControls(card);
            bindAutoRequestFilters(card);
            if (pushState) {
                history.pushState({ autoRequestsUrl: url }, '', url);
            }
        } catch (err) {
            console.error(err);
            alert('Không thể lọc dữ liệu lúc này. Vui lòng thử lại.');
        } finally {
            card.classList.remove('opacity-75');
        }
    }

    function bindAutoRequestFilters(root) {
        root = root || document;
        const filterForm = root.querySelector('#autoRequestFilterForm');
        const clearFilters = root.querySelector('#clearAutoRequestFilters');

        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams(new FormData(filterForm));
                fetchAutoRequests('pending_enrollments.php?' + params.toString(), true);
            });

            filterForm.querySelectorAll('select').forEach(function(select) {
                select.addEventListener('change', function() {
                    filterForm.requestSubmit();
                });
            });
        }

        if (clearFilters) {
            clearFilters.addEventListener('click', function(e) {
                e.preventDefault();
                fetchAutoRequests(clearFilters.getAttribute('href'), true);
            });
        }
    }

    bindAutoRequestControls(document);
    bindAutoRequestFilters(document);

    window.addEventListener('popstate', function() {
        fetchAutoRequests(location.pathname.split('/').pop() + location.search, false);
    });
});
</script>
<?php include 'includes/footer.php'; ?>
