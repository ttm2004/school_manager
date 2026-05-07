<?php
require_once '../../php/config.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$major_id = (int)$data['major_id'];
$year = (int)$data['year'];
$quota = (int)$data['quota'];

// Kiểm tra đã tồn tại chưa
$check = $conn->prepare("SELECT id FROM admission_quota WHERE major_id = ? AND year = ?");
$check->bind_param("ii", $major_id, $year);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Update
    $stmt = $conn->prepare("UPDATE admission_quota SET quota = ? WHERE major_id = ? AND year = ?");
    $stmt->bind_param("iii", $quota, $major_id, $year);
} else {
    // Insert
    $stmt = $conn->prepare("INSERT INTO admission_quota (major_id, year, quota) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $major_id, $year, $quota);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật chỉ tiêu thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
}