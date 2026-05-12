<?php
require_once __DIR__ . '/app.php';

mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

// Session config từ .env
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => config('session.secure', false),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.gc_maxlifetime', config('session.lifetime', 7200));
    session_start();

    // Session timeout
    if (isset($_SESSION['_last_activity'])) {
        if (time() - $_SESSION['_last_activity'] > config('session.lifetime', 7200)) {
            session_unset();
            session_destroy();
            header('Location: /university/login.php?msg=timeout');
            exit();
        }
    }
    if (isset($_SESSION['user_id'])) {
        $_SESSION['_last_activity'] = time();
    }
}

// DB connection
$conn = new mysqli(
    config('db.host'),
    config('db.user'),
    config('db.pass'),
    config('db.name'),
    config('db.port')
);
$conn->set_charset(config('db.charset', 'utf8mb4'));
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("SET character_set_connection = utf8mb4");
$conn->query("SET character_set_results = utf8mb4");

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    if (config('app.debug')) {
        die('DB Error: ' . htmlspecialchars($conn->connect_error));
    }
    die('Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.');
}
