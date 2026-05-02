<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xác định có đang ở trong thư mục admissions không
$isInAdmissions = (strpos($_SERVER['REQUEST_URI'], '/admissions/') !== false);

// Đường dẫn login phù hợp
$loginUrl = $isInAdmissions ? 'login.php' : '../login.php';

// Đường dẫn cơ sở cho các file css, js, link
$baseUrl = $isInAdmissions ? '' : '../';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trường Học Hạnh Phúc - Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>assets/css/style.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="<?php echo $baseUrl; ?>index.php" style="color: #4e54c8;">
                <i class="fas fa-graduation-cap me-2"></i>EDUTECH<span class="text-warning">2026</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link active" href="<?php echo $baseUrl; ?>index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>about.php">Giới thiệu</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>teachers.php">Giảng viên</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>all_news.php">Tin tức</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>contact.php">Liên hệ</a></li>
                    

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown ms-3">
                            <a class="nav-link dropdown-toggle btn btn-light rounded-pill px-4 shadow-sm" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> <?= $_SESSION['full_name'] ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3">
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                    <li><a class="dropdown-item py-2" href="<?php echo $baseUrl; ?>admin/index.php"><i class="fas fa-user-shield me-2 text-primary"></i>Bảng điều khiển Admin</a></li>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] == 'teacher'): ?>
                                    <li><a class="dropdown-item py-2" href="<?php echo $baseUrl; ?>teacher/modules/dashboard/index.php"><i class="fas fa-chalkboard-teacher me-2 text-success"></i>Quản lý lớp học</a></li>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] == 'student'): ?>
                                    <li><a class="dropdown-item py-2" href="<?php echo $baseUrl; ?>student/index.php"><i class="fas fa-user-graduate me-2 text-warning"></i>Cổng học sinh</a></li>
                                <?php endif; ?>

                                <li><a class="dropdown-item py-2" href="<?php echo $baseUrl; ?>profile.php"><i class="fas fa-user me-2 text-info"></i>Hồ sơ cá nhân</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?php echo $baseUrl; ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-3">
                            <a class="btn btn-gradient rounded-pill px-4" href="<?php echo $loginUrl; ?>">Đăng nhập</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>