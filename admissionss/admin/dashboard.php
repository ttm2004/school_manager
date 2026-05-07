<?php
// session_start();
require_once '../php/config.php';
require_once 'includes/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Thống kê
$stats = [];

// Tổng số hồ sơ
$result = $conn->query("SELECT COUNT(*) as total FROM registrations");
$stats['total'] = $result->fetch_assoc()['total'];

// Hồ sơ chờ xử lý
$result = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'pending'");
$stats['pending'] = $result->fetch_assoc()['total'];

// Hồ sơ đã duyệt
$result = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'approved'");
$stats['approved'] = $result->fetch_assoc()['total'];

// Hồ sơ từ chối
$result = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'rejected'");
$stats['rejected'] = $result->fetch_assoc()['total'];

// Hồ sơ hôm nay
$result = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE DATE(created_at) = CURDATE()");
$stats['today'] = $result->fetch_assoc()['total'];

// Hồ sơ theo ngày (7 ngày gần nhất)
$daily_stats = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = $conn->query("SELECT COUNT(*) as count FROM registrations WHERE DATE(created_at) = '$date'");
    $daily_stats['labels'][] = date('d/m', strtotime($date));
    $daily_stats['data'][] = $result->fetch_assoc()['count'];
}

// Hồ sơ theo ngành
$major_stats = [];
$result = $conn->query("
    SELECT m.name, COUNT(r.id) as count 
    FROM majors m 
    LEFT JOIN registrations r ON r.major = m.id 
    GROUP BY m.id 
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $major_stats['labels'][] = $row['name'];
    $major_stats['data'][] = $row['count'];
}

// Hồ sơ gần đây
$recent = $conn->query("
    SELECT r.*, m.name as major_name 
    FROM registrations r 
    LEFT JOIN majors m ON r.major = m.id 
    ORDER BY r.created_at DESC 
    LIMIT 10
");

$page_title = "Dashboard";
require_once 'includes/header.php';
?>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Dashboard</h1>
        </div>
        
        <div class="nav-right">
            <div class="nav-item dropdown">
                <button class="nav-link" onclick="toggleDropdown('notificationDropdown')">
                    <i class="fas fa-bell"></i>
                    <?php if ($stats['pending'] > 0): ?>
                    <span class="badge"><?php echo $stats['pending']; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu" id="notificationDropdown">
                    <div class="dropdown-header">
                        <h4>Thông báo</h4>
                        <span class="badge bg-primary"><?php echo $stats['pending']; ?> mới</span>
                    </div>
                    <div class="dropdown-body">
                        <?php
                        $notifications = $conn->query("
                            SELECT r.*, m.name as major_name 
                            FROM registrations r 
                            LEFT JOIN majors m ON r.major = m.id 
                            WHERE r.status = 'pending' 
                            ORDER BY r.created_at DESC 
                            LIMIT 5
                        ");
                        
                        if ($notifications && $notifications->num_rows > 0):
                            while($row = $notifications->fetch_assoc()):
                        ?>
                        <a href="registration-detail.php?id=<?php echo $row['id']; ?>" class="dropdown-item">
                            <div class="item-icon pending">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title"><?php echo $row['fullname']; ?></div>
                                <div class="item-subtitle">Đăng ký ngành <?php echo $row['major_name']; ?></div>
                                <span class="item-time"><?php echo timeAgo($row['created_at']); ?></span>
                            </div>
                        </a>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bell-slash fa-3x mb-3" style="color: var(--gray); opacity: 0.5;"></i>
                            <p style="color: var(--gray);">Không có thông báo</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-footer">
                        <a href="registrations.php?status=pending">
                            <span>Xem tất cả</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="nav-item dropdown">
                <button class="nav-link" onclick="toggleDropdown('userDropdown')">
                    <i class="fas fa-user-circle"></i>
                </button>
                <div class="dropdown-menu" id="userDropdown">
                    <div class="user-info-dropdown">
                        <div class="user-avatar large">
                            <?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <h4><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></h4>
                            <p><?php echo $_SESSION['admin_email'] ?? 'admin@example.com'; ?></p>
                        </div>
                    </div>
                    <div class="dropdown-body">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user" style="width: 20px;"></i>
                            <span>Hồ sơ</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog" style="width: 20px;"></i>
                            <span>Cài đặt</span>
                        </a>
                    </div>
                    <div class="dropdown-footer">
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Đăng xuất</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h2>Xin chào, <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?>! 👋</h2>
                <p>Đây là tổng quan về hệ thống tuyển sinh của bạn hôm nay</p>
            </div>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d/m/Y'); ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card total" onclick="window.location.href='registrations.php'">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p>Tổng hồ sơ</p>
                </div>
                <div class="stat-footer">
                    <span>Xem chi tiết</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="stat-bg-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>

            <div class="stat-card pending" onclick="window.location.href='registrations.php?status=pending'">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending']); ?></h3>
                    <p>Chờ xử lý</p>
                </div>
                <div class="stat-footer">
                    <span>Xem chi tiết</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="stat-bg-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>

            <div class="stat-card approved" onclick="window.location.href='registrations.php?status=approved'">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['approved']); ?></h3>
                    <p>Đã duyệt</p>
                </div>
                <div class="stat-footer">
                    <span>Xem chi tiết</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="stat-bg-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <div class="stat-card rejected" onclick="window.location.href='registrations.php?status=rejected'">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['rejected']); ?></h3>
                    <p>Từ chối</p>
                </div>
                <div class="stat-footer">
                    <span>Xem chi tiết</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="stat-bg-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>

            <div class="stat-card today" onclick="window.location.href='registrations.php?date=today'">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['today']); ?></h3>
                    <p>Hôm nay</p>
                </div>
                <div class="stat-footer">
                    <span>Xem chi tiết</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                <div class="stat-bg-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h4>
                            <i class="fas fa-chart-line"></i>
                            Thống kê hồ sơ 7 ngày qua
                        </h4>
                        <p class="chart-subtitle">Biến động số lượng hồ sơ theo ngày</p>
                    </div>
                    <button class="btn-icon" onclick="refreshChart()" title="Làm mới dữ liệu">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="chart-body">
                    <canvas id="registrationsChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h4>
                            <i class="fas fa-chart-pie"></i>
                            Phân bố theo ngành
                        </h4>
                        <p class="chart-subtitle">Top 5 ngành có nhiều hồ sơ nhất</p>
                    </div>
                </div>
                <div class="chart-body">
                    <canvas id="majorsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Registrations -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <h4>
                        <i class="fas fa-history"></i>
                        Hồ sơ mới nhất
                    </h4>
                    <p class="table-subtitle"><?php echo $recent->num_rows; ?> hồ sơ gần đây nhất</p>
                </div>
                <a href="registrations.php" class="btn-view-all">
                    <span>Xem tất cả</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã HS</th>
                            <th>Thông tin</th>
                            <th>Ngành</th>
                            <th>Ngày đăng ký</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent && $recent->num_rows > 0): ?>
                            <?php while ($row = $recent->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="registration-id">
                                        #<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="user-info-cell">
                                        <div class="user-avatar small" style="background: linear-gradient(135deg, <?php echo $row['status'] == 'pending' ? '#f8961e' : ($row['status'] == 'approved' ? '#4cc9f0' : '#f94144'); ?>, <?php echo $row['status'] == 'pending' ? '#f5576c' : ($row['status'] == 'approved' ? '#4895ef' : '#f3722c'); ?>)">
                                            <?php echo strtoupper(substr($row['fullname'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <strong><?php echo $row['fullname']; ?></strong>
                                            <small>
                                                <i class="fas fa-phone"></i> <?php echo $row['phone']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope"></i> <?php echo $row['email']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="major-name">
                                        <i class="fas fa-graduation-cap"></i>
                                        <?php echo $row['major_name']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-info">
                                        <span><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></span>
                                        <small>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('H:i', strtotime($row['created_at'])); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $status_text = [
                                        'pending' => 'Chờ duyệt',
                                        'approved' => 'Đã duyệt',
                                        'rejected' => 'Từ chối'
                                    ];
                                    $status_icons = [
                                        'pending' => 'clock',
                                        'approved' => 'check-circle',
                                        'rejected' => 'times-circle'
                                    ];
                                    ?>
                                    <span class="status-badge <?php echo $status_class[$row['status']]; ?>">
                                        <i class="fas fa-<?php echo $status_icons[$row['status']]; ?>"></i>
                                        <?php echo $status_text[$row['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="registration-detail.php?id=<?php echo $row['id']; ?>" 
                                           class="btn-action view" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($row['status'] == 'pending'): ?>
                                        <button class="btn-action approve" title="Duyệt hồ sơ" 
                                                onclick="approveRegistration(<?php echo $row['id']; ?>, '<?php echo $row['fullname']; ?>')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn-action reject" title="Từ chối" 
                                                onclick="rejectRegistration(<?php echo $row['id']; ?>, '<?php echo $row['fullname']; ?>')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox fa-4x mb-3"></i>
                                        <h5>Chưa có hồ sơ nào</h5>
                                        <p>Hiện tại chưa có hồ sơ đăng ký nào trong hệ thống</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart data
const registrationsData = <?php echo json_encode($daily_stats); ?>;
const majorsData = <?php echo json_encode($major_stats); ?>;

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initLineChart();
    initPieChart();
});

function initLineChart() {
    const ctx = document.getElementById('registrationsChart').getContext('2d');
    
    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(102, 126, 234, 0.4)');
    gradient.addColorStop(1, 'rgba(102, 126, 234, 0.0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: registrationsData.labels,
            datasets: [{
                label: 'Số hồ sơ',
                data: registrationsData.data,
                borderColor: '#667eea',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#667eea',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1a1a2e',
                    titleColor: '#fff',
                    bodyColor: '#a8b2d1',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return `Số hồ sơ: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1,
                        color: '#6c757d',
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#6c757d',
                        font: {
                            size: 11
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

function initPieChart() {
    const ctx = document.getElementById('majorsChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: majorsData.labels,
            datasets: [{
                data: majorsData.data,
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#f093fb',
                    '#f5576c',
                    '#4facfe'
                ],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#6c757d',
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: '#1a1a2e',
                    titleColor: '#fff',
                    bodyColor: '#a8b2d1',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.raw / total) * 100).toFixed(1);
                            return `${context.label}: ${context.raw} hồ sơ (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '70%',
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    });
}

// Toggle dropdown
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const isOpen = dropdown.classList.contains('show');
    
    document.querySelectorAll('.dropdown-menu').forEach(d => {
        d.classList.remove('show');
    });
    
    if (!isOpen) {
        dropdown.classList.add('show');
        
        setTimeout(() => {
            document.addEventListener('click', function closeDropdown(e) {
                if (!dropdown.contains(e.target) && !e.target.closest('.nav-link')) {
                    dropdown.classList.remove('show');
                    document.removeEventListener('click', closeDropdown);
                }
            });
        }, 100);
    }
}

// Time ago function
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    const intervals = [
        { label: 'năm', seconds: 31536000 },
        { label: 'tháng', seconds: 2592000 },
        { label: 'tuần', seconds: 604800 },
        { label: 'ngày', seconds: 86400 },
        { label: 'giờ', seconds: 3600 },
        { label: 'phút', seconds: 60 }
    ];
    
    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count >= 1) {
            return count + ' ' + interval.label + ' trước';
        }
    }
    
    return 'vừa xong';
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

// Approve registration with confirmation
function approveRegistration(id, name) {
    showConfirmDialog(
        'Xác nhận duyệt hồ sơ',
        `Bạn có chắc chắn muốn duyệt hồ sơ của <strong>${name}</strong>?`,
        function() {
            showLoading('Đang duyệt hồ sơ...');
            
            // Simulate API call
            setTimeout(() => {
                hideLoading();
                showNotification('success', `Đã duyệt hồ sơ của ${name} thành công!`);
                setTimeout(() => location.reload(), 1500);
            }, 1000);
        }
    );
}

// Reject registration with confirmation
function rejectRegistration(id, name) {
    showConfirmDialog(
        'Xác nhận từ chối hồ sơ',
        `Bạn có chắc chắn muốn từ chối hồ sơ của <strong>${name}</strong>?`,
        function() {
            showLoading('Đang từ chối hồ sơ...');
            
            // Simulate API call
            setTimeout(() => {
                hideLoading();
                showNotification('error', `Đã từ chối hồ sơ của ${name}!`);
                setTimeout(() => location.reload(), 1500);
            }, 1000);
        }
    );
}

// Refresh chart
function refreshChart() {
    showLoading('Đang làm mới dữ liệu...');
    setTimeout(() => {
        hideLoading();
        showNotification('info', 'Dữ liệu đã được cập nhật!');
        location.reload();
    }, 800);
}

// Show confirm dialog
function showConfirmDialog(title, message, onConfirm) {
    const dialog = document.createElement('div');
    dialog.className = 'confirm-dialog';
    dialog.innerHTML = `
        <div class="confirm-content">
            <div class="confirm-header">
                <i class="fas fa-question-circle"></i>
                <h3>${title}</h3>
            </div>
            <div class="confirm-body">
                <p>${message}</p>
            </div>
            <div class="confirm-footer">
                <button class="btn-cancel">Hủy bỏ</button>
                <button class="btn-confirm">Xác nhận</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    
    const cancelBtn = dialog.querySelector('.btn-cancel');
    const confirmBtn = dialog.querySelector('.btn-confirm');
    
    cancelBtn.addEventListener('click', () => dialog.remove());
    confirmBtn.addEventListener('click', () => {
        dialog.remove();
        if (onConfirm) onConfirm();
    });
}

// Show loading
function showLoading(message = 'Đang xử lý...') {
    const loader = document.createElement('div');
    loader.className = 'loading-overlay';
    loader.id = 'loadingOverlay';
    loader.innerHTML = `
        <div class="loading-spinner"></div>
        <p>${message}</p>
    `;
    document.body.appendChild(loader);
}

// Hide loading
function hideLoading() {
    const loader = document.getElementById('loadingOverlay');
    if (loader) {
        loader.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => loader.remove(), 300);
    }
}

// Show notification
function showNotification(type, message) {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colors = {
        success: '#4cc9f0',
        error: '#f94144',
        warning: '#f8961e',
        info: '#4361ee'
    };
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas ${icons[type]}" style="color: ${colors[type]};"></i>
        <span>${message}</span>
        <button class="notification-close"><i class="fas fa-times"></i></button>
    `;
    
    document.body.appendChild(notification);
    
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    });
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Scroll to top button
window.addEventListener('scroll', function() {
    const mainContent = document.querySelector('.main-content');
    const scrollTop = mainContent.scrollTop;
    let scrollBtn = document.getElementById('scrollTopBtn');
    
    if (scrollTop > 400 && !scrollBtn) {
        const btn = document.createElement('button');
        btn.id = 'scrollTopBtn';
        btn.className = 'scroll-top-btn';
        btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        btn.onclick = function() {
            mainContent.scrollTo({ top: 0, behavior: 'smooth' });
        };
        document.body.appendChild(btn);
    } else if (scrollTop <= 400 && scrollBtn) {
        scrollBtn.remove();
    }
});
</script>