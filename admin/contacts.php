<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Liên hệ';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if ($id && in_array($status, ['new','read','replied'])) {
            $stmt = $conn->prepare("UPDATE contacts SET status=? WHERE id=?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute() ? $success = 'Cập nhật trạng thái thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM contacts WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa liên hệ thành công!' : $error = 'Lỗi: ' . $conn->error;
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

$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$filter_status = trim($_GET['status'] ?? '');

if ($filter_status) {
    $total = $conn->prepare("SELECT COUNT(*) as c FROM contacts WHERE status=?");
    $total->bind_param('s', $filter_status);
    $total->execute();
    $total = $total->get_result()->fetch_assoc()['c'];
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE status=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('sii', $filter_status, $perPage, $offset);
} else {
    $total = $conn->query("SELECT COUNT(*) as c FROM contacts")->fetch_assoc()['c'];
    $stmt = $conn->prepare("SELECT * FROM contacts ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$contacts = $stmt->get_result();
$stmt->close();
$totalPages = ceil($total / $perPage);

// View detail
$viewContact = null;
if (isset($_GET['view'])) {
    $vid = intval($_GET['view']);
    $vs = $conn->prepare("SELECT * FROM contacts WHERE id=?");
    $vs->bind_param('i', $vid);
    $vs->execute();
    $viewContact = $vs->get_result()->fetch_assoc();
    $vs->close();
    // Auto mark as read
    if ($viewContact && $viewContact['status'] === 'new') {
        $conn->query("UPDATE contacts SET status='read' WHERE id=$vid");
        $viewContact['status'] = 'read';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Liên hệ</span>
        </div>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <?php if ($viewContact): ?>
        <!-- View Detail -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-envelope-open me-2"></i>Chi tiết liên hệ</span>
                <a href="contacts.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th width="140">Họ tên:</th><td class="fw-bold"><?php echo htmlspecialchars($viewContact['full_name']); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo htmlspecialchars($viewContact['email']); ?></td></tr>
                            <tr><th>Điện thoại:</th><td><?php echo htmlspecialchars($viewContact['phone']); ?></td></tr>
                            <tr><th>Chủ đề:</th><td><?php echo htmlspecialchars($viewContact['subject']); ?></td></tr>
                            <tr><th>Ngày gửi:</th><td><?php echo date('d/m/Y H:i', strtotime($viewContact['created_at'])); ?></td></tr>
                            <tr><th>Trạng thái:</th><td>
                                <?php $sm=['new'=>['Mới','warning'],'read'=>['Đã đọc','info'],'replied'=>['Đã trả lời','success']]; $s=$sm[$viewContact['status']]??['N/A','secondary']; ?>
                                <span class="badge bg-<?php echo $s[1]; ?>"><?php echo $s[0]; ?></span>
                            </td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nội dung tin nhắn:</label>
                        <div class="bg-light rounded p-3"><?php echo nl2br(htmlspecialchars($viewContact['message'])); ?></div>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?php echo $viewContact['id']; ?>">
                        <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto">
                            <option value="new" <?php echo $viewContact['status']=='new'?'selected':''; ?>>Mới</option>
                            <option value="read" <?php echo $viewContact['status']=='read'?'selected':''; ?>>Đã đọc</option>
                            <option value="replied" <?php echo $viewContact['status']=='replied'?'selected':''; ?>>Đã trả lời</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-navy ms-1"><i class="bi bi-save me-1"></i>Cập nhật</button>
                    </form>
                    <a href="mailto:<?php echo htmlspecialchars($viewContact['email']); ?>" class="btn btn-sm btn-gold">
                        <i class="bi bi-envelope me-1"></i>Gửi email trả lời
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-chat-dots-fill me-2"></i>Danh sách Liên hệ (<?php echo $total; ?>)</span>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="d-flex gap-2">
                        <select name="status" class="form-select" style="width:180px" onchange="this.form.submit()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="new" <?php echo $filter_status=='new'?'selected':''; ?>>Mới</option>
                            <option value="read" <?php echo $filter_status=='read'?'selected':''; ?>>Đã đọc</option>
                            <option value="replied" <?php echo $filter_status=='replied'?'selected':''; ?>>Đã trả lời</option>
                        </select>
                        <?php if ($filter_status): ?><a href="contacts.php" class="btn btn-outline-secondary">Xóa lọc</a><?php endif; ?>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>#</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Chủ đề</th><th>Trạng thái</th><th>Ngày gửi</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $statusMap = ['new'=>['Mới','warning'],'read'=>['Đã đọc','info'],'replied'=>['Đã trả lời','success']];
                            if ($contacts && $contacts->num_rows > 0): $idx=$offset+1; while ($c = $contacts->fetch_assoc()):
                            $s = $statusMap[$c['status']] ?? [$c['status'],'secondary'];
                            ?>
                            <tr class="<?php echo $c['status']=='new'?'table-warning':''; ?>">
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($c['full_name']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($c['email']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($c['phone']); ?></td>
                                <td><?php echo htmlspecialchars($c['subject']); ?></td>
                                <td><span class="badge bg-<?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                                <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></td>
                                <td>
                                    <a href="?view=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye-fill"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa liên hệ này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Không có liên hệ nào</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav><ul class="pagination justify-content-center mt-3">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body></html>
