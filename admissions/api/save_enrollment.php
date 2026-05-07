<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$regId  = intval($data['registration_id'] ?? 0);
$code   = trim($data['student_code'] ?? '');
$status = $data['status'] ?? 'processing';
$docs   = intval($data['documents_received'] ?? 0);
$tuition= intval($data['tuition_paid'] ?? 0);
$notes  = trim($data['notes'] ?? '');
$userId = $_SESSION['user_id'];

if (!$regId) adm_json(false, 'ID hồ sơ không hợp lệ');
if (!in_array($status, ['processing','completed','cancelled'])) adm_json(false, 'Trạng thái không hợp lệ');

// Check existing
$existing = $conn->query("SELECT id FROM adm_enrollments WHERE registration_id=$regId")->fetch_assoc();

if ($existing) {
    $stmt = $conn->prepare("UPDATE adm_enrollments SET student_code=?, status=?, documents_received=?, tuition_paid=?, notes=?, enrolled_by=? WHERE registration_id=?");
    $stmt->bind_param('ssiisii', $code, $status, $docs, $tuition, $notes, $userId, $regId);
} else {
    $stmt = $conn->prepare("INSERT INTO adm_enrollments (registration_id, student_code, status, documents_received, tuition_paid, notes, enrolled_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('issiiis', $regId, $code, $status, $docs, $tuition, $notes, $userId);
}

if (!$stmt->execute()) adm_json(false, 'Lỗi: ' . $conn->error);

// Log
$desc = "Cập nhật nhập học: $status" . ($code ? " | MSSV: $code" : '');
$ls = $conn->prepare("INSERT INTO adm_logs (registration_id, user_id, action, description) VALUES (?,?,'enrollment',?)");
$ls->bind_param('iis', $regId, $userId, $desc);
$ls->execute();

adm_json(true, 'Lưu thành công');
