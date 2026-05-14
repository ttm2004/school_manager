<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');

$pageTitle = 'Thống kê truy cập';
$onlineMinutes = max(1, min(120, (int)($_GET['minutes'] ?? 15)));
$roleFilter = trim($_GET['role'] ?? '');
$moduleFilter = trim($_GET['module'] ?? '');
$validRoles = ['admin', 'staff', 'teacher', 'student'];
if ($roleFilter !== '' && !in_array($roleFilter, $validRoles, true)) $roleFilter = '';

$where = ["vl.is_active=1", "vl.last_seen >= NOW() - INTERVAL ? MINUTE"];
$params = [$onlineMinutes];
$types = 'i';
if ($roleFilter !== '') {
    $where[] = "vl.role=?";
    $params[] = $roleFilter;
    $types .= 's';
}
if ($moduleFilter !== '') {
    $where[] = "vl.current_module=?";
    $params[] = $moduleFilter;
    $types .= 's';
}
$whereSql = implode(' AND ', $where);

$stats = [
    'online' => 0,
    'admin' => 0,
    'staff' => 0,
    'teacher' => 0,
    'student' => 0,
];
$rs = $conn->query("SELECT role, COUNT(*) c FROM visit_logs WHERE is_active=1 AND last_seen >= NOW() - INTERVAL {$onlineMinutes} MINUTE GROUP BY role");
if ($rs) {
    while ($r = $rs->fetch_assoc()) {
        $role = $r['role'] ?: 'unknown';
        $count = (int)$r['c'];
        $stats['online'] += $count;
        if (isset($stats[$role])) $stats[$role] = $count;
    }
}

$modules = [];
$mrs = $conn->query("SELECT current_module, COUNT(*) c FROM visit_logs WHERE is_active=1 AND last_seen >= NOW() - INTERVAL {$onlineMinutes} MINUTE GROUP BY current_module ORDER BY c DESC, current_module");
if ($mrs) while ($m = $mrs->fetch_assoc()) $modules[] = $m;

$stmt = $conn->prepare("
    SELECT vl.*, u.full_name, u.username, u.email
    FROM visit_logs vl
    LEFT JOIN users u ON u.id = vl.user_id
    WHERE {$whereSql}
    ORDER BY vl.last_seen DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$onlineUsers = $stmt->get_result();
$stmt->close();

$roleLabels = [
    'admin' => 'Quản trị',
    'staff' => 'Nhân viên',
    'teacher' => 'Giảng viên',
    'student' => 'Sinh viên',
];
$deviceLabels = [
    'desktop' => 'Máy tính',
    'mobile' => 'Điện thoại',
    'tablet' => 'Máy tính bảng',
];

function accessTimeAgo(?string $time): string {
    if (!$time) return '--';
    $diff = time() - strtotime($time);
    if ($diff < 60) return $diff . ' giây trước';
    if ($diff < 3600) return floor($diff / 60) . ' phút trước';
    return floor($diff / 3600) . ' giờ trước';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Thống kê truy cập</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="access_stats.php?minutes=<?php echo $onlineMinutes; ?>&role=<?php echo urlencode($roleFilter); ?>&module=<?php echo urlencode($moduleFilter); ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-clockwise me-1"></i>Làm mới
            </a>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Đăng xuất</a>
        </div>
    </div>
    <div class="admin-content">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-1">
                    <div class="stat-icon"><i class="bi bi-broadcast-pin"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($stats['online']); ?></div>
                    <div class="stat-label">Đang online</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-2">
                    <div class="stat-icon"><i class="bi bi-person-gear"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($stats['staff']); ?></div>
                    <div class="stat-label">Nhân viên</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-3">
                    <div class="stat-icon"><i class="bi bi-person-badge-fill"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($stats['teacher']); ?></div>
                    <div class="stat-label">Giảng viên</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card-admin stat-bg-4">
                    <div class="stat-icon"><i class="bi bi-mortarboard-fill"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($stats['student']); ?></div>
                    <div class="stat-label">Sinh viên</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-funnel-fill me-2"></i>Bộ lọc</span>
                <span class="badge bg-light text-dark">Hoạt động trong <?php echo $onlineMinutes; ?> phút gần nhất</span>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Khoảng online</label>
                        <select name="minutes" class="form-select">
                            <?php foreach ([5, 15, 30, 60, 120] as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $onlineMinutes === $m ? 'selected' : ''; ?>><?php echo $m; ?> phút</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Vai trò</label>
                        <select name="role" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($roleLabels as $code => $label): ?>
                            <option value="<?php echo $code; ?>" <?php echo $roleFilter === $code ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Module</label>
                        <select name="module" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($modules as $m): $module = $m['current_module'] ?: 'public'; ?>
                            <option value="<?php echo htmlspecialchars($module); ?>" <?php echo $moduleFilter === $module ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($module); ?> (<?php echo (int)$m['c']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-navy w-100"><i class="bi bi-search me-1"></i>Xem thống kê</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-people-fill me-2"></i>Người đang đăng nhập sử dụng hệ thống</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Người dùng</th>
                                <th>Vai trò</th>
                                <th>Module</th>
                                <th>Trang hiện tại</th>
                                <th>Thiết bị/IP</th>
                                <th>Đăng nhập</th>
                                <th>Hoạt động cuối</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($onlineUsers && $onlineUsers->num_rows > 0): while ($u = $onlineUsers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($u['full_name'] ?: 'Không rõ'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($u['username'] ?: ('user_id=' . $u['user_id'])); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($roleLabels[$u['role']] ?? ($u['role'] ?: '--')); ?></span>
                                    <?php if (!empty($u['active_role'])): ?>
                                    <div class="text-muted small mt-1"><?php echo htmlspecialchars($u['active_role']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['current_module'] ?: 'public'); ?></span></td>
                                <td class="small text-muted" style="max-width:260px;word-break:break-word;">
                                    <?php echo htmlspecialchars($u['current_path'] ?: '--'); ?>
                                </td>
                                <td class="small">
                                    <div><?php echo htmlspecialchars($deviceLabels[$u['device']] ?? ($u['device'] ?: '--')); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($u['ip'] ?: '--'); ?></div>
                                </td>
                                <td class="small text-muted"><?php echo $u['login_at'] ? date('d/m/Y H:i', strtotime($u['login_at'])) : '--'; ?></td>
                                <td>
                                    <span class="text-success fw-semibold"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i><?php echo accessTimeAgo($u['last_seen']); ?></span>
                                    <div class="text-muted small"><?php echo date('H:i:s', strtotime($u['last_seen'])); ?></div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Chưa có phiên đang hoạt động.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
setTimeout(() => window.location.reload(), 60000);
</script>
</body></html>
