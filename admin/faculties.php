<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quan ly Khoa';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $faculty_code = trim($_POST['faculty_code'] ?? '');
        $faculty_name = trim($_POST['faculty_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        if ($faculty_code && $faculty_name) {
            $stmt = $conn->prepare("INSERT INTO faculties (faculty_code, faculty_name, description) VALUES (?,?,?)");
            $stmt->bind_param('sss', $faculty_code, $faculty_name, $description);
            $stmt->execute() ? $success = 'Them khoa thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui long nhap ma khoa va ten khoa.'; }
    }
    if ($action === 'edit') {
        $id           = intval($_POST['id'] ?? 0);
        $faculty_code = trim($_POST['faculty_code'] ?? '');
        $faculty_name = trim($_POST['faculty_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        if ($id && $faculty_name) {
            $stmt = $conn->prepare("UPDATE faculties SET faculty_code=?, faculty_name=?, description=? WHERE id=?");
            $stmt->bind_param('sssi', $faculty_code, $faculty_name, $description, $id);
            $stmt->execute() ? $success = 'Cap nhat thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        }
    }
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM faculties WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xoa thanh cong!' : $error = 'Loi: ' . $conn->error;
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

$faculties = $conn->query("SELECT f.*, COUNT(m.id) as major_count FROM faculties f LEFT JOIN majors m ON f.id=m.faculty_id GROUP BY f.id ORDER BY f.faculty_name");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quan ly Khoa</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-building-fill me-2"></i>Danh sach Khoa</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Them moi
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>#</th><th>Ma khoa</th><th>Ten khoa</th><th>Mo ta</th><th>So nganh</th><th>Thao tac</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($faculties && $faculties->num_rows > 0): $idx=1; while ($f = $faculties->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td><span class="badge bg-navy"><?php echo htmlspecialchars($f['faculty_code']); ?></span></td>
                                <td>
                                    <div class="fw-bold text-navy"><?php echo htmlspecialchars($f['faculty_name']); ?></div>
                                    <?php if ($f['description']): ?><div class="text-muted small"><?php echo mb_substr($f['description'],0,80); ?></div><?php endif; ?>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($f['description'] ?? ''); ?></td>
                                <td><span class="badge bg-success"><?php echo $f['major_count']; ?> nganh</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $f['id']; ?>"
                                        data-faculty_code="<?php echo htmlspecialchars($f['faculty_code']); ?>"
                                        data-faculty_name="<?php echo htmlspecialchars($f['faculty_name']); ?>"
                                        data-description="<?php echo htmlspecialchars($f['description'] ?? ''); ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xoa khoa nay? Cac nganh thuoc khoa cung se bi xoa!')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Chua co du lieu</td></tr>
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
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-building me-2"></i>Them Khoa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Ma khoa <span class="text-danger">*</span></label><input type="text" name="faculty_code" class="form-control" required placeholder="VD: CNTT"></div>
                    <div class="mb-3"><label class="form-label">Ten khoa <span class="text-danger">*</span></label><input type="text" name="faculty_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Mo ta</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Luu</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chinh sua Khoa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Ma khoa <span class="text-danger">*</span></label><input type="text" name="faculty_code" id="editFacultyCode" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Ten khoa <span class="text-danger">*</span></label><input type="text" name="faculty_name" id="editFacultyName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Mo ta</label><textarea name="description" id="editDescription" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cap nhat</button></div>
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
    document.getElementById('editFacultyCode').value = btn.dataset.faculty_code;
    document.getElementById('editFacultyName').value = btn.dataset.faculty_name;
    document.getElementById('editDescription').value = btn.dataset.description;
});
</script>
</body></html>
