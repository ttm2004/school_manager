<?php

require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();
$userName = $_SESSION['full_name'] ?? 'Nhân viên';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chưa có quyền truy cập - TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f2f7; font-family: 'Segoe UI', sans-serif; }
        .box {
            max-width: 520px; margin: 80px auto; background: #fff;
            border-radius: 16px; padding: 48px 40px; text-align: center;
            box-shadow: 0 4px 24px rgba(13,45,107,.1);
        }
        .icon-wrap {
            width: 80px; height: 80px; background: rgba(239,68,68,.1);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; margin: 0 auto 24px; font-size: 2.5rem; color: #dc2626;
        }
        h2 { color: #0d2d6b; font-weight: 700; margin-bottom: 12px; }
        p { color: #6b7a99; line-height: 1.7; }
        .roles-list { background: #f8f9ff; border-radius: 10px; padding: 16px; margin: 20px 0; text-align: left; }
        .roles-list .badge { font-size: .8rem; padding: 5px 10px; }
    </style>
</head>
<body>
<div class="box">
    <div class="icon-wrap"><i class="bi bi-shield-exclamation"></i></div>
    <h2>Chưa có quyền truy cập</h2>
    <p>Xin chào <strong><?php echo htmlspecialchars($userName); ?></strong>,<br>
    Tài khoản của bạn chưa được cấp quyền truy cập vào bất kỳ module nào.<br>
    Vui lòng liên hệ quản trị viên để được cấp quyền.</p>

    <?php
    // Hiển thị roles hiện tại nếu có
    $uid = (int)$_SESSION['user_id'];
    $roles = $conn->query("
        SELECT r.name, r.department, r.color FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = $uid AND r.is_active = 1
    ");
    if ($roles && $roles->num_rows > 0):
    ?>
    <div class="roles-list">
        <small class="text-muted d-block mb-2 fw-semibold">Quyền hiện tại của bạn:</small>
        <?php while ($r = $roles->fetch_assoc()): ?>
        <span class="badge me-1 mb-1" style="background:<?php echo $r['color']; ?>">
            <?php echo htmlspecialchars($r['name']); ?>
        </span>
        <?php endwhile; ?>
        <div class="mt-2 small text-muted">Module tương ứng chưa được xây dựng hoặc chưa được cấu hình.</div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="/university/login.php?logout=1" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
        </a>
        <a href="/university/index.php" class="btn btn-sm" style="background:#0d2d6b;color:#fff">
            <i class="bi bi-house me-1"></i>Trang chủ
        </a>
    </div>
</div>
</body>
</html>
