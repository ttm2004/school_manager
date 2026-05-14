<?php
/**
 * switch_role.php — Rebuild pending roles từ DB và redirect sang role_select.php
 * Dùng khi user muốn chuyển vai trò từ bất kỳ module nào
 */
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /university/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Xóa active role hiện tại
unset(
    $_SESSION['_active_role'],
    $_SESSION['_faculty_id'],
    $_SESSION['_faculty_id_ts'],
    $_SESSION['_dept_id'],
    $_SESSION['_user_role_codes'],
    $_SESSION['_roles_cached'],
    $_SESSION['_can_switch_role']
);

// Rebuild pending roles từ DB (loại faculty_lecturer)
$stmt = $conn->prepare(
    "SELECT r.code, r.name, r.department, r.color
     FROM user_roles ur
     JOIN roles r ON ur.role_id = r.id
     WHERE ur.user_id = ? AND r.is_active = 1
       AND r.code != 'faculty_lecturer'
       AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
     ORDER BY
         CASE WHEN r.code LIKE '%_manager%' THEN 0
              WHEN r.code LIKE 'faculty_manager%' THEN 0
              WHEN r.code LIKE 'dept_head%' THEN 1
              ELSE 2 END,
         r.department"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$deptRoles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($deptRoles) && $_SESSION['role'] === 'teacher') {
    // GV không có dept role → về thẳng teacher portal
    $_SESSION['_active_role'] = '__teacher__';
    $_SESSION['_user_role_codes'] = ['faculty_lecturer'];
    $_SESSION['_roles_cached'] = true;
    header('Location: /university/teacher/');
    exit();
}

if (count($deptRoles) === 1 && $_SESSION['role'] !== 'teacher') {
    // Chỉ 1 role duy nhất → redirect thẳng
    $_SESSION['_active_role'] = $deptRoles[0]['code'];
    $map = [
        'admissions_'  => '/university/admissions/',
        'academic_'    => '/university/academic/',
        'faculty_'     => '/university/faculty/',
        'finance_'     => '/university/finance/',
        'hr_'          => '/university/hr/',
        'exam_'        => '/university/exam/',
        'it_'          => '/university/admin/',
    ];
    $url = '/university/teacher/';
    foreach ($map as $prefix => $target) {
        if (str_starts_with($deptRoles[0]['code'], $prefix)) {
            $url = $target;
            break;
        }
    }
    header('Location: ' . $url);
    exit();
}

// Nhiều roles → set pending và cho chọn
$_SESSION['_pending_roles'] = $deptRoles;
header('Location: /university/role_select.php');
exit();
