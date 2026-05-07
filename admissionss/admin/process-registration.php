<?php
session_start();
require_once '../php/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id']);
$action = $data['action'];
$note = isset($data['note']) ? sanitize($data['note']) : '';

if ($action == 'approve') {
    $status = 'approved';
    $subject = "Kết quả xét tuyển - Đã trúng tuyển";
    $message = "Chúc mừng bạn đã trúng tuyển vào trường!";
} else {
    $status = 'rejected';
    $subject = "Kết quả xét tuyển - Không trúng tuyển";
    $message = "Rất tiếc, hồ sơ của bạn không đủ điều kiện trúng tuyển.";
}

// Cập nhật trạng thái
$sql = "UPDATE registrations SET status = ?, note = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $status, $note, $id);

if ($stmt->execute()) {
    // Lấy email để gửi thông báo
    $result = $conn->query("SELECT email, fullname FROM registrations WHERE id = $id");
    $row = $result->fetch_assoc();
    
    // Gửi email thông báo (tùy chọn)
    // sendEmail($row['email'], $subject, $message);
    
    // Ghi log
    $log_sql = "INSERT INTO activity_logs (admin_id, action, registration_id, note) 
                VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("isis", $_SESSION['admin_id'], $action, $id, $note);
    $log_stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>