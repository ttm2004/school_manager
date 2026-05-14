<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="sidebar-brand-text">
            <div>Trường ĐH</div>
            <small>Thủ Dầu Một</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-title">Tổng quan</div>
        <a href="/university/admin/index.php" class="sidebar-link <?php echo $currentFile === 'index.php' && $currentDir === 'admin' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section-title">Quản trị hệ thống</div>
        <a href="/university/admin/users.php" class="sidebar-link <?php echo $currentFile === 'users.php' ? 'active' : ''; ?>">
            <i class="bi bi-people-fill"></i> Người dùng
        </a>

        <a href="/university/admin/access_stats.php" class="sidebar-link <?php echo $currentFile === 'access_stats.php' ? 'active' : ''; ?>">
            <i class="bi bi-broadcast-pin"></i> Th?ng k? truy c?p
        </a>

        <div class="sidebar-section-title">Danh mục hệ thống</div>
        <a href="/university/admin/faculties.php" class="sidebar-link <?php echo $currentFile === 'faculties.php' ? 'active' : ''; ?>">
            <i class="bi bi-building-fill"></i> Khoa/Viện
        </a>
        <a href="/university/admin/majors.php" class="sidebar-link <?php echo $currentFile === 'majors.php' ? 'active' : ''; ?>">
            <i class="bi bi-book-fill"></i> Ngành học
        </a>
        <a href="/university/admin/classes.php" class="sidebar-link <?php echo $currentFile === 'classes.php' ? 'active' : ''; ?>">
            <i class="bi bi-collection-fill"></i> Lớp hành chính
        </a>

        <div class="sidebar-section-title">Truyền thông</div>
        <a href="/university/admin/notifications.php" class="sidebar-link <?php echo $currentFile === 'notifications.php' ? 'active' : ''; ?>">
            <i class="bi bi-bell-fill"></i> Thông báo chung
        </a>
        <a href="/university/admin/contacts.php" class="sidebar-link <?php echo $currentFile === 'contacts.php' ? 'active' : ''; ?>">
            <i class="bi bi-chat-dots-fill"></i> Liên hệ
        </a>

        <div class="sidebar-section-title">Điều hướng module</div>
        <a href="/university/academic/index.php" class="sidebar-link">
            <i class="bi bi-calendar-check-fill"></i> Phòng Đào tạo
        </a>
        <a href="/university/admissions/index.php" class="sidebar-link">
            <i class="bi bi-mortarboard-fill"></i> Tuyển sinh
        </a>
        <a href="/university/finance/index.php" class="sidebar-link">
            <i class="bi bi-cash-coin"></i> Tài chính
        </a>
        <a href="/university/faculty/index.php" class="sidebar-link">
            <i class="bi bi-building-gear"></i> Khoa/Viện
        </a>
        <a href="/university/teacher/index.php" class="sidebar-link">
            <i class="bi bi-person-badge-fill"></i> Giảng viên
        </a>
        <a href="/university/student/index.php" class="sidebar-link">
            <i class="bi bi-person-vcard-fill"></i> Sinh viên
        </a>
        <a href="/university/hr/index.php" class="sidebar-link">
            <i class="bi bi-briefcase-fill"></i> Nhân sự
        </a>

        <div class="sidebar-section-title">Hệ thống</div>
        <a href="/university/index.php" class="sidebar-link" target="_blank">
            <i class="bi bi-globe"></i> Xem trang web
        </a>
        <a href="/university/login.php?logout=1" class="sidebar-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </nav>
</aside>
<?php include_once __DIR__ . '/../../includes/analytics_widget.php'; ?>
