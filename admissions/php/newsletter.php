<?php
require_once 'config.php';
header('Content-Type: application/json');

// Lấy dữ liệu JSON từ request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu email']);
    exit;
}

$email = sanitize($data['email']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
    exit;
}

// Kiểm tra đã tồn tại chưa
$check = $conn->query("SELECT id FROM newsletter WHERE email = '$email'");
if ($check->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email đã đăng ký nhận tin']);
    exit;
}

$sql = "INSERT INTO newsletter (email) VALUES ('$email')";
if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Đăng ký nhận tin thành công!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
}
?>