<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Câu hỏi đánh giá';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $text   = trim($_POST['question_text'] ?? '');
        $type   = in_array($_POST['question_type'] ?? '', ['rating','text']) ? $_POST['question_type'] : 'rating';
        $status = in_array($_POST['status'] ?? '', ['show','hide']) ? $_POST['status'] : 'show';
        if ($text) {
            $stmt = $conn->prepare("INSERT INTO evaluation_questions (question_text, question_type, status) VALUES (?,?,?)");
            $stmt->bind_param('sss', $text, $type, $status);
            $stmt->execute() ? $success = 'Thêm câu hỏi thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else {
            $error = 'Vui lòng nhập nội dung câu hỏi.';
        }
    }

    if ($action === 'edit') {
        $id     = intval($_POST['id'] ?? 0);
        $text   = trim($_POST['question_text'] ?? '');
        $type   = in_array($_POST['question_type'] ?? '', ['rating','text']) ? $_POST['question_type'] : 'rating';
        $status = in_array($_POST['status'] ?? '', ['show','hide']) ? $_POST['status'] : 'show';
        if ($id && $text) {
            $stmt = $conn->prepare("UPDATE evaluation_questions SET question_text=?, question_type=?, status=? WHERE id=?");
            $stmt->bind_param('sssi', $text, $type, $status, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM evaluation_questions WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("UPDATE evaluation_questions SET status = IF(status='show','hide','show') WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Đã cập nhật trạng thái!' : $error = 'Lỗi: ' . $conn->error;
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

$questions = $conn->query("SELECT * FROM evaluation_questions ORDER BY id ASC");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Câu hỏi đánh giá</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-question-circle-fill me-2"></i>Danh sách Câu hỏi đánh giá</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm câu hỏi
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>Nội dung câu hỏi</th>
                                <th style="width:140px">Loại</th>
                                <th style="width:120px">Trạng thái</th>
                                <th style="width:130px">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($questions && $questions->num_rows > 0): $idx = 1; while ($q = $questions->fetch_assoc()): ?>
                            <tr class="<?php echo $q['status']==='hide'?'table-secondary text-muted':''; ?>">
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($q['question_text']); ?></td>
                                <td>
                                    <?php if ($q['question_type'] === 'rating'): ?>
                                    <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i>Đánh giá sao</span>
                                    <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-chat-left-text-fill me-1"></i>Nhận xét</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($q['status'] === 'show'): ?>
                                    <span class="badge bg-success"><i class="bi bi-eye-fill me-1"></i>Hiển thị</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-eye-slash-fill me-1"></i>Ẩn</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <!-- Toggle show/hide -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $q['status']==='show'?'warning':'outline-success'; ?>"
                                                title="<?php echo $q['status']==='show'?'Ẩn câu hỏi':'Hiện câu hỏi'; ?>">
                                                <i class="bi bi-eye<?php echo $q['status']==='show'?'-slash':''; ?>-fill"></i>
                                            </button>
                                        </form>
                                        <!-- Edit -->
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $q['id']; ?>"
                                            data-text="<?php echo htmlspecialchars($q['question_text']); ?>"
                                            data-type="<?php echo $q['question_type']; ?>"
                                            data-status="<?php echo $q['status']; ?>">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <!-- Delete -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa câu hỏi này?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Chưa có câu hỏi nào</td></tr>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Thêm Câu hỏi đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                        <textarea name="question_text" class="form-control" rows="3" required
                            placeholder="VD: Giảng viên giảng bài rõ ràng, dễ hiểu"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Loại câu hỏi</label>
                        <select name="question_type" class="form-select">
                            <option value="rating">⭐ Đánh giá sao (1-5)</option>
                            <option value="text">💬 Nhận xét văn bản</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="show" selected>Hiển thị</option>
                            <option value="hide">Ẩn</option>
                        </select>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Câu hỏi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                        <textarea name="question_text" id="editText" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Loại câu hỏi</label>
                        <select name="question_type" id="editType" class="form-select">
                            <option value="rating">⭐ Đánh giá sao (1-5)</option>
                            <option value="text">💬 Nhận xét văn bản</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="show">Hiển thị</option>
                            <option value="hide">Ẩn</option>
                        </select>
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
    document.getElementById('editId').value     = btn.dataset.id;
    document.getElementById('editText').value   = btn.dataset.text;
    document.getElementById('editType').value   = btn.dataset.type;
    document.getElementById('editStatus').value = btn.dataset.status;
});
</script>
</body>
</html>
