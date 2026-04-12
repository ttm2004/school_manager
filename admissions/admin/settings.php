<?php
// session_start();
require_once '../php/config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$message = '';

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
                    $message = '<div class="alert alert-success">Đổi mật khẩu thành công!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Mật khẩu mới không khớp!</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Mật khẩu hiện tại không đúng!</div>';
            }
        } elseif ($_POST['action'] == 'add_user') {
            $username = sanitize($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            
            $conn->query("INSERT INTO admin_users (username, password, email, role) VALUES ('$username', '$password', '$email', '$role')");
            $message = '<div class="alert alert-success">Thêm người dùng thành công!</div>';
        }
    }
}

// Lấy danh sách admin
$admins = $conn->query("SELECT * FROM admin_users ORDER BY id");

$page_title = "Cài đặt hệ thống";
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Cài đặt hệ thống</h1>
            </div>

            <?php echo $message; ?>

            <div class="row">
                <!-- Đổi mật khẩu -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-key me-2"></i>Đổi mật khẩu</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu hiện tại</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu mới</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Xác nhận mật khẩu</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Cập nhật mật khẩu
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Quản lý người dùng (chỉ admin) -->
                <?php if ($_SESSION['admin_role'] == 'admin'): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-users me-2"></i>Quản lý người dùng</h5>
                        </div>
                        <div class="card-body">
                            <!-- Form thêm user -->
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="action" value="add_user">
                                
                                <div class="mb-3">
                                    <label class="form-label">Tên đăng nhập</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Vai trò</label>
                                    <select class="form-select" name="role">
                                        <option value="admin">Admin</option>
                                        <option value="moderator">Moderator</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus"></i> Thêm người dùng
                                </button>
                            </form>

                            <!-- Danh sách user -->
                            <h6>Danh sách người dùng</h6>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Vai trò</th>
                                        <th>Lần cuối</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $admins->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['username']; ?></td>
                                        <td><?php echo $row['email']; ?></td>
                                        <td>
                                            <?php if ($row['role'] == 'admin'): ?>
                                                <span class="badge bg-primary">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Mod</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : 'Chưa đăng nhập'; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Thông tin hệ thống -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Thông tin hệ thống</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th>Phiên bản</th>
                            <td>1.0.0</td>
                        </tr>
                        <tr>
                            <th>PHP Version</th>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <th>Database</th>
                            <td>MySQL <?php echo $conn->server_info; ?></td>
                        </tr>
                        <tr>
                            <th>Thư mục uploads</th>
                            <td><?php echo UPLOAD_DIR; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>