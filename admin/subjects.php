<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Môn học';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $subject_code = trim($_POST['subject_code'] ?? '');
        $major_id = intval($_POST['major_id'] ?? 0);
        $credits = intval($_POST['credits'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if ($name && $subject_code) {
            $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, major_id, credits, description) VALUES (?,?,?,?,?)");
            $stmt->bind_param('ssids', $name, $subject_code, $major_id, $credits, $description);
            $stmt->execute() ? $success = 'Thêm môn học thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng điền đầy đủ thông tin.'; }
    }
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $subject_code = trim($_POST['subject_code'] ?? '');
        $major_id = intval($_POST['major_id'] ?? 0);
        $credits = intval($_POST['credits'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if ($id && $name) {
            $stmt = $conn->prepare("UPDATE subjects SET subject_name=?, subject_code=?, major_id=?, credits=?, description=? WHERE id=?");
            $stmt->bind_param('ssidsi', $name, $subject_code, $major_id, $credits, $description, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM subjects WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }
}

$subjects = $conn->query("SELECT s.*, m.major_name FROM subjects s LEFT JOIN majors m ON s.major_id=m.id ORDER BY s.subject_name");
$majors = $conn->query("SELECT * FROM majors ORDER BY major_name");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Môn học</span>
        </div>
    </div>
    <div class="admin-content">
        <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-2"></i>Danh sách Môn học</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Thêm mới</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Mã môn</th><th>Tên môn học</th><th>Ngành</th><th>Số tín chỉ</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            <?php if ($subjects && $subjects->num_rows > 0): $idx=1; while ($s = $subjects->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold text-navy"><?php echo htmlspecialchars($s['subject_code']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                                    <?php if ($s['description']): ?><div class="text-muted small text-truncate-2"><?php echo htmlspecialchars($s['description']); ?></div><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($s['major_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-navy"><?php echo $s['credits']; ?> TC</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $s['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($s['subject_name']); ?>"
                                        data-code="<?php echo htmlspecialchars($s['subject_code']); ?>"
                                        data-major="<?php echo $s['major_id']; ?>"
                                        data-credits="<?php echo $s['credits']; ?>"
                                        data-description="<?php echo htmlspecialchars($s['description']); ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa môn học này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-journal-plus me-2"></i>Thêm Môn học</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Mã môn học <span class="text-danger">*</span></label><input type="text" name="subject_code" class="form-control" required placeholder="VD: CNTT101"></div>
                        <div class="col-md-6"><label class="form-label">Số tín chỉ</label><input type="number" name="credits" class="form-control" min="1" max="10" value="3"></div>
                        <div class="col-12"><label class="form-label">Tên môn học <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
                        <div class="col-12">
                            <label class="form-label">Ngành</label>
                            <select name="major_id" class="form-select">
                                <option value="">-- Chọn ngành --</option>
                                <?php if ($majors): while ($m = $majors->fetch_assoc()): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Mô tả</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Môn học</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Mã môn học</label><input type="text" name="subject_code" id="editCode" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Số tín chỉ</label><input type="number" name="credits" id="editCredits" class="form-control" min="1" max="10"></div>
                        <div class="col-12"><label class="form-label">Tên môn học</label><input type="text" name="name" id="editName" class="form-control"></div>
                        <div class="col-12">
                            <label class="form-label">Ngành</label>
                            <select name="major_id" id="editMajor" class="form-select">
                                <option value="">-- Chọn ngành --</option>
                                <?php
                                $majors2 = $conn->query("SELECT * FROM majors ORDER BY major_name");
                                if ($majors2): while ($m = $majors2->fetch_assoc()): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Mô tả</label><textarea name="description" id="editDescription" class="form-control" rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cập nhật</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value = btn.dataset.id;
    document.getElementById('editName').value = btn.dataset.name;
    document.getElementById('editCode').value = btn.dataset.code;
    document.getElementById('editMajor').value = btn.dataset.major;
    document.getElementById('editCredits').value = btn.dataset.credits;
    document.getElementById('editDescription').value = btn.dataset.description;
});
</script>
</body></html>
