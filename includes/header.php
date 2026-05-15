<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>TDMU - Trường Đại học Thủ Dầu Một</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-navy sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/university/index.php">
            <div class="brand-logo me-2">
                <i class="bi bi-mortarboard-fill fs-2 text-gold"></i>
            </div>
            <div>
                <div class="fw-bold" style="font-size:1rem;line-height:1.1;">Trường Đại học</div>
                <div class="fw-bold text-gold" style="font-size:1.1rem;line-height:1.1;">Thủ Dầu Một</div>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage=='index.php'?'active':''; ?>" href="/university/index.php">Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage=='about.php'?'active':''; ?>" href="/university/about.php">Giới thiệu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage=='programs.php'?'active':''; ?>" href="/university/programs.php">Chương trình ĐT</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage=='admission.php'?'active':''; ?>" href="/university/admission.php">Tuyển sinh</a>
                </li>
                <?php if (function_exists('isAnyAdmissionLookupOpen') && isAnyAdmissionLookupOpen()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage=='admission_lookup.php'?'active':''; ?>" href="/university/admission_lookup.php">Tra cứu kết quả</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage=='news.php'?'active':''; ?>" href="/university/news.php">Tin tức</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage=='contact.php'?'active':''; ?>" href="/university/contact.php">Liên hệ</a>
                </li>
            </ul>
            <div class="ms-3 d-flex gap-2">
                <?php if (isLoggedIn()): ?>
                    <?php
                    $dashLink = '/university/login.php';
                    if ($_SESSION['role'] === 'admin') $dashLink = '/university/admin/';
                    elseif ($_SESSION['role'] === 'student') $dashLink = '/university/student/';
                    elseif ($_SESSION['role'] === 'teacher') $dashLink = '/university/teacher/';
                    ?>
                    <a href="<?php echo $dashLink; ?>" class="btn btn-outline-gold btn-sm">
                        <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </a>
                    <a href="/university/login.php?logout=1" class="btn btn-gold btn-sm">Đăng xuất</a>
                <?php else: ?>
                    <a href="/university/login.php" class="btn btn-gold btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
