<?php
// session_start();
require_once '../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Lọc theo trạng thái
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Xử lý phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Xây dựng query
$where = [];
$params = [];
$types = "";

if ($status_filter != 'all') {
    $where[] = "r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where[] = "(r.fullname LIKE ? OR r.phone LIKE ? OR r.email LIKE ? OR r.identification LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= "ssss";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Đếm tổng số
$count_sql = "SELECT COUNT(*) as total FROM registrations r $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Lấy dữ liệu
$sql = "SELECT r.*, m.name as major_name 
        FROM registrations r 
        LEFT JOIN majors m ON r.major = m.id 
        $where_clause 
        ORDER BY r.created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$registrations = $stmt->get_result();

// Thống kê cho stats
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM registrations");
$stats['total'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'pending'");
$stats['pending'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'approved'");
$stats['approved'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE status = 'rejected'");
$stats['rejected'] = $result->fetch_assoc()['total'];

$page_title = "Quản lý hồ sơ";
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
            <h1 class="page-title">Quản lý hồ sơ</h1>
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
        <!-- Filter Bar -->
        <div class="table-card" style="margin-bottom: 25px;">
            <div class="filter-bar">
                <div class="filter-tabs">
                    <a href="?status=all" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i>
                        <span>Tất cả</span>
                        <span class="badge"><?php echo $stats['total']; ?></span>
                    </a>
                    <a href="?status=pending" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i>
                        <span>Chờ duyệt</span>
                        <span class="badge warning"><?php echo $stats['pending']; ?></span>
                    </a>
                    <a href="?status=approved" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Đã duyệt</span>
                        <span class="badge success"><?php echo $stats['approved']; ?></span>
                    </a>
                    <a href="?status=rejected" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle"></i>
                        <span>Từ chối</span>
                        <span class="badge danger"><?php echo $stats['rejected']; ?></span>
                    </a>
                </div>
                
                <div class="filter-actions">
                    <form method="GET" class="search-form">
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Tìm kiếm hồ sơ..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <button class="btn-export" onclick="exportData()">
                        <i class="fas fa-file-excel"></i>
                        <span>Xuất Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Registrations Table -->
        <div class="table-card">
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
                        <?php if ($registrations && $registrations->num_rows > 0): ?>
                            <?php while ($row = $registrations->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="registration-id">
                                        #<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="user-info-cell">
                                        <div class="user-avatar small">
                                            <?php echo strtoupper(substr($row['fullname'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <strong><?php echo $row['fullname']; ?></strong>
                                            <small>
                                                <i class="fas fa-phone"></i> <?php echo $row['phone']; ?> | 
                                                <i class="fas fa-envelope"></i> <?php echo $row['email']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-id-card"></i> <?php echo $row['identification']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="major-name"><?php echo $row['major_name']; ?></span>
                                </td>
                                <td>
                                    <div class="date-info">
                                        <span><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></span>
                                        <small><?php echo date('H:i', strtotime($row['created_at'])); ?></small>
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
                                                    onclick="processRegistration(<?php echo $row['id']; ?>, 'approve')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn-action reject" title="Từ chối" 
                                                    onclick="processRegistration(<?php echo $row['id']; ?>, 'reject')">
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
                                        <h5>Không có hồ sơ nào</h5>
                                        <p>Hiện tại chưa có hồ sơ nào trong danh sách</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Hiển thị <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_records); ?> trên tổng số <?php echo $total_records; ?> hồ sơ
                </div>
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=1">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $total_pages; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>
</div>

<style>
/* Filter Bar Styles */
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.filter-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: #f8f9fa;
    border-radius: 10px;
    color: var(--gray);
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.filter-tab i {
    font-size: 16px;
}

.filter-tab span {
    font-size: 13px;
    font-weight: 500;
}

.filter-tab .badge {
    padding: 3px 8px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    background: var(--gray);
    color: white;
}

.filter-tab .badge.warning {
    background: var(--warning);
}

.filter-tab .badge.success {
    background: var(--success);
}

.filter-tab .badge.danger {
    background: var(--danger);
}

.filter-tab:hover {
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
    color: var(--primary);
    transform: translateY(-2px);
    border-color: var(--primary);
}

.filter-tab.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.filter-tab.active .badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.filter-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.search-form {
    min-width: 250px;
}

.search-box {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    overflow: hidden;
    transition: all 0.3s ease;
}

.search-box:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.search-box input {
    flex: 1;
    padding: 10px 15px;
    border: none;
    background: transparent;
    font-size: 13px;
    outline: none;
}

.search-box button {
    padding: 10px 15px;
    background: transparent;
    border: none;
    color: var(--gray);
    cursor: pointer;
    transition: all 0.3s ease;
}

.search-box button:hover {
    color: var(--primary);
}

.btn-export {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(40, 167, 69, 0.2);
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
}

/* Registration ID */
.registration-id {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: var(--primary);
    background: rgba(102, 126, 234, 0.1);
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 12px;
}

/* Major Name */
.major-name {
    font-weight: 500;
    color: var(--dark);
    padding: 5px 10px;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 13px;
    display: inline-block;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px;
}

.empty-state i {
    color: var(--gray);
    opacity: 0.3;
}

.empty-state h5 {
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--gray);
    font-size: 14px;
}

/* Pagination Wrapper */
.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.pagination-info {
    color: var(--gray);
    font-size: 13px;
}

.pagination {
    display: flex;
    gap: 5px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.page-item {
    margin: 0;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 35px;
    height: 35px;
    padding: 0 5px;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    color: var(--gray);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.page-link:hover {
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
    color: var(--primary);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-color: transparent;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
}

.page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .search-form {
        width: 100%;
        min-width: auto;
    }
    
    .btn-export {
        width: 100%;
        justify-content: center;
    }
    
    .pagination-wrapper {
        flex-direction: column;
        text-align: center;
    }
    
    .pagination {
        justify-content: center;
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .filter-tab {
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .filter-tab span:not(.badge) {
        display: none;
    }
    
    .filter-tab i {
        font-size: 18px;
    }
    
    .filter-tab .badge {
        padding: 2px 6px;
        font-size: 10px;
    }
    
    .registration-id {
        font-size: 11px;
        padding: 4px 6px;
    }
    
    .user-details small {
        font-size: 10px;
    }
    
    .major-name {
        font-size: 11px;
        padding: 4px 8px;
    }
    
    .status-badge {
        padding: 4px 8px;
        font-size: 10px;
    }
    
    .page-link {
        min-width: 32px;
        height: 32px;
        font-size: 12px;
    }
}
</style>

<script>
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

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = Math.floor(seconds / 86400);
    if (interval > 7) return formatDate(dateString);
    if (interval >= 1) return interval + ' ngày trước';
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) return interval + ' giờ trước';
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) return interval + ' phút trước';
    
    return 'vừa xong';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.getDate().toString().padStart(2, '0') + '/' + 
           (date.getMonth() + 1).toString().padStart(2, '0') + '/' + 
           date.getFullYear();
}

function processRegistration(id, action) {
    const actionText = action == 'approve' ? 'duyệt' : 'từ chối';
    
    if (confirm('Bạn có chắc chắn muốn ' + actionText + ' hồ sơ này?')) {
        showLoading();
        
        // Simulate API call
        setTimeout(() => {
            hideLoading();
            alert((action == 'approve' ? 'Duyệt' : 'Từ chối') + ' hồ sơ thành công!');
            location.reload();
        }, 1000);
    }
}

function exportData() {
    showLoading();
    setTimeout(() => {
        window.location.href = 'export.php?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>';
        hideLoading();
    }, 500);
}

function showLoading() {
    const loader = document.createElement('div');
    loader.className = 'loading-spinner';
    loader.id = 'loadingSpinner';
    loader.innerHTML = '<div class="spinner"></div><p>Đang xử lý...</p>';
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.getElementById('loadingSpinner');
    if (loader) {
        loader.remove();
    }
}

// Add loading spinner styles
const style = document.createElement('style');
style.textContent = `
    .loading-spinner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 99999;
    }
    
    .loading-spinner .spinner {
        width: 50px;
        height: 50px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }
    
    .loading-spinner p {
        color: var(--dark);
        font-weight: 500;
        font-size: 16px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

<style>
/* Thêm vào cuối phần style hiện có */

/* Đảm bảo content wrapper có scroll khi cần */
.content-wrapper {
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
}

/* Main content có scroll riêng */
.main-content {
    flex: 1;
    padding: 20px 30px;
    background: #f8f9fa;
    overflow-y: auto;
    overflow-x: hidden;
    max-height: calc(100vh - var(--header-height) - 70px); /* Trừ header và footer */
}

/* Custom scrollbar cho main content */
.main-content::-webkit-scrollbar {
    width: 6px;
}

.main-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.main-content::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
}

.main-content::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
}

/* Điều chỉnh responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 15px;
        max-height: calc(100vh - var(--header-height) - 60px);
    }
    
    /* Filter bar scroll ngang nếu cần */
    .filter-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 10px;
        margin-bottom: -10px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    
    .filter-tabs::-webkit-scrollbar {
        height: 3px;
    }
    
    .filter-tabs::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .filter-tabs::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 3px;
    }
    
    .filter-tab {
        flex-shrink: 0;
    }
    
    /* Table container scroll */
    .table-responsive {
        border-radius: 10px;
        margin: 0 -5px;
        padding: 0 5px;
    }
    
    /* Đảm bảo table không bị che */
    .data-table {
        min-width: 800px; /* Cho phép scroll ngang */
    }
    
    /* Action buttons gọn lại */
    .action-buttons {
        flex-wrap: nowrap;
    }
    
    .btn-action {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .main-content {
        padding: 10px;
        max-height: calc(100vh - var(--header-height) - 50px);
    }
    
    /* Filter tabs cuộn ngang */
    .filter-tabs {
        gap: 5px;
    }
    
    .filter-tab {
        padding: 6px 10px;
    }
    
    /* Table cells điều chỉnh */
    .data-table td, 
    .data-table th {
        padding: 10px 8px;
    }
    
    /* User info gọn lại */
    .user-info-cell {
        gap: 8px;
    }
    
    .user-avatar.small {
        width: 30px;
        height: 30px;
        font-size: 14px;
    }
    
    .user-details strong {
        font-size: 12px;
    }
    
    .user-details small {
        font-size: 9px;
    }
    
    /* Major name */
    .major-name {
        font-size: 10px;
        padding: 3px 6px;
    }
    
    /* Status badge */
    .status-badge {
        padding: 3px 6px;
        font-size: 9px;
    }
    
    .status-badge i {
        font-size: 8px;
    }
}

/* Landscape mode */
@media (max-height: 600px) and (orientation: landscape) {
    .main-content {
        max-height: calc(100vh - var(--header-height) - 40px);
    }
    
    .table-card {
        margin-bottom: 15px;
    }
    
    .data-table td,
    .data-table th {
        padding: 8px;
    }
}

/* Đảm bảo footer luôn ở dưới */
.admin-footer {
    margin-top: auto;
    background: white;
    padding: 15px 30px;
    border-top: 1px solid rgba(0,0,0,0.05);
    flex-shrink: 0;
}

/* Loading spinner position fix */
.loading-spinner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999;
}

/* Dropdown menu position fix trên mobile */
@media (max-width: 768px) {
    .dropdown-menu {
        position: fixed;
        top: var(--header-height);
        left: 10px;
        right: 10px;
        width: auto;
        max-width: none;
        max-height: calc(100vh - var(--header-height) - 20px);
        overflow-y: auto;
    }
    
    .dropdown-body {
        max-height: 300px;
    }
}

/* Smooth scrolling */
.main-content {
    scroll-behavior: smooth;
}

/* Fix cho iOS */
@supports (-webkit-overflow-scrolling: touch) {
    .main-content,
    .filter-tabs,
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }
}

/* Animation cho scroll reveal */
.table-card {
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Pagination cố định */
.pagination-wrapper {
    position: sticky;
    bottom: 0;
    background: white;
    z-index: 10;
    border-radius: 0 0 20px 20px;
}

/* Empty state căn giữa */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
}
</style>

<script>
// Thêm vào cuối file, sau các function hiện có

// Auto scroll to top khi chuyển trang
document.addEventListener('DOMContentLoaded', function() {
    // Scroll to top khi load trang
    window.scrollTo(0, 0);
    
    // Xử lý scroll cho filter tabs
    const filterTabs = document.querySelector('.filter-tabs');
    if (filterTabs) {
        let isDown = false;
        let startX;
        let scrollLeft;

        filterTabs.addEventListener('mousedown', (e) => {
            isDown = true;
            filterTabs.classList.add('active');
            startX = e.pageX - filterTabs.offsetLeft;
            scrollLeft = filterTabs.scrollLeft;
        });

        filterTabs.addEventListener('mouseleave', () => {
            isDown = false;
            filterTabs.classList.remove('active');
        });

        filterTabs.addEventListener('mouseup', () => {
            isDown = false;
            filterTabs.classList.remove('active');
        });

        filterTabs.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - filterTabs.offsetLeft;
            const walk = (x - startX) * 2;
            filterTabs.scrollLeft = scrollLeft - walk;
        });
    }
});

// Scroll to top function
function scrollToTop() {
    document.querySelector('.main-content').scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Thêm nút scroll to top khi cần
window.addEventListener('scroll', function() {
    const scrollButton = document.getElementById('scrollTopBtn');
    const mainContent = document.querySelector('.main-content');
    
    if (!scrollButton && mainContent.scrollTop > 300) {
        const btn = document.createElement('button');
        btn.id = 'scrollTopBtn';
        btn.className = 'scroll-top-btn';
        btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        btn.onclick = scrollToTop;
        document.body.appendChild(btn);
        
        // Style cho nút
        const style = document.createElement('style');
        style.textContent = `
            .scroll-top-btn {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 45px;
                height: 45px;
                border-radius: 12px;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                transition: all 0.3s ease;
                z-index: 999;
                animation: fadeIn 0.3s ease;
            }
            
            .scroll-top-btn:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media (max-width: 768px) {
                .scroll-top-btn {
                    bottom: 20px;
                    right: 20px;
                    width: 40px;
                    height: 40px;
                    font-size: 16px;
                }
            }
        `;
        document.head.appendChild(style);
    } else if (scrollButton && mainContent.scrollTop <= 300) {
        scrollButton.remove();
    }
});

// Cập nhật processRegistration function để có loading đẹp hơn
function processRegistration(id, action) {
    const actionText = action == 'approve' ? 'duyệt' : 'từ chối';
    
    // Custom confirm
    showConfirmDialog(
        'Xác nhận ' + actionText,
        'Bạn có chắc chắn muốn ' + actionText + ' hồ sơ này?',
        function() {
            showLoading();
            
            // Simulate API call
            setTimeout(() => {
                hideLoading();
                showNotification('success', (action == 'approve' ? 'Duyệt' : 'Từ chối') + ' hồ sơ thành công!');
                setTimeout(() => location.reload(), 1500);
            }, 1000);
        }
    );
}

// Custom confirm dialog
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
                <button class="btn-cancel">Hủy</button>
                <button class="btn-confirm">Đồng ý</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    
    // Style cho dialog
    const style = document.createElement('style');
    style.textContent = `
        .confirm-dialog {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100000;
            animation: fadeIn 0.3s ease;
        }
        
        .confirm-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }
        
        .confirm-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--warning), #f3722c);
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .confirm-header i {
            font-size: 24px;
        }
        
        .confirm-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .confirm-body {
            padding: 30px 20px;
            text-align: center;
        }
        
        .confirm-body p {
            margin: 0;
            color: var(--dark);
            font-size: 15px;
        }
        
        .confirm-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-cancel {
            padding: 10px 25px;
            border-radius: 10px;
            border: 1px solid #ddd;
            background: white;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #f8f9fa;
        }
        
        .btn-confirm {
            padding: 10px 25px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
    
    // Xử lý sự kiện
    const cancelBtn = dialog.querySelector('.btn-cancel');
    const confirmBtn = dialog.querySelector('.btn-confirm');
    
    cancelBtn.addEventListener('click', () => dialog.remove());
    confirmBtn.addEventListener('click', () => {
        dialog.remove();
        if (onConfirm) onConfirm();
    });
}

// Notification function
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Style cho notification
    const style = document.createElement('style');
    style.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 100000;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid;
        }
        
        .notification.success {
            border-left-color: var(--success);
        }
        
        .notification.success i {
            color: var(--success);
        }
        
        .notification i {
            font-size: 20px;
        }
        
        .notification span {
            color: var(--dark);
            font-size: 14px;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    `;
    document.head.appendChild(style);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>