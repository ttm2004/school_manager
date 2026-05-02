<?php
// session_start();
require_once '../php/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Cập nhật cài đặt
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'change_password') {
            $current = $_POST['current_password'];
            $new = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];
            
            // Kiểm tra mật khẩu hiện tại
            $sql = "SELECT password FROM admin_users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_SESSION['admin_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (password_verify($current, $user['password'])) {
                if ($new == $confirm) {
                    $hash = password_hash($new, PASSWORD_DEFAULT);
                    $conn->query("UPDATE admin_users SET password = '$hash' WHERE id = " . $_SESSION['admin_id']);
                    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>Đổi mật khẩu thành công!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
                } else {
                    $error = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>Mật khẩu mới không khớp!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
                }
            } else {
                $error = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>Mật khẩu hiện tại không đúng!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
            }
        } elseif ($_POST['action'] == 'add_user') {
            $username = sanitize($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            
            $check = $conn->query("SELECT id FROM admin_users WHERE username = '$username'");
            if ($check->num_rows > 0) {
                $error = '<div class="alert alert-danger">Tên đăng nhập đã tồn tại!</div>';
            } else {
                $conn->query("INSERT INTO admin_users (username, password, email, role) VALUES ('$username', '$password', '$email', '$role')");
                $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>Thêm người dùng thành công!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
            }
        } elseif ($_POST['action'] == 'delete_user') {
            $user_id = intval($_POST['user_id']);
            if ($user_id != $_SESSION['admin_id']) {
                $conn->query("DELETE FROM admin_users WHERE id = $user_id");
                $message = '<div class="alert alert-success">Đã xóa người dùng!</div>';
            } else {
                $error = '<div class="alert alert-danger">Không thể xóa chính mình!</div>';
            }
        }
    }
}

// Lấy danh sách admin
$admins = $conn->query("SELECT * FROM admin_users ORDER BY id");

$page_title = "Cài đặt hệ thống";
require_once 'includes/header.php';
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e, #38ef7d);
    --danger-gradient: linear-gradient(135deg, #eb3349, #f45c43);
    --warning-gradient: linear-gradient(135deg, #f2994a, #f2c94c);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
}

/* Settings Page Styles */
.settings-header {
    background: var(--primary-gradient);
    border-radius: 20px;
    padding: 25px 30px;
    margin-bottom: 30px;
    color: white;
}

.settings-header h1 {
    font-size: 1.8rem;
    margin-bottom: 10px;
    font-weight: 600;
}

.settings-header p {
    margin: 0;
    opacity: 0.9;
}

.settings-card {
    background: white;
    border-radius: 20px;
    border: none;
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.settings-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.settings-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 20px 25px;
}

.settings-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
}

.settings-card .card-header h5 i {
    width: 35px;
    height: 35px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-gradient);
    border-radius: 10px;
    color: white;
    margin-right: 12px;
}

.settings-card .card-body {
    padding: 25px;
}

.form-control, .form-select {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 15px;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
}

.btn-gradient {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-gradient-success {
    background: var(--success-gradient);
}

.btn-gradient-danger {
    background: var(--danger-gradient);
}

/* Table Styles */
.table-modern {
    border-radius: 16px;
    overflow: hidden;
}

.table-modern thead th {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 2px solid #e2e8f0;
    padding: 15px;
    font-weight: 600;
    color: #2d3748;
}

.table-modern tbody tr {
    transition: all 0.3s;
}

.table-modern tbody tr:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.badge-admin {
    background: var(--primary-gradient);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-moderator {
    background: var(--warning-gradient);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-icon:hover {
    transform: scale(1.1);
}

/* Info Card */
.info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 25px;
    color: white;
}

.info-card h5 {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-card .info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.info-card .info-row:last-child {
    border-bottom: none;
}

.info-card .info-label {
    font-weight: 500;
    opacity: 0.9;
}

.info-card .info-value {
    font-weight: 600;
}

/* Modal */
.modal-modern .modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: var(--shadow-xl);
}

.modal-modern .modal-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: 20px 20px 0 0;
    padding: 20px 25px;
}

.modal-modern .modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-modern .modal-body {
    padding: 25px;
}

.modal-modern .modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e2e8f0;
}

/* Responsive */
@media (max-width: 768px) {
    .settings-header {
        padding: 20px;
    }
    
    .settings-header h1 {
        font-size: 1.5rem;
    }
    
    .settings-card .card-body {
        padding: 20px;
    }
    
    .table-modern {
        font-size: 0.85rem;
    }
}
</style>

<div class="container-fluid px-4">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Header -->
            <div class="settings-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-cog me-3"></i>Cài đặt hệ thống</h1>
                        <p>Quản lý cấu hình, tài khoản và thông tin hệ thống</p>
                    </div>
                    <div>
                        <i class="fas fa-shield-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php echo $message; ?>
            <?php echo $error; ?>

            <div class="row">
                <!-- Đổi mật khẩu -->
                <div class="col-lg-6 mb-4">
                    <div class="settings-card">
                        <div class="card-header">
                            <h5><i class="fas fa-key"></i>Đổi mật khẩu</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock me-2 text-muted"></i>Mật khẩu hiện tại
                                    </label>
                                    <input type="password" class="form-control" name="current_password" required placeholder="Nhập mật khẩu hiện tại">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-key me-2 text-muted"></i>Mật khẩu mới
                                    </label>
                                    <input type="password" class="form-control" name="new_password" required placeholder="Nhập mật khẩu mới">
                                    <small class="text-muted">Mật khẩu phải có ít nhất 6 ký tự</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-check-circle me-2 text-muted"></i>Xác nhận mật khẩu mới
                                    </label>
                                    <input type="password" class="form-control" name="confirm_password" required placeholder="Nhập lại mật khẩu mới">
                                </div>
                                
                                <button type="submit" class="btn btn-gradient w-100">
                                    <i class="fas fa-save me-2"></i>Cập nhật mật khẩu
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Quản lý người dùng (chỉ admin) -->
                <?php if ($_SESSION['admin_role'] == 'admin'): ?>
                <div class="col-lg-6 mb-4">
                    <div class="settings-card">
                        <div class="card-header">
                            <h5><i class="fas fa-users"></i>Quản lý người dùng</h5>
                        </div>
                        <div class="card-body">
                            <!-- Form thêm user -->
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="action" value="add_user">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Tên đăng nhập</label>
                                        <input type="text" class="form-control" name="username" required placeholder="Nhập username">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Mật khẩu</label>
                                        <input type="password" class="form-control" name="password" required placeholder="Nhập mật khẩu">
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required placeholder="example@email.com">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Vai trò</label>
                                        <select class="form-select" name="role">
                                            <option value="admin">Admin</option>
                                            <option value="moderator">Moderator</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-gradient-success w-100">
                                            <i class="fas fa-user-plus me-2"></i>Thêm người dùng
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Danh sách user -->
                            <div class="mt-4">
                                <h6 class="mb-3"><i class="fas fa-list me-2"></i>Danh sách người dùng</h6>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-user me-2"></i>Username</th>
                                                <th><i class="fas fa-envelope me-2"></i>Email</th>
                                                <th><i class="fas fa-tag me-2"></i>Vai trò</th>
                                                <th><i class="fas fa-clock me-2"></i>Lần cuối</th>
                                                <th><i class="fas fa-cog me-2"></i>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = $admins->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-user-circle me-2 text-primary"></i>
                                                    <strong><?php echo htmlspecialchars($row['username']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td>
                                                    <?php if ($row['role'] == 'admin'): ?>
                                                        <span class="badge-admin"><i class="fas fa-crown me-1"></i>Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge-moderator"><i class="fas fa-user-check me-1"></i>Moderator</span>
                                                    <?php endif; ?>
                                                 </td>
                                                <td>
                                                    <?php echo $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : '<span class="text-muted"><i class="fas fa-minus-circle"></i> Chưa đăng nhập</span>'; ?>
                                                 </td>
                                                <td>
                                                    <?php if ($row['id'] != $_SESSION['admin_id']): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa người dùng này?')">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                        <span class="text-muted"><i class="fas fa-user-shield"></i> Chính bạn</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Thông tin hệ thống -->
            <div class="row">
                <div class="col-12">
                    <div class="info-card">
                        <h5><i class="fas fa-info-circle"></i>Thông tin hệ thống</h5>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-code-branch me-2"></i>Phiên bản</span>
                            <span class="info-value">1.0.0</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fab fa-php me-2"></i>PHP Version</span>
                            <span class="info-value"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-database me-2"></i>Database</span>
                            <span class="info-value">MySQL <?php echo $conn->server_info; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-folder-open me-2"></i>Thư mục uploads</span>
                            <span class="info-value"><?php echo UPLOAD_DIR; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-clock me-2"></i>Thời gian hệ thống</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i:s'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade modal-modern" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa người dùng này?</p>
                <p class="text-danger mb-0"><small>Hành động này không thể hoàn tác!</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-gradient-danger" id="confirmDeleteBtn">Xóa người dùng</button>
            </div>
        </div>
    </div>
</div>

<script>
// Xử lý xóa user với modal
let deleteForm = null;

document.querySelectorAll('.btn-outline-danger').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        deleteForm = btn.closest('form');
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});

document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
    if (deleteForm) {
        deleteForm.submit();
    }
});
</script>

