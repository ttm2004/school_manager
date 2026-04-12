<?php
session_start();
require '../includes/functions.php';
check_login();
check_role('teacher'); // Chỉ giáo viên (hoặc admin) mới vào được
?>