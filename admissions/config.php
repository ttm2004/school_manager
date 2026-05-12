<?php
/**
 * Admissions Module Config
 * Bridge to school_manager's existing DB connection
 */
require_once __DIR__ . '/../config/database.php';

// Session already started by auth.php, but ensure it's active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function adm_sanitize($input) {
    // Chỉ dùng để làm sạch output HTML, KHÔNG dùng để escape SQL
    // Cho SQL: luôn dùng prepared statements
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function adm_redirect($url) {
    header("Location: $url");
    exit();
}

function adm_json($success, $message, $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit();
}

// Auth check for admissions admin pages
function adm_require_auth() {
    if (!isset($_SESSION['user_id'])) {
        adm_redirect('/university/login.php');
    }
    if (!in_array($_SESSION['role'], ['admin', 'admissions'])) {
        adm_redirect('/university/login.php');
    }
}

// Upload config
define('ADM_UPLOAD_DIR', __DIR__ . '/uploads/');
define('ADM_MAX_FILE_SIZE', 5242880); // 5MB
define('ADM_ALLOWED_EXT', ['pdf', 'jpg', 'jpeg', 'png']);

// Ensure upload dirs exist
foreach (['registrations', 'documents'] as $dir) {
    $path = ADM_UPLOAD_DIR . $dir . '/';
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
}

date_default_timezone_set('Asia/Ho_Chi_Minh');
