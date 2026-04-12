<?php
require_once 'config.php';

if (!isset($_GET['file']) || !isset($_GET['id'])) {
    die('Thiếu thông tin file');
}

$file = sanitize($_GET['file']);
$reg_id = intval($_GET['id']);

// Kiểm tra hồ sơ tồn tại
$sql = "SELECT * FROM registrations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reg_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $file_path = UPLOAD_DIR . 'registrations/' . $reg_id . '/' . $row[$file . '_file'];
    
    if (file_exists($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}

die('File không tồn tại');
?>