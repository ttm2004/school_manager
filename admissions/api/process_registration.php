<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$id     = intval($data['id'] ?? 0);
$action = $data['action'] ?? '';
$note   = trim($data['note'] ?? '');

if (!$id || !in_array($action, ['approved','rejected'])) {
    adm_json(false, 'Dữ liệu không hợp lệ');
}

$stmt = $conn->prepare("UPDATE adm_registrations SET status=? WHERE id=?");
$stmt->bind_param('si', $action, $id);
if (!$stmt->execute()) adm_json(false, 'Lỗi cập nhật: ' . $conn->error);

// Log
$userId = $_SESSION['user_id'];
$desc = ($action === 'approved' ? 'Duyệt hồ sơ' : 'Từ chối hồ sơ') . ($note ? ": $note" : '');
$ls = $conn->prepare("INSERT INTO adm_logs (registration_id, user_id, action, description) VALUES (?,?,?,?)");
$ls->bind_param('iiss', $id, $userId, $action, $desc);
$ls->execute();

adm_json(true, 'Cập nhật thành công');
