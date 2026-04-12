<?php
// session_start();
require_once '../../php/config.php';

header('Content-Type: application/json; charset=utf-8');

// Chỉ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "Sai method"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy JSON
$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (!$username || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Thiếu dữ liệu"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Query DB
$sql = "SELECT * FROM admin_users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password'])) {

        // tạo session
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_username'] = $row['username'];
        $_SESSION['admin_role'] = $row['role'];

        // update login
        $conn->query("UPDATE admin_users SET last_login = NOW() WHERE id = " . $row['id']);

        echo json_encode([
            "success" => true,
            "message" => "Đăng nhập thành công"
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode([
            "success" => false,
            "message" => "Sai mật khẩu"
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Không tồn tại tài khoản"
    ], JSON_UNESCAPED_UNICODE);
}