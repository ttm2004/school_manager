<?php
require_once '../../php/config.php';
// session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM cutoff_scores WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Xóa điểm chuẩn thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
}