<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

// Chỉ dành cho staff (nhân viên các phòng ban), không dành cho admin chung
if ($_SESSION['role'] === 'admin') {
    header('Location: /university/admin/');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$success = $error = '';

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if (!$full_name) {
            $error = 'Vui lòng nhập họ và tên.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param('sssi', $full_name, $email, $phone, $userId);
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $success = 'Cập nhật thông tin thành công!';
            } else {
                $error = 'Lỗi: ' . $conn->error;
            }
            $stmt->close();
        }
    }

    if ($action === 'change_password') {
        $current  = trim($_POST['current_password'] ?? '');
        $new_pw   = trim($_POST['new_password'] ?? '');
        $confirm  = trim($_POST['confirm_password'] ?? '');

        if (!$current || !$new_pw || !$confirm) {
            $error = 'Vui lòng điền đầy đủ thông tin đổi mật khẩu.';
        } elseif ($new_pw !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } elseif (strlen($new_pw) < 6) {
            $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        } else {
            // Kiểm tra mật khẩu hiện tại
            $chk = $conn->prepare("SELECT password FROM users WHERE id=?");
            $chk->bind_param('i', $userId); $chk->execute();
            $row = $chk->get_result()->fetch_assoc(); $chk->close();

            if (!$row || !password_verify($current, $row['password'])) {
                $error = 'Mật khẩu hiện tại không đúng.';
            } else {
                $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param('si', $hashed, $userId);
                $stmt->execute() ? $success = 'Đổi mật khẩu thành công!' : $error = 'Lỗi: ' . $conn->error;
                $stmt->close();
            }
        }
    }
}

// Lấy thông tin user
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $userId); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$user) {
    header('Location: /university/login.php?logout=1');
    exit();
}

// Lấy roles của user
$roles = getUserRoles();

// Lấy lịch sử đăng nhập gần đây (nếu có bảng audit_log)
// Tạm thời bỏ qua nếu chưa có bảng

// Xác định URL quay lại:
// 1. Dùng HTTP_REFERER nếu có và thuộc cùng domain
// 2. Fallback về module theo role
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$siteBase = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/university/';

if ($referer && str_starts_with($referer, $siteBase) && !str_contains($referer, 'profile.php')) {
    // Dùng referer nếu hợp lệ và không phải chính trang này
    $backUrl = $referer;
} else {
    // Fallback theo role
    if ($_SESSION['role'] === 'admin') {
        $backUrl = '/university/admin/';
    } else {
        $backUrl = '/university/no_access.php';
        foreach ($roles as $r) {
            $prefixMap = [
                'admissions_' => '/university/admissions/',
                'academic_'   => '/university/academic/',
                'finance_'    => '/university/finance/',
                'hr_'         => '/university/hr/',
                'exam_'       => '/university/exam/',
                'it_'         => '/university/admin/',
            ];
            foreach ($prefixMap as $prefix => $url) {
                if (str_starts_with($r['code'], $prefix)) {
                    $backUrl = $url;
                    break 2;
                }
            }
        }
    }
}

// Determine which header to include based on module
$moduleHeader = null;
if (str_contains($backUrl, '/admissions/')) {
    $moduleHeader = '/university/admissions/includes/header.php';
}

// Use a standalone layout since this is shared across modules
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin cá nhân — TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        :root { --navy:#0d2d6b; --gold:#f5a623; }
        body { background:#f0f2f7; font-family:'Segoe UI',system-ui,sans-serif; }
        .profile-header {
            background: linear-gradient(135deg, var(--navy) 0%, #1a4fa0 100%);
            padding: 40px 0 80px;
            position: relative;
        }
        .profile-header::after {
            content:''; position:absolute; bottom:0; left:0; right:0; height:40px;
            background:#f0f2f7; border-radius:40px 40px 0 0;
        }
        .avatar-wrap {
            width:100px; height:100px; border-radius:50%;
            background:var(--gold); display:flex; align-items:center;
            justify-content:center; font-size:2.5rem; font-weight:800;
            color:var(--navy); border:4px solid #fff;
            box-shadow:0 8px 24px rgba(0,0,0,.2);
        }
        .role-badge {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 12px; border-radius:20px; font-size:.78rem;
            font-weight:600; color:#fff; margin:2px;
        }
        .info-card { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(13,45,107,.08); border:1px solid #e2e8f0; }
        .info-label { font-size:.78rem; color:#6b7a99; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .info-value { font-size:.95rem; color:#1a2340; font-weight:500; }
        .section-title { font-size:1rem; font-weight:700; color:var(--navy); border-bottom:2px solid var(--gold); padding-bottom:8px; margin-bottom:20px; }
        .back-btn { position:absolute; top:20px; left:20px; }
    </style>
</head>
<body>

<!-- Header -->
<div class="profile-header">
    <div class="container position-relative">
        <a href="<?php echo $backUrl; ?>" class="back-btn btn btn-sm btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>
        <div class="text-center text-white">
            <div class="avatar-wrap mx-auto mb-3">
                <?php echo mb_strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
            </div>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
            <div class="text-white-50 small mb-2">@<?php echo htmlspecialchars($user['username']); ?></div>
            <div class="d-flex flex-wrap justify-content-center gap-1">
                <?php foreach ($roles as $r): ?>
                <span class="role-badge" style="background:<?php echo $r['color'] ?? '#6b7a99'; ?>">
                    <i class="bi bi-building"></i>
                    <?php echo htmlspecialchars($r['name']); ?>
                </span>
                <?php endforeach; ?>
                <?php if (empty($roles)): ?>
                <span class="role-badge" style="background:#6b7a99">Chưa có quyền phòng ban</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5" style="margin-top:-20px; position:relative; z-index:1;">

    <?php if ($success): ?>
    <div class="alert alert-success auto-dismiss alert-dismissible fade show mb-4">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger auto-dismiss alert-dismissible fade show mb-4">
        <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Thông tin cá nhân -->
        <div class="col-lg-8">
            <div class="info-card p-4 mb-4">
                <div class="section-title"><i class="bi bi-person-fill me-2"></i>Thông tin cá nhân</div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_info">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="info-label d-block mb-1">Họ và tên</label>
                            <input type="text" name="full_name" class="form-control"
                                value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label d-block mb-1">Tên đăng nhập</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label d-block mb-1">Email</label>
                            <input type="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="info-label d-block mb-1">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="info-label d-block mb-1">Ngày tạo tài khoản</label>
                            <input type="text" class="form-control bg-light"
                                value="<?php echo date('d/m/Y', strtotime($user['created_at'])); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="info-label d-block mb-1">Trạng thái</label>
                            <input type="text" class="form-control bg-light"
                                value="<?php echo $user['status'] ? 'Đang hoạt động' : 'Đã khóa'; ?>" readonly>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-save me-1"></i>Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>

            <!-- Đổi mật khẩu -->
            <div class="info-card p-4">
                <div class="section-title"><i class="bi bi-lock-fill me-2"></i>Đổi mật khẩu</div>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="info-label d-block mb-1">Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="info-label d-block mb-1">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-4">
                            <label class="info-label d-block mb-1">Xác nhận mật khẩu</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-key me-1"></i>Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar thông tin -->
        <div class="col-lg-4">
            <!-- Quyền phòng ban -->
            <div class="info-card p-4 mb-4">
                <div class="section-title"><i class="bi bi-shield-fill me-2"></i>Quyền phòng ban</div>
                <?php if (!empty($roles)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php
                    $deptGroups = [];
                    foreach ($roles as $r) {
                        $deptGroups[$r['department']][] = $r;
                    }
                    foreach ($deptGroups as $dept => $deptRoles):
                    ?>
                    <div>
                        <div class="text-muted small fw-semibold mb-1"><?php echo htmlspecialchars($dept); ?></div>
                        <?php foreach ($deptRoles as $r): ?>
                        <span class="role-badge d-inline-flex mb-1" style="background:<?php echo $r['color'] ?? '#6b7a99'; ?>">
                            <i class="bi bi-check-circle me-1"></i>
                            <?php echo htmlspecialchars($r['name']); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-shield-x fs-2 d-block mb-2"></i>
                    <small>Chưa được cấp quyền phòng ban nào.<br>Liên hệ quản trị viên.</small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Thống kê nhanh theo module -->
            <?php if (!empty($roles)): ?>
            <div class="info-card p-4">
                <div class="section-title"><i class="bi bi-bar-chart-fill me-2"></i>Thống kê nhanh</div>
                <?php
                // Hiển thị thống kê theo module của nhân viên
                $firstRole = $roles[0]['code'] ?? '';
                if (str_starts_with($firstRole, 'admissions_')):
                    $totalApps = $conn->query("SELECT COUNT(*) as c FROM admission_applications")->fetch_assoc()['c'] ?? 0;
                    $pendingApps = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='new'")->fetch_assoc()['c'] ?? 0;
                    $enrolledApps = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='enrolled'")->fetch_assoc()['c'] ?? 0;
                ?>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f8f9ff">
                        <span class="small text-muted">Tổng hồ sơ</span>
                        <span class="fw-bold text-navy"><?php echo number_format($totalApps); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#fff8f0">
                        <span class="small text-muted">Chờ xét</span>
                        <span class="fw-bold" style="color:#c97a00"><?php echo number_format($pendingApps); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f0fdf4">
                        <span class="small text-muted">Đã nhập học</span>
                        <span class="fw-bold text-success"><?php echo number_format($enrolledApps); ?></span>
                    </div>
                </div>
                <a href="/university/admissions/" class="btn btn-sm btn-navy w-100 mt-3">
                    <i class="bi bi-arrow-right me-1"></i>Vào module Tuyển sinh
                </a>
                <?php else: ?>
                <div class="text-center text-muted py-2 small">
                    <i class="bi bi-info-circle me-1"></i>Module chưa được xây dựng.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body>
</html>
