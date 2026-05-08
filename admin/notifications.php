<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Thông báo';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image   = trim($_POST['image'] ?? '');
        $type    = trim($_POST['type'] ?? 'general');
        $status  = trim($_POST['status'] ?? 'show');
        if ($title && $content) {
            $stmt = $conn->prepare("INSERT INTO notifications (title, content, image, type, status) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $title, $content, $image, $type, $status);
            $stmt->execute() ? $success = 'Thêm thông báo thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng nhập tiêu đề và nội dung.'; }
    }

    if ($action === 'edit') {
        $id      = intval($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image   = trim($_POST['image'] ?? '');
        $type    = trim($_POST['type'] ?? 'general');
        $status  = trim($_POST['status'] ?? 'show');
        if ($id && $title && $content) {
            $stmt = $conn->prepare("UPDATE notifications SET title=?, content=?, image=?, type=?, status=? WHERE id=?");
            $stmt->bind_param('sssssi', $title, $content, $image, $type, $status, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("UPDATE notifications SET status = IF(status='show','hide','show') WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $success = 'Đã cập nhật trạng thái!';
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

$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$filter_type = trim($_GET['type'] ?? '');

if ($filter_type) {
    $total = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE type=?");
    $total->bind_param('s', $filter_type);
    $total->execute();
    $total = $total->get_result()->fetch_assoc()['c'];
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE type=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('sii', $filter_type, $perPage, $offset);
} else {
    $total = $conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'];
    $stmt = $conn->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();
$totalPages = ceil($total / $perPage);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Thông báo</span>
        </div>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bell-fill me-2"></i>Danh sách Thông báo (<?php echo $total; ?>)</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm mới
                </button>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <select name="type" class="form-select" style="width:200px" onchange="this.form.submit()">
                            <option value="">Tất cả loại</option>
                            <option value="general" <?php echo $filter_type=='general'?'selected':''; ?>>Chung</option>
                            <option value="registration" <?php echo $filter_type=='registration'?'selected':''; ?>>Đăng ký học phần</option>
                            <option value="grade" <?php echo $filter_type=='grade'?'selected':''; ?>>Điểm số</option>
                            <option value="tuition" <?php echo $filter_type=='tuition'?'selected':''; ?>>Học phí</option>
                            <option value="admission" <?php echo $filter_type=='admission'?'selected':''; ?>>Tuyển sinh</option>
                        </select>
                        <?php if ($filter_type): ?><a href="notifications.php" class="btn btn-outline-secondary">Xóa lọc</a><?php endif; ?>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>#</th><th>Tiêu đề</th><th>Loại</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $typeMap = ['general'=>['Chung','secondary'],'registration'=>['Đăng ký HP','primary'],'grade'=>['Điểm số','success'],'tuition'=>['Học phí','warning'],'admission'=>['Tuyển sinh','info']];
                            if ($notifications && $notifications->num_rows > 0): $idx=$offset+1; while ($n = $notifications->fetch_assoc()):
                            $t = $typeMap[$n['type']] ?? [$n['type'],'secondary'];
                            ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($n['title']); ?></div>
                                    <div class="text-muted small"><?php echo mb_substr(strip_tags($n['content']),0,80); ?>...</div>
                                </td>
                                <td><span class="badge bg-<?php echo $t[1]; ?>"><?php echo $t[0]; ?></span></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                        <button type="submit" class="badge border-0 bg-<?php echo $n['status']=='show'?'success':'secondary'; ?> text-white">
                                            <?php echo $n['status']=='show'?'Hiển thị':'Ẩn'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-muted small"><?php echo date('d/m/Y', strtotime($n['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $n['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($n['title']); ?>"
                                        data-content="<?php echo htmlspecialchars($n['content']); ?>"
                                        data-image="<?php echo htmlspecialchars($n['image'] ?? ''); ?>"
                                        data-type="<?php echo $n['type']; ?>"
                                        data-status="<?php echo $n['status']; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa thông báo này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có thông báo</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav><ul class="pagination justify-content-center mt-3">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?type=<?php echo urlencode($filter_type); ?>&page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?type=<?php echo urlencode($filter_type); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?type=<?php echo urlencode($filter_type); ?>&page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-bell me-2"></i>Thêm Thông báo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Tiêu đề <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nội dung <span class="text-danger">*</span></label><textarea name="content" class="form-control" rows="5" required></textarea></div>
                    <div class="mb-3"><label class="form-label">URL Hình ảnh</label><input type="text" name="image" class="form-control" placeholder="https://..."></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Loại thông báo</label>
                            <select name="type" class="form-select">
                                <option value="general">Chung</option>
                                <option value="registration">Đăng ký học phần</option>
                                <option value="grade">Điểm số</option>
                                <option value="tuition">Học phí</option>
                                <option value="admission">Tuyển sinh</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="show">Hiển thị</option>
                                <option value="hide">Ẩn</option>
                            </select>
                        </div>
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
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Thông báo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Tiêu đề <span class="text-danger">*</span></label><input type="text" name="title" id="editTitle" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nội dung <span class="text-danger">*</span></label><textarea name="content" id="editContent" class="form-control" rows="5" required></textarea></div>
                    <div class="mb-3"><label class="form-label">URL Hình ảnh</label><input type="text" name="image" id="editImage" class="form-control"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Loại thông báo</label>
                            <select name="type" id="editType" class="form-select">
                                <option value="general">Chung</option>
                                <option value="registration">Đăng ký học phần</option>
                                <option value="grade">Điểm số</option>
                                <option value="tuition">Học phí</option>
                                <option value="admission">Tuyển sinh</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="show">Hiển thị</option>
                                <option value="hide">Ẩn</option>
                            </select>
                        </div>
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
    document.getElementById('editTitle').value = btn.dataset.title;
    document.getElementById('editContent').value = btn.dataset.content;
    document.getElementById('editImage').value = btn.dataset.image;
    document.getElementById('editType').value = btn.dataset.type;
    document.getElementById('editStatus').value = btn.dataset.status;
});
</script>
</body></html>
