<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
if (!$id) adm_json(false, 'ID không hợp lệ');
$stmt = $conn->prepare("DELETE FROM adm_cutoff_scores WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute() ? adm_json(true, 'Đã xóa') : adm_json(false, $conn->error);
