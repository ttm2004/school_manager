<?php
session_start();
require_once 'config/db.php';

// Nếu đã đăng nhập rồi thì tự động điều hướng theo role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') header('Location: admin/index.php');
    elseif ($_SESSION['role'] == 'teacher') header('Location: teacher/modules/dashboard/index.php');
    else header('Location: student/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :u");
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();

        // Trong thực tế nên dùng password_verify, ở đây giữ nguyên logic so sánh chuỗi của bạn
        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Điều hướng
            if ($user['role'] == 'admin') {
                header('Location: admin/index.php');
            } elseif ($user['role'] == 'teacher') {
                header('Location: teacher/modules/dashboard/index.php'); 
            } else {
                header('Location: student/index.php');
            }
            exit;
        } else {
            $error = "Tài khoản hoặc mật khẩu không đúng!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập hệ thống</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: #f4f7f6; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card { 
            width: 100%;
            max-width: 400px; 
            padding: 40px; 
            border-radius: 20px; 
            background: white; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
        }
        .login-card h3 { font-weight: 700; color: #333; }
        .form-control { border-radius: 10px; padding: 12px; }
        .btn-primary { 
            border-radius: 10px; 
            padding: 12px; 
            font-weight: 600; 
            background: #4e73df;
            border: none;
            transition: all 0.3s;
        }
        .btn-primary:hover { background: #2e59d9; transform: translateY(-2px); }
        .input-group-text { border-radius: 10px 0 0 10px; background: white; }
        .login-card .form-control { border-left: none; border-radius: 0 10px 10px 0; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="bg-primary text-white d-inline-block p-3 rounded-circle mb-3">
                <i class="fas fa-user-shield fa-2x"></i>
            </div>
            <h3>HỆ THỐNG QUẢN LÝ</h3>
            <p class="text-muted">Vui lòng đăng nhập để tiếp tục</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 shadow-sm small text-center"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">Tên đăng nhập</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control" required placeholder="Nhập tài khoản">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">Mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control" required placeholder="********">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 shadow-sm">
                ĐĂNG NHẬP <i class="fas fa-sign-in-alt ms-2"></i>
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="index.php" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> Quay lại trang chủ
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>