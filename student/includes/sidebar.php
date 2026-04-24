<nav id="sidebar">
    <div class="p-4 text-white fw-bold fs-5 border-bottom border-secondary mb-3 text-center">
        <i class="fas fa-graduation-cap me-2 text-primary"></i> SINH VIÊN
    </div>
    
    <?php
    // Xác định trang hiện tại để highlight menu
    $current_uri = $_SERVER['REQUEST_URI'];
    ?>

    <a href="/student/index.php" class="sidebar-link <?= (strpos($current_uri, 'student/index.php') !== false) ? 'active' : '' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    
    <a href="/student/modules/schedule/index.php" class="sidebar-link <?= (strpos($current_uri, 'schedule/') !== false) ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i> Lịch học
    </a>

    <a href="/student/modules/grades/index.php" class="sidebar-link <?= (strpos($current_uri, 'grades/') !== false) ? 'active' : '' ?>">
        <i class="fas fa-poll"></i> Bảng điểm
    </a>
    
    <a href="/student/modules/notifications/index.php" class="sidebar-link <?= (strpos($current_uri, 'notifications/') !== false) ? 'active' : '' ?>">
        <i class="fas fa-bell"></i> Thông báo
    </a>
    
    <a href="/logout.php" class="sidebar-link mt-5 text-danger border-top border-secondary pt-3">
        <i class="fas fa-sign-out-alt"></i> Đăng xuất
    </a>
</nav>

<style>
    :root {
        --primary-color: #4e73df;
        --sidebar-width: 250px;
    }
    #sidebar {
        width: var(--sidebar-width);
        min-height: 100vh;
        background: #222e3c;
        transition: all 0.3s;
        flex-shrink: 0;
    }
    .sidebar-link {
        padding: 12px 20px;
        color: #adb5bd;
        display: flex;
        align-items: center;
        text-decoration: none;
        transition: 0.2s;
    }
    .sidebar-link:hover,
    .sidebar-link.active {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border-left: 4px solid var(--primary-color);
    }
    .sidebar-link i {
        width: 25px;
    }
</style>