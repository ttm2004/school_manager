<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Phương thức Xét tuyển';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $method_name    = trim($_POST['method_name'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $condition_text = trim($_POST['condition_text'] ?? '');
        $status         = trim($_POST['status'] ?? 'open');
        if ($method_name) {
            $stmt = $conn->prepare("INSERT INTO admission_methods (method_name, description, condition_text, status) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $method_name, $description, $condition_text, $status);
            $stmt->execute() ? $success = 'Thêm phương thức thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng nhập tên phương thức.'; }
    }

    if ($action === 'edit') {
        $id             = intval($_POST['id'] ?? 0);
        $method_name    = trim($_POST['method_name'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $condition_text = trim($_POST['condition_text'] ?? '');
        $status         = trim($_POST['status'] ?? 'open');
        if ($id && $method_name) {
            $stmt = $conn->prepare("UPDATE admission_methods SET method_name=?, description=?, condition_text=?, status=? WHERE id=?");
            $stmt->bind_param('ssssi', $method_name, $description, $condition_text, $status, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM admission_methods WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    // ── PRG: redirect sau POST để tránh F5 gửi lại form ──
    if (!empty($success) || !empty($error)) {
        $_SESSION['_flash'] = [
            'type'    => !empty($success) ? 'success' : 'danger',
            'message' => !empty($success) ? $success : $error,
        ];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($qs ? '?' . $qs : ''));
        exit();
    }
}

$methods = $conn->query("SELECT am.*, COUNT(aa.id) as app_count FROM admission_methods am LEFT JOIN admission_applications aa ON am.id=aa.method_id GROUP BY am.id ORDER BY am.id");
include __DIR__ . '/includes/header.php';
?>
<?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-2"></i>Danh sách Phương thức Xét tuyển</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm mới
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>#</th><th>Tên phương thức</th><th>Điều kiện</th><th>Hồ sơ</th><th>Trạng thái</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($methods && $methods->num_rows > 0): $idx=1; while ($m = $methods->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold text-navy"><?php echo htmlspecialchars($m['method_name']); ?></div>
                                    <?php if ($m['description']): ?><div class="text-muted small"><?php echo mb_substr($m['description'],0,80); ?>...</div><?php endif; ?>
                                </td>
                                <td class="text-muted small"><?php echo mb_substr($m['condition_text'] ?? '',0,100); ?></td>
                                <td><span class="badge bg-navy"><?php echo $m['app_count']; ?> hồ sơ</span></td>
                                <td><span class="badge bg-<?php echo $m['status']=='open'?'success':'secondary'; ?>"><?php echo $m['status']=='open'?'Đang mở':'Đóng'; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $m['id']; ?>"
                                        data-method_name="<?php echo htmlspecialchars($m['method_name']); ?>"
                                        data-description="<?php echo htmlspecialchars($m['description'] ?? ''); ?>"
                                        data-condition_text="<?php echo htmlspecialchars($m['condition_text'] ?? ''); ?>"
                                        data-status="<?php echo $m['status']; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa phương thức này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
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
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-list-check me-2"></i>Thêm Phương thức Xét tuyển</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Tên phương thức <span class="text-danger">*</span></label><input type="text" name="method_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Mô tả</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label">Điều kiện xét tuyển</label><textarea name="condition_text" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="open">Đang mở</option>
                            <option value="closed">Đóng</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Phương thức</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Tên phương thức <span class="text-danger">*</span></label><input type="text" name="method_name" id="editMethodName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Mô tả</label><textarea name="description" id="editDescription" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label">Điều kiện xét tuyển</label><textarea name="condition_text" id="editCondition" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="open">Đang mở</option>
                            <option value="closed">Đóng</option>
                        </select>
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
    document.getElementById('editMethodName').value = btn.dataset.method_name;
    document.getElementById('editDescription').value = btn.dataset.description;
    document.getElementById('editCondition').value = btn.dataset.condition_text;
    document.getElementById('editStatus').value = btn.dataset.status;
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
