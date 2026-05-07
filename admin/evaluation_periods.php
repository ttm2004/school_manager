<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Đợt đánh giá';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title      = trim($_POST['title'] ?? '');
        $sem_id     = intval($_POST['semester_id'] ?? 0);
        $desc       = trim($_POST['description'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $status     = in_array($_POST['status'] ?? '', ['open','closed']) ? $_POST['status'] : 'closed';
        if ($title && $sem_id) {
            $stmt = $conn->prepare("INSERT INTO evaluation_periods (title, semester_id, description, start_date, end_date, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('sissss', $title, $sem_id, $desc, $start_date, $end_date, $status);
            $stmt->execute() ? $success = 'Thêm đợt đánh giá thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else {
            $error = 'Vui lòng nhập tiêu đề và chọn học kỳ.';
        }
    }

    if ($action === 'edit') {
        $id         = intval($_POST['id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $sem_id     = intval($_POST['semester_id'] ?? 0);
        $desc       = trim($_POST['description'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $status     = in_array($_POST['status'] ?? '', ['open','closed']) ? $_POST['status'] : 'closed';
        if ($id && $title && $sem_id) {
            $stmt = $conn->prepare("UPDATE evaluation_periods SET title=?, semester_id=?, description=?, start_date=?, end_date=?, status=? WHERE id=?");
            $stmt->bind_param('sissssi', $title, $sem_id, $desc, $start_date, $end_date, $status, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM evaluation_periods WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("UPDATE evaluation_periods SET status = IF(status='open','closed','open') WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Đã cập nhật trạng thái!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }
}

// Lấy danh sách đợt đánh giá kèm học kỳ
$periods = $conn->query("
    SELECT ep.*, sm.semester_name, sm.school_year
    FROM evaluation_periods ep
    LEFT JOIN semesters sm ON ep.semester_id = sm.id
    ORDER BY ep.created_at DESC
");

// Lấy danh sách học kỳ cho dropdown
$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY school_year DESC, id DESC");
$semList = [];
if ($semesters) {
    while ($s = $semesters->fetch_assoc()) $semList[] = $s;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Đợt đánh giá</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard-check-fill me-2"></i>Danh sách Đợt đánh giá</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm đợt đánh giá
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tiêu đề</th>
                                <th>Học kỳ</th>
                                <th>Thời gian</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($periods && $periods->num_rows > 0): $idx = 1; while ($p = $periods->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold text-navy"><?php echo htmlspecialchars($p['title']); ?></div>
                                    <?php if ($p['description']): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($p['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($p['semester_name']): ?>
                                    <span class="badge bg-navy"><?php echo htmlspecialchars($p['semester_name'] . ' ' . $p['school_year']); ?></span>
                                    <?php else: ?><span class="text-muted">--</span><?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <div><i class="bi bi-play-fill text-success"></i> <?php echo $p['start_date'] ? date('d/m/Y H:i', strtotime($p['start_date'])) : '--'; ?></div>
                                    <div><i class="bi bi-stop-fill text-danger"></i> <?php echo $p['end_date'] ? date('d/m/Y H:i', strtotime($p['end_date'])) : '--'; ?></div>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'open'): ?>
                                    <span class="badge bg-success"><i class="bi bi-unlock-fill me-1"></i>Đang mở</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>Đã đóng</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <!-- Toggle status -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $p['status']==='open'?'warning':'outline-success'; ?>"
                                                title="<?php echo $p['status']==='open'?'Đóng đợt':'Mở đợt'; ?>">
                                                <i class="bi bi-<?php echo $p['status']==='open'?'lock':'unlock'; ?>-fill"></i>
                                            </button>
                                        </form>
                                        <!-- Edit -->
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($p['title']); ?>"
                                            data-semester="<?php echo $p['semester_id']; ?>"
                                            data-description="<?php echo htmlspecialchars($p['description'] ?? ''); ?>"
                                            data-start="<?php echo $p['start_date'] ? date('Y-m-d\TH:i', strtotime($p['start_date'])) : ''; ?>"
                                            data-end="<?php echo $p['end_date'] ? date('Y-m-d\TH:i', strtotime($p['end_date'])) : ''; ?>"
                                            data-status="<?php echo $p['status']; ?>">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <!-- Delete -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa đợt đánh giá này? Dữ liệu đánh giá liên quan cũng sẽ bị xóa.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Chưa có đợt đánh giá nào</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một</div>
</div>

<!-- Modal Thêm -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-plus me-2"></i>Thêm Đợt đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="VD: Đợt đánh giá giảng viên HK1 2025-2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học kỳ <span class="text-danger">*</span></label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">-- Chọn học kỳ --</option>
                                <?php foreach ($semList as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['semester_name'] . ' ' . $s['school_year']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="closed" selected>Đã đóng</option>
                                <option value="open">Đang mở</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input type="datetime-local" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày kết thúc</label>
                            <input type="datetime-local" name="end_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mô tả</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Mô tả ngắn về đợt đánh giá..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Sửa -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Đợt đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="editTitle" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học kỳ <span class="text-danger">*</span></label>
                            <select name="semester_id" id="editSemester" class="form-select" required>
                                <option value="">-- Chọn học kỳ --</option>
                                <?php foreach ($semList as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['semester_name'] . ' ' . $s['school_year']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="closed">Đã đóng</option>
                                <option value="open">Đang mở</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input type="datetime-local" name="start_date" id="editStart" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày kết thúc</label>
                            <input type="datetime-local" name="end_date" id="editEnd" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mô tả</label>
                            <textarea name="description" id="editDesc" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value       = btn.dataset.id;
    document.getElementById('editTitle').value    = btn.dataset.title;
    document.getElementById('editSemester').value = btn.dataset.semester;
    document.getElementById('editDesc').value     = btn.dataset.description;
    document.getElementById('editStart').value    = btn.dataset.start;
    document.getElementById('editEnd').value      = btn.dataset.end;
    document.getElementById('editStatus').value   = btn.dataset.status;
});
</script>
</body>
</html>
