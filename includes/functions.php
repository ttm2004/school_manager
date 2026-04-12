<?php
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }
}

function check_role($required_role) {
    if ($_SESSION['role'] !== $required_role && $_SESSION['role'] !== 'admin') {
        echo "Bạn không có quyền truy cập trang này!";
        exit;
    }
}
?>