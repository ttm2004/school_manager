<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Tin Tuyển sinh';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $image      = trim($_POST['image'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $status     = trim($_POST['status'] ?? 'show');
        if ($title && $content) {
            $stmt = $conn->prepare("INSERT INTO admission_news (title, image, content, start_date, end_date, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssssss', $title, $image, $content, $start_date, $end_date, $status);
            $stmt->execute() ? $success = 'Thêm tin tuyển sinh thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng nhập tiêu đề và nội dung.'; }
    }

    if ($action === 'edit') {
        $id         = intval($_POST['id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $image      = trim($_POST['image'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $status     = trim($_POST['status'] ?? 'show');
        if ($id && $title && $content) {
            $stmt = $conn->prepare("UPDATE admission_news SET title=?, image=?, content=?, start_date=?, end_date=?, status=? WHERE id=?");
            $stmt->bind_param('ssssssi', $title, $image, $content, $start_date, $end_date, $status, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM admission_news WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("UPDATE admission_news SET status = IF(status='show','hide','show') WHERE id=" . intval($id));
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
$total = $conn->query("SELECT COUNT(*) as c FROM admission_news")->fetch_assoc()['c'];
$stmt = $conn->prepare("SELECT * FROM admission_news ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$news = $stmt->get_result();
$stmt->close();
$totalPages = ceil($total / $perPage);
include __DIR__ . '/includes/header.php';
?>
<?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-newspaper me-2"></i>Danh sách Tin Tuyển sinh
                    <span class="badge bg-gold text-navy ms-1"><?php echo $total; ?></span>
                </span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm mới
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>#</th><th>Tiêu đề</th><th>Thời gian</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($news && $news->num_rows > 0): $idx=$offset+1; while ($n = $news->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td>
                                    <?php if ($n['image']): ?>
                                    <img src="<?php echo htmlspecialchars($n['image']); ?>" alt="" class="rounded me-2" style="width:50px;height:35px;object-fit:cover">
                                    <?php endif; ?>
                                    <span class="fw-bold"><?php echo htmlspecialchars($n['title']); ?></span>
                                </td>
                                <td class="text-muted small">
                                    <?php if ($n['start_date']): ?>
                                    <?php echo date('d/m/Y', strtotime($n['start_date'])); ?> — <?php echo date('d/m/Y', strtotime($n['end_date'])); ?>
                                    <?php else: ?>--<?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                        <button type="submit" class="badge border-0 bg-<?php echo $n['status']=='show'?'success':'secondary'; ?> text-white" style="cursor:pointer;">
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
                                        data-start_date="<?php echo $n['start_date'] ?? ''; ?>"
                                        data-end_date="<?php echo $n['end_date'] ?? ''; ?>"
                                        data-status="<?php echo $n['status']; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa tin này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có tin tuyển sinh</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav class="p-3"><ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Ban Tuyển sinh</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-newspaper me-2"></i>Thêm Tin Tuyển sinh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Tiêu đề <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nội dung <span class="text-danger">*</span></label><textarea name="content" class="form-control" rows="5" required></textarea></div>
                    <div class="mb-3"><label class="form-label">URL Hình ảnh</label><input type="text" name="image" class="form-control" placeholder="https://..."></div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Ngày bắt đầu</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Ngày kết thúc</label><input type="date" name="end_date" class="form-control"></div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select"><option value="show">Hiển thị</option><option value="hide">Ẩn</option></select>
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
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Tin Tuyển sinh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Tiêu đề <span class="text-danger">*</span></label><input type="text" name="title" id="editTitle" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Nội dung <span class="text-danger">*</span></label><textarea name="content" id="editContent" class="form-control" rows="5" required></textarea></div>
                    <div class="mb-3"><label class="form-label">URL Hình ảnh</label><input type="text" name="image" id="editImage" class="form-control"></div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Ngày bắt đầu</label><input type="date" name="start_date" id="editStartDate" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Ngày kết thúc</label><input type="date" name="end_date" id="editEndDate" class="form-control"></div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-select"><option value="show">Hiển thị</option><option value="hide">Ẩn</option></select>
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
    document.getElementById('editStartDate').value = btn.dataset.start_date;
    document.getElementById('editEndDate').value = btn.dataset.end_date;
    document.getElementById('editStatus').value = btn.dataset.status;
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
