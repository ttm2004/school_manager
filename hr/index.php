<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['hr_manager','hr_staff']);
$pageTitle = 'Phong To chuc - Nhan su';
$userId = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body style="background:#eef2f7;min-height:100vh">
<nav class="navbar navbar-dark" style="background:#003087">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="bi bi-mortarboard-fill me-2 text-warning"></i>TDMU
        </span>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white-50 small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
            <?php if (canSwitchRole()): ?>
            <a href="/university/switch_role.php" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-arrow-left-right me-1"></i>Chuyen vai tro
            </a>
            <?php endif; ?>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-light">
                <i class="bi bi-box-arrow-right me-1"></i>Dang xuat
            </a>
        </div>
    </div>
</nav>
<div class="container py-5" style="max-width:700px">
    <div class="text-center mb-5">
        <div style="width:80px;height:80px;background:#6f42c1;border-radius:20px;display:inline-flex;align-items:center;justify-content:center;font-size:2.5rem;color:#fff;margin-bottom:20px">
            <i class="bi bi-people-fill"></i>
        </div>
        <h3 class="fw-bold">Phong To chuc - Nhan su</h3>
        <p class="text-muted">Xin chao, <strong><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></strong>!</p>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-tools me-2"></i>Module dang duoc phat trien</div>
        <div class="card-body">
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle-fill me-2"></i>
                Module <strong>Phong To chuc - Nhan su</strong> dang trong qua trinh phat trien.
                Cac chuc nang se duoc cap nhat trong phien ban tiep theo.
            </div>
            <h6 class="fw-bold mb-3">Chuc nang se co:</h6>
            <ul class="list-group list-group-flush">
<li class='list-group-item'><i class='bi bi-check-circle-fill text-success me-2'></i>Quan ly hop dong GV/NV</li>
<li class='list-group-item'><i class='bi bi-check-circle-fill text-success me-2'></i>Cham cong giang vien</li>
<li class='list-group-item'><i class='bi bi-check-circle-fill text-success me-2'></i>Quan ly luong</li>
<li class='list-group-item'><i class='bi bi-check-circle-fill text-success me-2'></i>Ho so nhan su</li>
<li class='list-group-item'><i class='bi bi-check-circle-fill text-success me-2'></i>Bao cao nhan su</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-link-45deg me-2"></i>Truy cap nhanh</div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-6">
                    <a href="/university/index.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-house me-1"></i>Trang chu
                    </a>
                </div>
                <div class="col-6">
                    <a href="/university/switch_role.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left-right me-1"></i>Chuyen vai tro
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
