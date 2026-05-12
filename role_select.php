<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Phải đăng nhập mới vào được
if (!isLoggedIn()) {
    header('Location: /university/login.php');
    exit();
}

// Nếu không có pending roles → redirect về trang chính
if (empty($_SESSION['_pending_roles'])) {
    header('Location: /university/login.php');
    exit();
}

$pendingRoles = $_SESSION['_pending_roles'];
$userId       = (int)$_SESSION['user_id'];

// Xử lý khi user chọn role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ.';
    } else {
        $selectedCode = trim($_POST['role_code'] ?? '');

        // Validate: role được chọn phải nằm trong danh sách pending
        $validCodes = array_column($pendingRoles, 'code');

        // Cho phép chọn __teacher__ nếu là teacher
        if ($selectedCode === '__teacher__' && $_SESSION['role'] === 'teacher') {
            unset($_SESSION['_pending_roles'], $_SESSION['_active_role']);
            unset($_SESSION['_faculty_id'], $_SESSION['_faculty_id_ts'],
                  $_SESSION['_dept_id'], $_SESSION['_user_role_codes'], $_SESSION['_roles_cached']);
            header('Location: /university/teacher/');
            exit();
        }

        if (!in_array($selectedCode, $validCodes, true)) {
            $error = 'Role không hợp lệ.';
        } else {
            // Lưu active role, xóa pending
            $_SESSION['_active_role'] = $selectedCode;
            unset($_SESSION['_pending_roles']);

            // Xóa faculty cache để force re-resolve theo role mới
            unset(
                $_SESSION['_faculty_id'],
                $_SESSION['_faculty_id_ts'],
                $_SESSION['_dept_id'],
                $_SESSION['_user_role_codes'],
                $_SESSION['_roles_cached']
            );

            // Pre-cache role codes để requireAnyRole hoạt động ngay
            // Lấy tất cả roles của user (bao gồm cả faculty_lecturer)
            $stmtCache = $conn->prepare(
                "SELECT r.code FROM user_roles ur
                 JOIN roles r ON ur.role_id = r.id
                 WHERE ur.user_id = ? AND r.is_active = 1
                   AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
            );
            $stmtCache->bind_param('i', $userId);
            $stmtCache->execute();
            $cachedCodes = [];
            $res = $stmtCache->get_result();
            while ($row = $res->fetch_assoc()) $cachedCodes[] = $row['code'];
            $stmtCache->close();
            $_SESSION['_user_role_codes'] = $cachedCodes;
            $_SESSION['_roles_cached']    = true;

            // Redirect theo role được chọn
            $redirectUrl = getRedirectByRoleCode($selectedCode, $conn);
            header('Location: ' . $redirectUrl);
            exit();
        }
    }
}

// Map role prefix → URL (giống login.php)
function getRedirectByRoleCode(string $roleCode, $conn): string
{
    $map = [
        'admissions_'      => '/university/admissions/',
        'academic_'        => '/university/academic/',
        'faculty_manager'  => '/university/faculty/',
        'faculty_staff'    => '/university/faculty/',
        'faculty_lecturer' => '/university/teacher/',
        'dept_head'        => '/university/faculty/',
        'finance_'         => '/university/finance/',
        'hr_'              => '/university/hr/',
        'student_affairs_' => '/university/student_affairs/',
        'exam_'            => '/university/exam/',
        'it_'              => '/university/admin/',
    ];
    foreach ($map as $prefix => $url) {
        if (str_starts_with($roleCode, $prefix)) {
            return $url;
        }
    }
    return '/university/teacher/';
}

// Icon và màu cho từng loại role
function getRoleIcon(string $code): array
{
    if (str_starts_with($code, 'faculty_manager') || str_starts_with($code, 'dept_head')) {
        return ['bi-building-fill', '#0d2d6b', 'Quản lý Khoa/Viện'];
    }
    if (str_starts_with($code, 'faculty_staff') || str_starts_with($code, 'faculty_lecturer')) {
        return ['bi-person-badge-fill', '#1a4fa0', 'Nghiệp vụ Khoa'];
    }
    if (str_starts_with($code, 'academic_')) {
        return ['bi-mortarboard-fill', '#198754', 'Phòng Đào tạo'];
    }
    if (str_starts_with($code, 'admissions_')) {
        return ['bi-person-plus-fill', '#0d6efd', 'Phòng Tuyển sinh'];
    }
    if (str_starts_with($code, 'finance_')) {
        return ['bi-cash-coin', '#ffc107', 'Phòng Tài chính'];
    }
    if (str_starts_with($code, 'hr_')) {
        return ['bi-people-fill', '#6f42c1', 'Phòng Nhân sự'];
    }
    if (str_starts_with($code, 'exam_')) {
        return ['bi-clipboard-check-fill', '#0d2d6b', 'Phòng Khảo thí'];
    }
    return ['bi-shield-fill', '#6c757d', 'Hệ thống'];
}

// Thêm role giảng viên vào danh sách nếu là teacher
$systemRole = $_SESSION['role'] ?? '';
$allChoices = [];

// GV luôn có thể vào portal giảng viên — thêm vào đầu
if ($systemRole === 'teacher') {
    $allChoices[] = [
        'code'       => '__teacher__',
        'name'       => 'Giảng viên',
        'department' => 'Portal Giảng viên',
        'color'      => '#495057',
    ];
}

// Thêm các dept roles (đã loại faculty_lecturer ở login.php)
foreach ($pendingRoles as $r) {
    if ($r['code'] !== 'faculty_lecturer') {
        $allChoices[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn vai trò — TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            background: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .role-card {
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            transition: all .2s;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .role-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,48,135,.15);
            border-color: #003087;
            color: inherit;
        }
        .role-card:active { transform: translateY(-1px); }
        .role-icon {
            width: 64px; height: 64px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 16px;
            color: #fff;
        }
        .role-name { font-size: 1rem; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
        .role-dept { font-size: .8rem; color: #888; }
        .wrapper {
            width: 100%;
            max-width: 680px;
            padding: 20px;
        }
        .brand-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .brand-icon {
            width: 52px; height: 52px;
            background: #003087;
            border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            color: #FFB81C; font-size: 1.5rem;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Header -->
    <div class="brand-header">
        <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <h4 class="fw-bold text-navy mb-1">Xin chào, <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>!</h4>
        <p class="text-muted mb-0">
            Tài khoản của bạn có <strong><?php echo count($allChoices); ?> vai trò</strong>.
            Vui lòng chọn vai trò để tiếp tục.
        </p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Role cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($allChoices as $role):
            if ($role['code'] === '__teacher__') {
                $icon = 'bi-person-badge-fill';
                $color = '#495057';
                $dept  = 'Portal Giảng viên';
            } else {
                [$icon, $color, $dept] = getRoleIcon($role['code']);
            }
            $targetCode = $role['code'];
        ?>
        <div class="col-6 col-md-4">
            <form method="POST" action="role_select.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="role_code" value="<?php echo htmlspecialchars($targetCode); ?>">
                <button type="submit" class="role-card w-100 border-0 bg-transparent p-0">
                    <div class="role-card">
                        <div class="role-icon" style="background:<?php echo htmlspecialchars($color); ?>">
                            <i class="bi <?php echo htmlspecialchars($icon); ?>"></i>
                        </div>
                        <div class="role-name"><?php echo htmlspecialchars($role['name']); ?></div>
                        <div class="role-dept"><?php echo htmlspecialchars($role['department'] ?? $dept); ?></div>
                    </div>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Logout -->
    <div class="text-center">
        <a href="/university/login.php?logout=1" class="text-muted small">
            <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất và dùng tài khoản khác
        </a>
    </div>
</div>
</body>
</html>
