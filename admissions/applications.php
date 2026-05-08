<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);
$pageTitle = 'Hồ sơ Xét tuyển';
include __DIR__ . '/includes/header.php';

$success = $error = '';
$isReviewing     = isReviewingPhase();
$roundMsg        = getRoundStatusMessage();
$activeRound     = getActiveRound();
$roundPhase      = getRoundPhase();

// Logic khóa:
// - Đang xét tuyển (reviewing/supp_reviewing): khóa tất cả thủ công, kể cả admin
// - Không có đợt active (no_round/completed): khóa tất cả thủ công
// - Các giai đoạn khác: cho phép theo quyền
$isLocked = in_array($roundPhase, ['reviewing', 'supp_reviewing', 'no_round', 'completed']);
$canManualReview = !$isLocked;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        if (!hasPermission('admissions', 'edit_application')) {
            $error = 'Bạn không có quyền cập nhật trạng thái hồ sơ.';
        } elseif (!$canManualReview) {
            $error = '⚠️ Đang trong giai đoạn xét tuyển — không thể cập nhật trạng thái thủ công.';
        } else {
            $id     = intval($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            if ($id && in_array($status, ['new','checking','approved','rejected','enrolled'])) {
                $stmt = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=?");
                $stmt->bind_param('si', $status, $id);
                $stmt->execute() ? $success = 'Cập nhật trạng thái thành công!' : $error = 'Lỗi: ' . $conn->error;
                $stmt->close();
            }
        }
    }

    if ($action === 'delete') {
        if (!hasPermission('admissions', 'delete_application')) {
            $error = 'Bạn không có quyền xóa hồ sơ.';
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("DELETE FROM admission_applications WHERE id=?");
                $stmt->bind_param('i', $id);
                $stmt->execute() ? $success = 'Xóa hồ sơ thành công!' : $error = 'Lỗi: ' . $conn->error;
                $stmt->close();
            }
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

$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$filter_status = trim($_GET['status'] ?? '');
$filter_major  = intval($_GET['major_id'] ?? 0);
$filter_search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = '';
if ($filter_status) { $where[] = 'aa.status=?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_major)  { $where[] = 'aa.major_id=?'; $params[] = $filter_major; $types .= 'i'; }
if ($filter_search) { $where[] = '(aa.full_name LIKE ? OR aa.email LIKE ? OR aa.citizen_id LIKE ?)'; $like = "%$filter_search%"; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss'; }
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

$statusMap = [
    'new'      => ['Mới','warning'],
    'checking' => ['Đang xét','info'],
    'approved' => ['Đã duyệt','success'],
    'rejected' => ['Từ chối','danger'],
    'enrolled' => ['Nhập học','primary'],
];


?>
<?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($isLocked): ?>
<!-- Modal thông báo khóa -->
<div class="modal fade" id="statusModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0" style="background:rgba(239,68,68,.08);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:42px;height:42px;border-radius:50%;background:rgba(239,68,68,.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="bi bi-lock-fill" style="color:#dc2626;"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" style="color:#dc2626;">Hồ sơ chỉ xem</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="statusModalClose"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0" style="font-size:.9rem;line-height:1.6;">
                <?php if ($roundPhase === 'reviewing' || $roundPhase === 'supp_reviewing'): ?>
                    Đang trong giai đoạn <strong>xét tuyển</strong> — không thể cập nhật trạng thái hồ sơ thủ công để đảm bảo tính công bằng.
                <?php elseif ($roundPhase === 'completed'): ?>
                    Đợt tuyển sinh đã <strong>hoàn tất</strong> — hồ sơ không thể thay đổi.
                <?php else: ?>
                    Không có đợt tuyển sinh đang hoạt động — hồ sơ chỉ được xem.
                <?php endif; ?>
                </p>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:4px;border-radius:4px;">
                        <div id="statusModalProgress" class="progress-bar bg-danger" style="width:100%;transition:width linear;"></div>
                    </div>
                    <small class="text-muted" id="statusModalCountdown" style="font-size:.72rem;white-space:nowrap;"></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
    const duration = 7000;
    const bar = document.getElementById('statusModalProgress');
    const countdown = document.getElementById('statusModalCountdown');
    let remaining = Math.ceil(duration / 1000);
    countdown.textContent = remaining + 's';
    const tick = setInterval(function() { remaining--; countdown.textContent = remaining + 's'; if (remaining <= 0) { clearInterval(tick); modal.hide(); } }, 1000);
    requestAnimationFrame(function() { requestAnimationFrame(function() { bar.style.transitionDuration = duration + 'ms'; bar.style.width = '0%'; }); });
    document.getElementById('statusModalClose').addEventListener('click', function() { clearInterval(tick); });
});
</script>
<?php elseif ($roundMsg[0] !== 'secondary'): ?>
<!-- Modal thông báo trạng thái đợt -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0" style="background:rgba(16,185,129,.08);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:42px;height:42px;border-radius:50%;background:rgba(16,185,129,.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="bi bi-calendar-check" style="color:#059669;"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" style="color:#059669;">Trạng thái đợt tuyển sinh</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="statusModalClose"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0" style="font-size:.9rem;"><?php echo $roundMsg[1]; ?></p>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:4px;border-radius:4px;">
                        <div id="statusModalProgress" class="progress-bar bg-success" style="width:100%;transition:width linear;"></div>
                    </div>
                    <small class="text-muted" id="statusModalCountdown" style="font-size:.72rem;white-space:nowrap;"></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
    const duration = 5000;
    const bar = document.getElementById('statusModalProgress');
    const countdown = document.getElementById('statusModalCountdown');
    let remaining = Math.ceil(duration / 1000);
    countdown.textContent = remaining + 's';
    const tick = setInterval(function() { remaining--; countdown.textContent = remaining + 's'; if (remaining <= 0) { clearInterval(tick); modal.hide(); } }, 1000);
    requestAnimationFrame(function() { requestAnimationFrame(function() { bar.style.transitionDuration = duration + 'ms'; bar.style.width = '0%'; }); });
    document.getElementById('statusModalClose').addEventListener('click', function() { clearInterval(tick); });
});
</script>
<?php endif; ?>

        <?php if ($viewApp): ?>
        <!-- Detail View -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-person me-2"></i>Chi tiết Hồ sơ #<?php echo $viewApp['id']; ?></span>
                <a href="applications.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="text-navy fw-bold mb-3 border-bottom pb-2">Thông tin cá nhân</h6>
                        <table class="table table-borderless table-sm">
                            <tr><th width="150">Họ tên:</th><td class="fw-bold"><?php echo htmlspecialchars($viewApp['full_name']); ?></td></tr>
                            <tr><th>Giới tính:</th><td><?php echo htmlspecialchars($viewApp['gender'] ?? '--'); ?></td></tr>
                            <tr><th>Ngày sinh:</th><td><?php echo $viewApp['birthday'] ? date('d/m/Y', strtotime($viewApp['birthday'])) : '--'; ?></td></tr>
                            <tr><th>CCCD/CMND:</th><td><?php echo htmlspecialchars($viewApp['citizen_id'] ?? '--'); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo htmlspecialchars($viewApp['email']); ?></td></tr>
                            <tr><th>Điện thoại:</th><td><?php echo htmlspecialchars($viewApp['phone'] ?? '--'); ?></td></tr>
                            <tr><th>Địa chỉ:</th><td><?php echo htmlspecialchars($viewApp['address'] ?? '--'); ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-navy fw-bold mb-3 border-bottom pb-2">Thông tin xét tuyển</h6>
                        <table class="table table-borderless table-sm">
                            <tr><th width="150">Ngành đăng ký:</th><td class="fw-bold text-navy"><?php echo htmlspecialchars($viewApp['major_name'] ?? '--'); ?></td></tr>
                            <tr><th>Phương thức:</th><td><?php echo htmlspecialchars($viewApp['method_name'] ?? '--'); ?></td></tr>
                            <tr><th>Trường THPT:</th><td><?php echo htmlspecialchars($viewApp['high_school'] ?? '--'); ?></td></tr>
                            <tr><th>Năm tốt nghiệp:</th><td><?php echo htmlspecialchars($viewApp['graduation_year'] ?? '--'); ?></td></tr>
                            <tr><th>Điểm Toán:</th><td><?php echo number_format($viewApp['math_score'] ?? 0, 2); ?></td></tr>
                            <tr><th>Điểm Văn:</th><td><?php echo number_format($viewApp['literature_score'] ?? 0, 2); ?></td></tr>
                            <tr><th>Điểm Anh:</th><td><?php echo number_format($viewApp['english_score'] ?? 0, 2); ?></td></tr>
                            <tr><th>Tổng điểm:</th>
                                <td>
                                    <span class="fw-bold fs-5 text-success">
                                        <?php echo number_format(($viewApp['math_score'] ?? 0) + ($viewApp['literature_score'] ?? 0) + ($viewApp['english_score'] ?? 0), 2); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                        <?php if (!empty($viewApp['note'])): ?>
                        <div class="bg-light rounded p-2 mt-1"><small class="text-muted"><i class="bi bi-sticky me-1"></i><?php echo htmlspecialchars($viewApp['note']); ?></small></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-top d-flex gap-3 align-items-center flex-wrap">
                    <strong class="text-navy">Trạng thái hiện tại:</strong>
                    <?php $s = $statusMap[$viewApp['status']] ?? [$viewApp['status'],'secondary']; ?>
                    <span class="badge bg-<?php echo $s[1]; ?> fs-6"><?php echo $s[0]; ?></span>
                    <?php if (hasPermission('admissions', 'edit_application') && $canManualReview): ?>
                    <form method="POST" class="d-flex gap-2 align-items-center ms-auto">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?php echo $viewApp['id']; ?>">
                        <select name="status" class="form-select form-select-sm" style="width:auto">
                            <?php foreach ($statusMap as $val => [$label, $color]): ?>
                            <option value="<?php echo $val; ?>" <?php echo $viewApp['status']==$val?'selected':''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-save me-1"></i>Cập nhật</button>
                    </form>
                    <?php elseif ($isLocked): ?>
                    <span class="text-danger small ms-auto"><i class="bi bi-lock-fill me-1"></i>Hồ sơ đã khóa — không thể sửa</span>
                    <?php else: ?>
                    <span class="text-muted small ms-auto"><i class="bi bi-lock me-1"></i>Chỉ xem</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter & List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-funnel-fill me-2"></i>Danh sách Hồ sơ
                    <span class="badge bg-gold text-navy ms-1"><?php echo $total; ?></span>
                </span>
            </div>
            <div class="card-body border-bottom">
                <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
                    <div>
                        <label class="form-label small mb-1">Tìm kiếm</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Tên, email, CCCD..." value="<?php echo htmlspecialchars($filter_search); ?>" style="width:200px">
                    </div>
                    <div>
                        <label class="form-label small mb-1">Trạng thái</label>
                        <select name="status" class="form-select form-select-sm" style="width:140px">
                            <option value="">Tất cả</option>
                            <?php foreach ($statusMap as $val => [$label, $color]): ?>
                            <option value="<?php echo $val; ?>" <?php echo $filter_status==$val?'selected':''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Ngành</label>
                        <select name="major_id" class="form-select form-select-sm" style="width:200px">
                            <option value="">Tất cả ngành</option>
                            <?php if ($majors): while ($mj = $majors->fetch_assoc()): ?>
                            <option value="<?php echo $mj['id']; ?>" <?php echo $filter_major==$mj['id']?'selected':''; ?>><?php echo htmlspecialchars($mj['major_name']); ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search me-1"></i>Lọc</button>
                    <?php if ($filter_status || $filter_major || $filter_search): ?>
                    <a href="applications.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x me-1"></i>Xóa lọc</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>#</th><th>Họ tên</th><th>Ngành</th><th>Phương thức</th><th>Tổng điểm</th><th>Trạng thái</th><th>Ngày nộp</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($applications && $applications->num_rows > 0): $idx=$offset+1; while ($app = $applications->fetch_assoc()):
                                $s = $statusMap[$app['status']] ?? [$app['status'],'secondary'];
                            ?>
                            <tr>
                                <td class="text-muted small"><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($app['email']); ?></div>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($app['major_name'] ?? '--'); ?></td>
                                <td class="text-muted small"><?php echo mb_substr($app['method_name'] ?? '--', 0, 25); ?></td>
                                <td class="fw-bold text-success"><?php echo number_format(($app['math_score'] ?? 0) + ($app['literature_score'] ?? 0) + ($app['english_score'] ?? 0), 2); ?></td>
                                <td><span class="badge bg-<?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                                <td class="text-muted small"><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <a href="?view=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Xem chi tiết"><i class="bi bi-eye-fill"></i></a>
                                    <?php if (hasPermission('admissions', 'delete_application')): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa hồ sơ này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có hồ sơ nào
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav class="p-3"><ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?status=<?php echo urlencode($filter_status); ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>
<?php include __DIR__ . '/includes/footer.php'; ?>
