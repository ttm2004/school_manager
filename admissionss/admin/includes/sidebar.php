<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-wrapper">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="logo-text">
                <h3>AdminPanel</h3>
                <p>Hệ thống tuyển sinh</p>
            </div>
        </div>
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="user-info">
        <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-details">
            <h4><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></h4>
            <p>Quản trị viên</p>
        </div>
    </div>
    
    <ul class="sidebar-menu">
        <li class="menu-title">MAIN MENU</li>
        <li>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <div class="icon-wrapper">
                    <i class="fas fa-home"></i>
                </div>
                <span class="menu-text">Dashboard</span>
                <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
                <span class="menu-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="registrations.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['registrations.php', 'registration-detail.php']) ? 'active' : ''; ?>">
                <div class="icon-wrapper">
                    <i class="fas fa-file-alt"></i>
                </div>
                <span class="menu-text">Quản lý hồ sơ</span>
                <?php if (isset($stats) && $stats['pending'] > 0): ?>
                <span class="badge pulse"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li>
            <a href="score-management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'score-management.php' ? 'active' : ''; ?>">
                <div class="icon-wrapper">
                    <i class="fas fa-star"></i>
                </div>
                <span class="menu-text">Quản lý điểm thi</span>
                <?php if (isset($pending_scores) && $pending_scores > 0): ?>
                <span class="badge warning"><?php echo $pending_scores; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li>
            <a href="majors.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'majors.php' ? 'active' : ''; ?>">
                <div class="icon-wrapper">
                    <i class="fas fa-book"></i>
                </div>
                <span class="menu-text">Ngành đào tạo</span>
            </a>
        </li>
        
        <li>
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <div class="icon-wrapper">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span class="menu-text">Báo cáo thống kê</span>
            </a>
        </li>
        
        <li>
            <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <div class="icon-wrapper">
                    <i class="fas fa-cog"></i>
                </div>
                <span class="menu-text">Cài đặt</span>
            </a>
        </li>
        
        <li class="menu-divider"></li>
        <li class="menu-title">EXTERNAL</li>
        
        <li>
            <a href="../index.php" target="_blank">
                <div class="icon-wrapper">
                    <i class="fas fa-globe"></i>
                </div>
                <span class="menu-text">Xem trang chủ</span>
            </a>
        </li>
        <li>
            <a href="logout.php" class="logout-link">
                <div class="icon-wrapper">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span class="menu-text">Đăng xuất</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="footer-content">
            <p>© 2024 Admin Panel</p>
            <p>Version 2.0</p>
        </div>
    </div>
</div>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
:root {
    --sidebar-width: 280px;
    --sidebar-width-collapsed: 80px;
    --header-height: 70px;
    --primary: #667eea;
    --secondary: #764ba2;
    --success: #4cc9f0;
    --warning: #f8961e;
    --danger: #f94144;
    --info: #36a2eb;
    --dark: #1a1a2e;
    --darker: #16213e;
    --light: #f8f9fa;
    --text-primary: #ffffff;
    --text-secondary: #a8b2d1;
    --text-muted: #6c757d;
    --border-color: rgba(255, 255, 255, 0.1);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --shadow-hover: 0 10px 30px rgba(102,126,234,0.3);
}

/* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: var(--sidebar-width);
    background: linear-gradient(180deg, var(--dark) 0%, var(--darker) 100%);
    color: var(--text-primary);
    overflow-y: auto;
    overflow-x: hidden;
    transition: transform 0.3s ease-in-out, width 0.3s ease;
    z-index: 1000;
    box-shadow: var(--shadow-lg);
    border-right: 1px solid var(--border-color);
}

/* Custom scrollbar */
.sidebar::-webkit-scrollbar {
    width: 3px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 3px;
}

/* Header styles */
.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    background: rgba(255, 255, 255, 0.02);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.logo-icon {
    min-width: 40px;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
    animation: float 3s ease-in-out infinite;
    box-shadow: var(--shadow-md);
}

.logo-text {
    overflow: hidden;
}

.logo-text h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    white-space: nowrap;
}

.logo-text p {
    margin: 3px 0 0;
    font-size: 11px;
    color: var(--text-secondary);
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.sidebar-close {
    display: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.sidebar-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

/* User info styles */
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.03);
    margin: 15px;
    border-radius: 10px;
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.user-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    background: rgba(255, 255, 255, 0.05);
}

.user-avatar {
    min-width: 40px;
    width: 40px;
    height: 40px;
    font-size: 40px;
    color: var(--text-secondary);
    animation: pulse 2s infinite;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-details {
    overflow: hidden;
}

.user-details h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: white;
    white-space: nowrap;
}

.user-details p {
    margin: 3px 0 0;
    font-size: 11px;
    color: var(--text-secondary);
    white-space: nowrap;
}

/* Menu styles */
.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.menu-title {
    padding: 15px 20px 5px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
    white-space: nowrap;
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    margin: 3px 10px;
    color: var(--text-secondary);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    white-space: nowrap;
}

.icon-wrapper {
    min-width: 32px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.03);
    transition: all 0.3s ease;
    font-size: 16px;
}

.menu-text {
    flex: 1;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
}

/* Hover effects */
.sidebar-menu li a:hover {
    color: white;
    transform: translateX(3px);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.sidebar-menu li a:hover .icon-wrapper {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    transform: rotate(5deg) scale(1.05);
}

/* Active state */
.sidebar-menu li a.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
}

.sidebar-menu li a.active .icon-wrapper {
    background: white;
    color: var(--primary);
}

.menu-dot {
    width: 5px;
    height: 5px;
    background: white;
    border-radius: 50%;
    margin-left: auto;
    animation: pulse 2s infinite;
}

/* Badge styles */
.sidebar-menu .badge {
    margin-left: auto;
    padding: 3px 6px;
    border-radius: 15px;
    font-size: 10px;
    font-weight: 600;
    min-width: 18px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.sidebar-menu .badge.warning {
    background: linear-gradient(135deg, var(--warning), #f3722c);
    color: white;
}

.sidebar-menu .badge.success {
    background: linear-gradient(135deg, var(--success), #4895ef);
    color: white;
}

.sidebar-menu .badge.info {
    background: linear-gradient(135deg, var(--info), #2a9d8f);
    color: white;
}

.sidebar-menu .badge.danger {
    background: linear-gradient(135deg, var(--danger), #f3722c);
    color: white;
}

.pulse {
    animation: pulse-badge 2s infinite;
}

/* Logout link special style */
.logout-link {
    background: rgba(249, 65, 68, 0.1);
}

.logout-link:hover {
    background: linear-gradient(135deg, var(--danger), #f3722c) !important;
    color: white !important;
}

/* Menu divider */
.menu-divider {
    height: 1px;
    background: var(--border-color);
    margin: 15px;
}

/* Footer styles */
.sidebar-footer {
    padding: 15px;
    text-align: center;
    border-top: 1px solid var(--border-color);
    margin-top: 15px;
}

.sidebar-footer .footer-content p {
    margin: 3px 0;
    font-size: 10px;
    color: var(--text-muted);
    white-space: nowrap;
}

/* Overlay for mobile */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(3px);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.show {
    display: block;
    opacity: 1;
}

/* Animations */
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes pulse-badge {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Desktop styles (min-width: 1200px) */
@media (min-width: 1200px) {
    .sidebar {
        transform: translateX(0) !important;
    }
    
    .sidebar-close {
        display: none !important;
    }
}

/* Tablet styles (768px - 1199px) */
@media (min-width: 768px) and (max-width: 1199px) {
    .sidebar {
        width: 250px;
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: flex;
    }
    
    .logo-icon {
        width: 35px;
        height: 35px;
        font-size: 18px;
    }
    
    .logo-text h3 {
        font-size: 16px;
    }
    
    .logo-text p {
        font-size: 10px;
    }
    
    .user-avatar {
        font-size: 35px;
        width: 35px;
        height: 35px;
    }
    
    .user-details h4 {
        font-size: 14px;
    }
    
    .user-details p {
        font-size: 10px;
    }
    
    .icon-wrapper {
        width: 30px;
        height: 30px;
        font-size: 14px;
        margin-right: 10px;
    }
    
    .menu-text {
        font-size: 12px;
    }
}

/* Mobile styles (max-width: 767px) */
@media (max-width: 767px) {
    .sidebar {
        transform: translateX(-100%);
        width: 85%;
        max-width: 300px;
        box-shadow: none;
        transition: transform 0.3s ease-in-out;
    }
    
    .sidebar.show {
        transform: translateX(0);
        box-shadow: var(--shadow-lg);
    }
    
    .sidebar-close {
        display: flex;
    }
    
    .sidebar-header {
        padding: 15px;
    }
    
    .logo-icon {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }
    
    .logo-text h3 {
        font-size: 15px;
    }
    
    .logo-text p {
        font-size: 9px;
    }
    
    .user-info {
        padding: 12px;
        margin: 10px;
    }
    
    .user-avatar {
        font-size: 32px;
        width: 32px;
        height: 32px;
    }
    
    .user-details h4 {
        font-size: 13px;
    }
    
    .user-details p {
        font-size: 9px;
    }
    
    .sidebar-menu li a {
        padding: 8px 12px;
        margin: 2px 8px;
    }
    
    .icon-wrapper {
        width: 28px;
        height: 28px;
        font-size: 13px;
        margin-right: 8px;
    }
    
    .menu-text {
        font-size: 12px;
    }
    
    .menu-title {
        padding: 10px 15px 4px;
        font-size: 9px;
    }
    
    .sidebar-footer {
        padding: 10px;
    }
}

/* Extra small (max-width: 480px) */
@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        max-width: 280px;
    }
    
    .logo-icon {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }
    
    .logo-text h3 {
        font-size: 13px;
    }
    
    .logo-text p {
        font-size: 8px;
    }
    
    .user-info {
        padding: 10px;
    }
    
    .user-avatar {
        font-size: 28px;
        width: 28px;
        height: 28px;
    }
    
    .user-details h4 {
        font-size: 12px;
    }
    
    .user-details p {
        font-size: 8px;
    }
    
    .sidebar-menu li a {
        padding: 6px 10px;
        margin: 1px 6px;
    }
    
    .icon-wrapper {
        width: 24px;
        height: 24px;
        font-size: 11px;
        margin-right: 6px;
    }
    
    .menu-text {
        font-size: 11px;
    }
    
    .sidebar-menu .badge {
        padding: 2px 5px;
        font-size: 8px;
        min-width: 15px;
    }
    
    .menu-title {
        font-size: 8px;
    }
}

/* Landscape mode */
@media (max-height: 600px) and (orientation: landscape) {
    .sidebar {
        overflow-y: auto;
    }
    
    .sidebar-header {
        padding: 10px;
    }
    
    .user-info {
        padding: 8px;
        margin: 8px;
    }
    
    .sidebar-menu li a {
        padding: 5px 10px;
    }
    
    .sidebar-footer {
        padding: 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarClose = document.getElementById('sidebarClose');
    
    // Hàm mở sidebar
    window.openSidebar = function() {
        if (window.innerWidth <= 1199) {
            sidebar.classList.add('show');
            if (overlay) overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    };
    
    // Hàm đóng sidebar
    window.closeSidebar = function() {
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
    };
    
    // Xử lý đóng sidebar
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeSidebar();
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Đóng sidebar khi resize từ mobile lên desktop
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1199) {
                closeSidebar();
            }
            
            // Cập nhật CSS variables cho responsive
            if (window.innerWidth <= 767) {
                document.documentElement.style.setProperty('--sidebar-width', '85%');
            } else if (window.innerWidth <= 1199) {
                document.documentElement.style.setProperty('--sidebar-width', '250px');
            } else {
                document.documentElement.style.setProperty('--sidebar-width', '280px');
            }
        }, 250);
    });
    
    // Xử lý phím ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
});

// Style cho nút collapse
const style = document.createElement('style');
style.textContent = `
    .sidebar-collapse-btn {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: white;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        margin: 10px auto 0;
        transition: all 0.3s ease;
    }
    
    .sidebar-collapse-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }
    
    @media (min-width: 1200px) {
        .sidebar-collapse-btn {
            display: flex;
        }
    }
`;
document.head.appendChild(style);
</script>