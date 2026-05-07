<?php
require_once '../../php/config.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $major_id = (int)$_POST['major_id'];
    $year = (int)$_POST['year'];
    $method_code = $_POST['method_code'];
    $combination_id = !empty($_POST['combination_id']) ? (int)$_POST['combination_id'] : null;
    $score = (float)$_POST['score'];
    $quota = !empty($_POST['quota']) ? (int)$_POST['quota'] : null;
    
    if ($action == 'add') {
        // Kiểm tra đã tồn tại chưa
        $check = $conn->prepare("SELECT id FROM cutoff_scores WHERE major_id = ? AND year = ? AND method_code = ? AND (combination_id = ? OR (combination_id IS NULL AND ? IS NULL))");
        $check->bind_param("iisi", $major_id, $year, $method_code, $combination_id, $combination_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Điểm chuẩn đã tồn tại']);
            exit();
        }
        
        // Thêm mới
        $stmt = $conn->prepare("INSERT INTO cutoff_scores (major_id, year, method_code, combination_id, score, quota) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisidi", $major_id, $year, $method_code, $combination_id, $score, $quota);
        
    } elseif ($action == 'edit') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE cutoff_scores SET method_code = ?, combination_id = ?, score = ?, quota = ? WHERE id = ?");
        $stmt->bind_param("sidii", $method_code, $combination_id, $score, $quota, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cập nhật điểm chuẩn thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}