<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Dashboard';

$totalUsers    = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'] ?? 0;
$totalFaculties = $conn->query("SELECT COUNT(*) as c FROM faculties")->fetch_assoc()['c'] ?? 0;
$totalMajors   = $conn->query("SELECT COUNT(*) as c FROM majors")->fetch_assoc()['c'] ?? 0;
$totalClasses  = $conn->query("SELECT COUNT(*) as c FROM classes")->fetch_assoc()['c'] ?? 0;
$totalNotifications = $conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'] ?? 0;
$newContacts   = $conn->query("SELECT COUNT(*) as c FROM contacts WHERE status='new'")->fetch_assoc()['c'] ?? 0;

$recentContacts = $conn->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5");
$recentUsers = $conn->query("SELECT id, username, full_name, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 5");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Dashboard</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>&#272;&#259;ng xu&#7845;t</a>
        </div>
    </div>
    <div class="admin-content">
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-1">
                    <div class="stat-icon"><i class="bi bi-person-fill-check"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-label">Người dùng</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-2">
                    <div class="stat-icon"><i class="bi bi-building-fill"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalFaculties); ?></div>
                    <div class="stat-label">Khoa/Viện</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-3">
                    <div class="stat-icon"><i class="bi bi-book-fill"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalMajors); ?></div>
                    <div class="stat-label">Ngành học</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-4">
                    <div class="stat-icon"><i class="bi bi-collection-fill"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalClasses); ?></div>
                    <div class="stat-label">Lớp hành chính</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-5">
                    <div class="stat-icon"><i class="bi bi-bell-fill"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($totalNotifications); ?></div>
                    <div class="stat-label">Thông báo</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card-admin stat-bg-6">
                    <div class="stat-icon"><i class="bi bi-chat-dots-fill"></i></div>
                    <div class="stat-value mt-2"><?php echo number_format($newContacts); ?></div>
                    <div class="stat-label">Li&#234;n h&#7879; m&#7899;i</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-chat-dots me-2"></i>Li&#234;n h&#7879; m&#7899;i nh&#7845;t</span>
                        <a href="/university/admin/contacts.php" class="btn btn-sm btn-outline-light">Xem t&#7845;t c&#7843;</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>H&#7885; t&#234;n</th><th>Email</th><th>Tr&#7841;ng th&#225;i</th><th>Ng&#224;y</th></tr></thead>
                                <tbody>
                                <?php
                                $csMap = ['new'=>['M&#7899;i','warning'],'read'=>['&#272;&#227; &#273;&#7885;c','info'],'replied'=>['&#272;&#227; tr&#7843; l&#7901;i','success']];
                                if ($recentContacts && $recentContacts->num_rows > 0):
                                    while ($c = $recentContacts->fetch_assoc()):
                                        $cs = $csMap[$c['status']] ?? ['N/A','secondary'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($c['full_name']); ?></div>
                                        <?php if (!empty($c['subject'])): ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars(mb_substr($c['subject'],0,30)); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($c['email']); ?></td>
                                    <td><span class="badge bg-<?php echo $cs[1]; ?>"><?php echo $cs[0]; ?></span></td>
                                    <td class="text-muted small"><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Ch&#432;a c&#243; li&#234;n h&#7879; n&#224;o</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-lines-fill me-2"></i>Người dùng mới nhất</span>
                        <a href="/university/admin/users.php" class="btn btn-sm btn-outline-light">Quản lý</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Họ tên</th><th>Tài khoản</th><th>Vai trò</th><th>Trạng thái</th></tr></thead>
                                <tbody>
                                <?php
                                $roleMap = [
                                    'admin' => 'Quản trị',
                                    'staff' => 'Nhân sự',
                                    'teacher' => 'Giảng viên',
                                    'student' => 'Sinh viên',
                                ];
                                if ($recentUsers && $recentUsers->num_rows > 0):
                                    while ($user = $recentUsers->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($roleMap[$user['role']] ?? $user['role']); ?></td>
                                    <td>
                                        <?php if ((int)$user['status'] === 1): ?>
                                        <span class="badge bg-success">Đang hoạt động</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Đã khóa</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Chưa có người dùng</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Tr&#432;&#7901;ng &#272;&#7841;i h&#7885;c Th&#7911; D&#7847;u M&#7897;t</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body></html>
