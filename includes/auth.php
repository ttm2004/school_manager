<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isLoggedIn() && $_SESSION['role'] === 'student';
}

function isTeacher() {
    return isLoggedIn() && $_SESSION['role'] === 'teacher';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /university/login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        // Redirect to appropriate dashboard
        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: /university/admin/');
                break;
            case 'student':
                header('Location: /university/student/');
                break;
            case 'teacher':
                header('Location: /university/teacher/');
                break;
            default:
                header('Location: /university/login.php');
        }
        exit();
    }
}
