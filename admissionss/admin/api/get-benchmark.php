<?php
require_once '../../php/config.php';
// session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = $_GET['id'] ?? 0;

$result = $conn->query("
    SELECT cs.*, m.code as major_code, m.name as major_name
    FROM cutoff_scores cs
    LEFT JOIN majors m ON cs.major_id = m.id
    WHERE cs.id = $id
");

if ($result && $result->num_rows > 0) {
    echo json_encode($result->fetch_assoc(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy']);
}