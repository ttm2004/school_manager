<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Hồ sơ Xét tuyển';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $id     = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if ($id && in_array($status, ['new','checking','approved','rejected'])) {
            $stmt = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute() ? $success = 'Cập nhật trạng thái thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM admission_applications WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa hồ sơ thành công!' : $error = 'Lỗi: ' . $conn->error;
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
$filter_major  = intval($_GET['major_id'] ?? 0);

$where = [];
$params = [];
$types = '';
if ($filter_status) { $where[] = 'aa.status=?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_major)  { $where[] = 'aa.major_id=?'; $params[] = $filter_major; $types .= 'i'; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSQL = "SELECT COUNT(*) as c FROM admission_applications aa $whereSQL";
if ($params) {
    $cs = $conn->prepare($countSQL);
    $cs->bind_param($types, ...$params);
    $cs->execute();
    $total = $cs->get_result()->fetch_assoc()['c'];
    $cs->close();
} else {
    $total = $conn->query($countSQL)->fetch_assoc()['c'];
}

$dataSQL = "SELECT aa.*, m.major_name, am.method_name
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id = m.id
    LEFT JOIN admission_methods am ON aa.method_id = am.id
    $whereSQL ORDER BY aa.created_at DESC LIMIT ? OFFSET ?";

$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt = $conn->prepare($dataSQL);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$applications = $stmt->get_result();
$stmt->close();
$totalPages = ceil($total / $perPage);

$majors = $conn->query("SELECT id, major_name FROM majors ORDER BY major_name");

// View detail
$viewApp = null;
if (isset($_GET['view'])) {
    $vid = intval($_GET['view']);
    $vs = $conn->prepare("SELECT aa.*, m.major_name, am.method_name FROM admission_applications aa LEFT JOIN majors m ON aa.major_id=m.id LEFT JOIN admission_methods am ON aa.method_id=am.id WHERE aa.id=?");
    $vs->bind_param('i', $vid);
    $vs->execute();
    $viewApp = $vs->get_result()->fetch_assoc();
    $vs->close();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Hồ sơ Xét tuyển</span>
        </div>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <?php if ($viewApp): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-person me-2"></i>Chi tiết Hồ sơ #<?php echo $viewApp['id']; ?></span>
                <a href="admission_applications.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="text-navy fw-bold mb-3">Thông tin cá nhân</h6>
                        <table class="table table-borderless table-sm">
                            <tr><th width="160">Họ tên:</th><td class="fw-bold"><?php echo htmlspecialchars($viewApp['full_name']); ?></td></tr>
                            <tr><th>Giới tính:</th><td><?php echo $viewApp['gender']; ?></td></tr>
                            <tr><th>Ngày sinh:</th><td><?php echo $viewApp['birthday'] ? date('d/m/Y', strtotime($viewApp['birthday'])) : '--'; ?></td></tr>
                            <tr><th>CCCD/CMND:</th><td><?php echo htmlspecialchars($viewApp['citizen_id']); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo htmlspecialchars($viewApp['email']); ?></td></tr>
                            <tr><th>Điện thoại:</th><td><?php echo htmlspecialchars($viewApp['phone']); ?></td></tr>
                            <tr><th>Địa chỉ:</th><td><?php echo htmlspecialchars($viewApp['address']); ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-navy fw-bold mb-3">Thông tin xét tuyển</h6>
                        <table class="table table-borderless table-sm">
                            <tr><th width="160">Ngành đăng ký:</th><td class="fw-bold text-navy"><?php echo htmlspecialchars($viewApp['major_name']); ?></td></tr>
                            <tr><th>Phương thức:</th><td><?php echo htmlspecialchars($viewApp['method_name']); ?></td></tr>
                            <tr><th>Trường THPT:</th><td><?php echo htmlspecialchars($viewApp['high_school']); ?></td></tr>
                            <tr><th>Năm tốt nghiệp:</th><td><?php echo htmlspecialchars($viewApp['graduation_year']); ?></td></tr>
                            <tr><th>Điểm Toán:</th><td><?php echo number_format($viewApp['math_score'], 2); ?></td></tr>
                            <tr><th>Điểm Văn:</th><td><?php echo number_format($viewApp['literature_score'], 2); ?></td></tr>
                            <tr><th>Điểm Anh:</th><td><?php echo number_format($viewApp['english_score'], 2); ?></td></tr>
                            <tr><th>Tổng điểm:</th><td class="fw-bold fs-5 text-success"><?php echo number_format($viewApp['math_score'] + $viewApp['literature_score'] + $viewApp['english_score'], 2); ?></td></tr>
                        </table>
                        <?php if ($viewApp['note']): ?>
                        <div class="bg-light rounded p-2 mt-2"><small class="text-muted">Ghi chú: <?php echo htmlspecialchars($viewApp['note']); ?></small></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2 align-items-center">
                    <strong>Cập nhật trạng thái:</strong>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?php echo $viewApp['id']; ?>">
                        <select name="status" class="form-select form-select-sm" style="width:auto">
                            <option value="new" <?php echo $viewApp['status']=='new'?'selected':''; ?>>Mới</option>
                            <option value="checking" <?php echo $viewApp['status']=='checking'?'selected':''; ?>>Đang xét</option>
                            <option value="approved" <?php echo $viewApp['status']=='approved'?'selected':''; ?>>Đã duyệt</option>
                            <option value="rejected" <?php echo $viewApp['status']=='rejected'?'selected':''; ?>>Từ chối</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-save me-1"></i>Lưu</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-person-fill me-2"></i>Danh sách Hồ sơ (<?php echo $total; ?>)</span>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <select name="status" class="form-select" style="width:160px" onchange="this.form.submit()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="new" <?php echo $filter_status=='new'?'selected':''; ?>>Mới</option>
                            <option value="checking" <?php echo $filter_status=='checking'?'selected':''; ?>>Đang xét</option>
                            <option value="approved" <?php echo $filter_status=='approved'?'selected':''; ?>>Đã duyệt</option>
                            <option value="rejected" <?php echo $filter_status=='rejected'?'selected':''; ?>>Từ chối</option>
                        </select>
                        <select name="major_id" class="form-select" style="width:220px" onchange="this.form.submit()">
                            <option value="">Tất cả ngành</option>
                            <?php if ($majors): while ($mj = $majors->fetch_assoc()): ?>
                            <option value="<?php echo $mj['id']; ?>" <?php echo $filter_major==$mj['id']?'selected':''; ?>><?php echo htmlspecialchars($mj['major_name']); ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                        <?php if ($filter_status || $filter_major): ?><a href="admission_applications.php" class="btn btn-outline-secondary">Xóa lọc</a><?php endif; ?>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>#</th><th>Họ tên</th><th>Ngành</th><th>Phương thức</th><th>Tổng điểm</th><th>Trạng thái</th><th>Ngày nộp</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $statusMap = ['new'=>['Mới','warning'],'checking'=>['Đang xét','info'],'approved'=>['Đã duyệt','success'],'rejected'=>['Từ chối','danger']];
                            if ($applications && $applications->num_rows > 0): $idx=$offset+1; while ($app = $applications->fetch_assoc()):
                            $s = $statusMap[$app['status']] ?? [$app['status'],'secondary'];
                            ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($app['email']); ?></div>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($app['major_name']); ?></td>
                                <td class="text-muted small"><?php echo mb_substr($app['method_name'] ?? '',0,30); ?></td>
                                <td class="fw-bold text-success"><?php echo number_format($app['math_score'] + $app['literature_score'] + $app['english_score'], 2); ?></td>
                                <td><span class="badge bg-<?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                                <td class="text-muted small"><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <a href="?view=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye-fill"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa hồ sơ này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Không có hồ sơ nào</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav><ul class="pagination justify-content-center mt-3">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&major_id=<?php echo $filter_major; ?>&page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&major_id=<?php echo $filter_major; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&major_id=<?php echo $filter_major; ?>&page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
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
