<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Phân công Giảng viên';

$success = $error = '';

// Xử lý phân công / hủy phân công
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $section_id = intval($_POST['section_id'] ?? 0);
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        if ($section_id && $teacher_id) {
            $stmt = $conn->prepare("UPDATE course_sections SET teacher_id=? WHERE id=?");
            $stmt->bind_param('ii', $teacher_id, $section_id);
            $stmt->execute() ? $success = 'Phân công giảng viên thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else {
            $error = 'Vui lòng chọn đầy đủ lớp học phần và giảng viên.';
        }
    }

    if ($action === 'unassign') {
        $section_id = intval($_POST['section_id'] ?? 0);
        if ($section_id) {
            $stmt = $conn->prepare("UPDATE course_sections SET teacher_id=NULL WHERE id=?");
            $stmt->bind_param('i', $section_id);
            $stmt->execute() ? $success = 'Đã hủy phân công giảng viên.' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'batch_assign') {
        // Phân công hàng loạt: gán 1 giảng viên cho nhiều lớp
        $teacher_id  = intval($_POST['teacher_id'] ?? 0);
        $section_ids = $_POST['section_ids'] ?? [];
        if ($teacher_id && !empty($section_ids)) {
            $ids = array_map('intval', $section_ids);
            $placeholders = implode(',', $ids);
            $stmt = $conn->prepare("UPDATE course_sections SET teacher_id=? WHERE id IN ($placeholders)");
            $stmt->bind_param('i', $teacher_id);
            $stmt->execute() ? $success = 'Phân công hàng loạt thành công cho ' . count($ids) . ' lớp học phần!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else {
            $error = 'Vui lòng chọn giảng viên và ít nhất một lớp học phần.';
        }
    }
}

// Bộ lọc
$filter_sem     = intval($_GET['semester_id'] ?? 0);
$filter_teacher = intval($_GET['teacher_id'] ?? 0);
$filter_status  = $_GET['assign_status'] ?? ''; // 'assigned' | 'unassigned' | ''

// Build WHERE
$where = [];
$params = [];
$types  = '';
if ($filter_sem) {
    $where[] = 'cs.semester_id = ?';
    $params[] = $filter_sem;
    $types   .= 'i';
}
if ($filter_teacher) {
    $where[] = 'cs.teacher_id = ?';
    $params[] = $filter_teacher;
    $types   .= 'i';
}
if ($filter_status === 'assigned') {
    $where[] = 'cs.teacher_id IS NOT NULL';
} elseif ($filter_status === 'unassigned') {
    $where[] = 'cs.teacher_id IS NULL';
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Phân trang
$perPage = 15;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countSQL = "SELECT COUNT(*) as c FROM course_sections cs $whereSQL";
if ($params) {
    $cStmt = $conn->prepare($countSQL);
    $cStmt->bind_param($types, ...$params);
    $cStmt->execute();
    $total = $cStmt->get_result()->fetch_assoc()['c'];
    $cStmt->close();
} else {
    $total = $conn->query($countSQL)->fetch_assoc()['c'];
}
$totalPages = ceil($total / $perPage);

$mainSQL = "
    SELECT cs.id, cs.section_code, cs.schedule_text, cs.room, cs.max_students, cs.current_students, cs.status,
           cs.teacher_id,
           sub.subject_name, sub.subject_code, sub.credits,
           sm.semester_name, sm.school_year,
           u.full_name as teacher_name,
           t.teacher_code, t.degree
    FROM course_sections cs
    LEFT JOIN subjects sub ON cs.subject_id = sub.id
    LEFT JOIN semesters sm ON cs.semester_id = sm.id
    LEFT JOIN teachers t ON cs.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    $whereSQL
    ORDER BY sm.school_year DESC, sm.semester_name, sub.subject_name
    LIMIT ? OFFSET ?
";
$limitParams  = array_merge($params, [$perPage, $offset]);
$limitTypes   = $types . 'ii';
$mStmt = $conn->prepare($mainSQL);
$mStmt->bind_param($limitTypes, ...$limitParams);
$mStmt->execute();
$sections = $mStmt->get_result();
$mStmt->close();

// Dữ liệu cho dropdown
$semesters = $conn->query("SELECT * FROM semesters ORDER BY school_year DESC, id DESC");
$teachers  = $conn->query("SELECT t.id, t.teacher_code, t.degree, u.full_name FROM teachers t LEFT JOIN users u ON t.user_id=u.id ORDER BY u.full_name");

// Thống kê nhanh
$stats = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(teacher_id IS NOT NULL) as assigned,
        SUM(teacher_id IS NULL) as unassigned
    FROM course_sections
")->fetch_assoc();

// Build query string cho pagination (giữ filter)
$qParams = [];
if ($filter_sem)     $qParams['semester_id']   = $filter_sem;
if ($filter_teacher) $qParams['teacher_id']     = $filter_teacher;
if ($filter_status)  $qParams['assign_status']  = $filter_status;
$qString = $qParams ? '&' . http_build_query($qParams) : '';

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Phân công Giảng viên</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">

        <?php if ($success): ?>
        <div class="alert alert-success auto-dismiss alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger auto-dismiss alert-dismissible fade show">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Thống kê -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-2 fw-bold text-navy"><?php echo $stats['total']; ?></div>
                    <div class="text-muted small">Tổng lớp học phần</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-2 fw-bold text-success"><?php echo $stats['assigned']; ?></div>
                    <div class="text-muted small">Đã phân công</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-2 fw-bold text-danger"><?php echo $stats['unassigned']; ?></div>
                    <div class="text-muted small">Chưa phân công</div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Học kỳ</label>
                        <select name="semester_id" class="form-select form-select-sm">
                            <option value="">-- Tất cả học kỳ --</option>
                            <?php $semesters->data_seek(0); while ($sem = $semesters->fetch_assoc()): ?>
                            <option value="<?php echo $sem['id']; ?>" <?php echo $filter_sem==$sem['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' ' . $sem['school_year']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Giảng viên</label>
                        <select name="teacher_id" class="form-select form-select-sm">
                            <option value="">-- Tất cả GV --</option>
                            <?php $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $filter_teacher==$t['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($t['full_name']); ?> (<?php echo htmlspecialchars($t['teacher_code']); ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Trạng thái phân công</label>
                        <select name="assign_status" class="form-select form-select-sm">
                            <option value="">-- Tất cả --</option>
                            <option value="assigned"   <?php echo $filter_status==='assigned'?'selected':''; ?>>Đã phân công</option>
                            <option value="unassigned" <?php echo $filter_status==='unassigned'?'selected':''; ?>>Chưa phân công</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-navy btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Lọc</button>
                        <a href="teacher_assignments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Phân công hàng loạt -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <i class="bi bi-people-fill text-navy"></i>
                <span class="fw-semibold">Phân công hàng loạt</span>
                <span class="text-muted small ms-1">— Chọn nhiều lớp rồi gán một giảng viên</span>
            </div>
            <div class="card-body py-2">
                <form method="POST" id="batchForm">
                    <input type="hidden" name="action" value="batch_assign">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <select name="teacher_id" class="form-select form-select-sm" required>
                                <option value="">-- Chọn giảng viên để phân công --</option>
                                <?php $teachers->data_seek(0); while ($t = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['full_name']); ?>
                                    (<?php echo htmlspecialchars($t['teacher_code']); ?>)
                                    <?php echo $t['degree'] ? '- ' . htmlspecialchars($t['degree']) : ''; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small" id="selectedCount">Chưa chọn lớp nào</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <button type="submit" class="btn btn-gold btn-sm" id="batchBtn" disabled>
                                <i class="bi bi-person-check-fill me-1"></i>Phân công hàng loạt
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bảng danh sách -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-lines-fill me-2"></i>Danh sách Lớp học phần (<?php echo $total; ?>)</span>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" id="selectAllBtn" type="button">
                        <i class="bi bi-check-all me-1"></i>Chọn tất cả
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="40"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                <th>#</th>
                                <th>Mã lớp HP</th>
                                <th>Môn học</th>
                                <th>Học kỳ</th>
                                <th>Lịch / Phòng</th>
                                <th>Sĩ số</th>
                                <th>Giảng viên phụ trách</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($sections && $sections->num_rows > 0):
                            $idx = $offset + 1;
                            while ($s = $sections->fetch_assoc()):
                                $hasTeacher = !empty($s['teacher_id']);
                        ?>
                            <tr class="<?php echo !$hasTeacher ? 'table-warning' : ''; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input row-check" name="section_ids[]"
                                           value="<?php echo $s['id']; ?>" form="batchForm">
                                </td>
                                <td class="text-muted small"><?php echo $idx++; ?></td>
                                <td>
                                    <span class="fw-bold text-navy small"><?php echo htmlspecialchars($s['section_code']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                                    <div class="text-muted" style="font-size:11px">
                                        <?php echo htmlspecialchars($s['subject_code']); ?> &bull; <?php echo $s['credits']; ?> TC
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <?php echo htmlspecialchars($s['semester_name'] . ' ' . $s['school_year']); ?>
                                </td>
                                <td class="small">
                                    <div><?php echo htmlspecialchars($s['schedule_text'] ?: '--'); ?></div>
                                    <?php if ($s['room']): ?>
                                    <div class="text-muted" style="font-size:11px"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($s['room']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $s['current_students'] >= $s['max_students'] ? 'danger' : 'success'; ?>">
                                        <?php echo $s['current_students']; ?>/<?php echo $s['max_students']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($hasTeacher): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-navy text-white rounded-circle d-flex align-items-center justify-content-center" style="width:30px;height:30px;font-size:12px">
                                                <?php echo mb_substr($s['teacher_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="small fw-semibold"><?php echo htmlspecialchars($s['teacher_name']); ?></div>
                                                <div class="text-muted" style="font-size:11px">
                                                    <?php echo htmlspecialchars($s['teacher_code']); ?>
                                                    <?php echo $s['degree'] ? ' &bull; ' . htmlspecialchars($s['degree']) : ''; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Chưa phân công</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#assignModal"
                                            data-id="<?php echo $s['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($s['section_code']); ?>"
                                            data-subject="<?php echo htmlspecialchars($s['subject_name']); ?>"
                                            data-teacher="<?php echo $s['teacher_id'] ?? ''; ?>"
                                            title="Phân công giảng viên">
                                        <i class="bi bi-person-check-fill"></i>
                                    </button>
                                    <?php if ($hasTeacher): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hủy phân công giảng viên cho lớp này?')">
                                        <input type="hidden" name="action" value="unassign">
                                        <input type="hidden" name="section_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hủy phân công">
                                            <i class="bi bi-person-x-fill"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Không có dữ liệu</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                <nav class="p-3">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $qString; ?>"><i class="bi bi-chevron-left"></i></a></li>
                        <?php endif; ?>
                        <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
                        <li class="page-item <?php echo $p==$page?'active':''; ?>">
                            <a class="page-link" href="?page=<?php echo $p; ?><?php echo $qString; ?>"><?php echo $p; ?></a>
                        </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $qString; ?>"><i class="bi bi-chevron-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.admin-content -->
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>

<!-- Modal Phân công đơn lẻ -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-check-fill me-2"></i>Phân công Giảng viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="section_id" id="modalSectionId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Lớp học phần</label>
                        <div class="fw-bold" id="modalSectionInfo"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giảng viên phụ trách <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="modalTeacherId" class="form-select" required>
                            <option value="">-- Chọn giảng viên --</option>
                            <?php
                            $teachers->data_seek(0);
                            while ($t = $teachers->fetch_assoc()):
                            ?>
                            <option value="<?php echo $t['id']; ?>">
                                <?php echo htmlspecialchars($t['full_name']); ?>
                                (<?php echo htmlspecialchars($t['teacher_code']); ?>)
                                <?php echo $t['degree'] ? ' - ' . htmlspecialchars($t['degree']) : ''; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Giảng viên được phân công sẽ thấy lớp này trong trang <strong>Lớp học phần</strong> của họ và có thể nhập điểm cho sinh viên đã đăng ký.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu phân công</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
// Modal phân công đơn lẻ
document.getElementById('assignModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modalSectionId').value = btn.dataset.id;
    document.getElementById('modalSectionInfo').textContent = btn.dataset.code + ' — ' + btn.dataset.subject;
    const sel = document.getElementById('modalTeacherId');
    sel.value = btn.dataset.teacher || '';
});

// Checkbox chọn tất cả
const checkAll = document.getElementById('checkAll');
const rowChecks = document.querySelectorAll('.row-check');
const batchBtn  = document.getElementById('batchBtn');
const countSpan = document.getElementById('selectedCount');

function updateBatchState() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    batchBtn.disabled = checked === 0;
    countSpan.textContent = checked > 0 ? `Đã chọn ${checked} lớp học phần` : 'Chưa chọn lớp nào';
    checkAll.indeterminate = checked > 0 && checked < rowChecks.length;
    checkAll.checked = checked === rowChecks.length && rowChecks.length > 0;
}

checkAll.addEventListener('change', function() {
    rowChecks.forEach(cb => cb.checked = this.checked);
    updateBatchState();
});

rowChecks.forEach(cb => cb.addEventListener('change', updateBatchState));

document.getElementById('selectAllBtn').addEventListener('click', function() {
    const allChecked = [...rowChecks].every(cb => cb.checked);
    rowChecks.forEach(cb => cb.checked = !allChecked);
    updateBatchState();
});

// Xác nhận phân công hàng loạt
document.getElementById('batchForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.row-check:checked').length;
    const teacher = this.querySelector('select[name="teacher_id"]');
    if (!teacher.value) {
        e.preventDefault();
        alert('Vui lòng chọn giảng viên trước khi phân công hàng loạt.');
        return;
    }
    if (!confirm(`Xác nhận phân công giảng viên cho ${checked} lớp học phần đã chọn?`)) {
        e.preventDefault();
    }
});
</script>
</body>
</html>
