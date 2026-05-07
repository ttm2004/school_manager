<?php
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon" style="background:linear-gradient(135deg,#f5a623,#d4891a);">
            <i class="bi bi-mortarboard-fill" style="color:#0d2d6b;"></i>
        </div>
        <div class="sidebar-brand-text">
            <div>Ban Tuyển sinh</div>
            <small style="color:#f5a623;">TDMU Admissions</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-title">Tổng quan</div>
        <a href="/university/admissions/index.php" class="sidebar-link <?php echo $currentFile=='index.php'?'active':''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section-title">Hồ sơ xét tuyển</div>
        <a href="/university/admissions/applications.php" class="sidebar-link <?php echo $currentFile=='applications.php'?'active':''; ?>">
            <i class="bi bi-file-earmark-person-fill"></i> Danh sách hồ sơ
        </a>
        <a href="/university/admissions/auto_review.php" class="sidebar-link <?php echo $currentFile=='auto_review.php'?'active':''; ?>">
            <i class="bi bi-robot"></i> Xét tuyển tự động
        </a>

        <div class="sidebar-section-title">Nhập học</div>
        <a href="/university/admissions/enrollment.php" class="sidebar-link <?php echo $currentFile=='enrollment.php'?'active':''; ?>">
            <i class="bi bi-person-check-fill"></i> Thủ tục nhập học
        </a>
        <?php if (hasRole('admissions_manager') || $_SESSION['role']==='admin'): ?>
        <a href="/university/admissions/auto_assign.php" class="sidebar-link <?php echo $currentFile=='auto_assign.php'?'active':''; ?>">
            <i class="bi bi-diagram-3-fill"></i> Phân lớp tự động
        </a>
        <?php endif; ?>

        <div class="sidebar-section-title">Cấu hình</div>
        <a href="/university/admissions/methods.php" class="sidebar-link <?php echo $currentFile=='methods.php'?'active':''; ?>">
            <i class="bi bi-list-check"></i> Phương thức xét tuyển
        </a>
        <a href="/university/admissions/news.php" class="sidebar-link <?php echo $currentFile=='news.php'?'active':''; ?>">
            <i class="bi bi-newspaper"></i> Tin tuyển sinh
        </a>

        <div class="sidebar-section-title">Hệ thống</div>
        <a href="/university/admin/index.php" class="sidebar-link">
            <i class="bi bi-arrow-left-circle-fill"></i> Về Admin
        </a>
        <a href="/university/index.php" class="sidebar-link" target="_blank">
            <i class="bi bi-globe"></i> Xem trang web
        </a>
        <a href="/university/login.php?logout=1" class="sidebar-link" style="color:#ff6b6b;">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </nav>
</aside>
