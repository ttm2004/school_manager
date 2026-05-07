<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();

$majorId  = intval($_POST['major_id'] ?? 0);
$year     = intval($_POST['year'] ?? date('Y'));
$method   = trim($_POST['method_code'] ?? '');
$comboId  = !empty($_POST['combination_id']) ? intval($_POST['combination_id']) : null;
$score    = floatval($_POST['score'] ?? 0);
$quota    = !empty($_POST['quota']) ? intval($_POST['quota']) : null;

if (!$majorId || !$method || $score <= 0) {
    $_SESSION['adm_error'] = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    header('Location: ../admin/scores.php?year=' . $year);
    exit();
}

// Upsert
$check = $conn->prepare("SELECT id FROM adm_cutoff_scores WHERE major_id=? AND year=? AND method_code=? AND (combination_id=? OR (combination_id IS NULL AND ? IS NULL))");
$check->bind_param('iisii', $majorId, $year, $method, $comboId, $comboId);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE adm_cutoff_scores SET score=?, quota=? WHERE major_id=? AND year=? AND method_code=?");
    $stmt->bind_param('diisi', $score, $quota, $majorId, $year, $method);
} else {
    $stmt = $conn->prepare("INSERT INTO adm_cutoff_scores (major_id, year, method_code, combination_id, score, quota) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('iisidi', $majorId, $year, $method, $comboId, $score, $quota);
}

if ($stmt->execute()) {
    $_SESSION['adm_success'] = 'Lưu điểm chuẩn thành công!';
} else {
    $_SESSION['adm_error'] = 'Lỗi: ' . $conn->error;
}
header('Location: ../admin/scores.php?year=' . $year);
exit();
