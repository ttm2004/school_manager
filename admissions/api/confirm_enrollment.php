<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$data  = json_decode(file_get_contents('php://input'), true);
$regId = intval($data['registration_id'] ?? 0);
if (!$regId) adm_json(false, 'Thiếu thông tin hồ sơ');

$conn->begin_transaction();
try {
    // Get registration
    $stmt = $conn->prepare("SELECT r.*, m.major_name, m.id as mid, am.method_name
        FROM adm_registrations r
        LEFT JOIN majors m ON r.major_id = m.id
        LEFT JOIN adm_methods am ON r.method_code = am.code
        WHERE r.id = ?");
    $stmt->bind_param('i', $regId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    if (!$student) throw new Exception('Không tìm thấy hồ sơ');

    // Check result
    $result = $conn->query("SELECT * FROM adm_results WHERE registration_id=$regId")->fetch_assoc();
    if (!$result || $result['status'] !== 'passed') throw new Exception('Hồ sơ chưa trúng tuyển');

    // Check already confirmed
    $existing = $conn->query("SELECT id FROM adm_confirmations WHERE registration_id=$regId")->fetch_assoc();
    if ($existing) throw new Exception('Bạn đã xác nhận nhập học trước đó');

    // Get score
    $scoreRow = $conn->query("SELECT total_score FROM adm_scores WHERE registration_id=$regId")->fetch_assoc();
    $totalScore = $scoreRow['total_score'] ?? 0;
    $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

    $ins = $conn->prepare("INSERT INTO adm_confirmations
        (registration_id, fullname, phone, email, major_id, major_name, total_score, method_name, status, confirmed_at, expiry_date)
        VALUES (?,?,?,?,?,?,?,?,'confirmed',NOW(),?)");
    $ins->bind_param('isssisdss',
        $regId, $student['fullname'], $student['phone'], $student['email'],
        $student['mid'], $student['major_name'], $totalScore, $student['method_name'], $expiry);
    if (!$ins->execute()) throw new Exception('Lỗi lưu xác nhận');

    // Update quota confirmed count
    $conn->query("UPDATE adm_quota SET confirmed=confirmed+1 WHERE major_id={$student['mid']}");

    // Log
    $ls = $conn->prepare("INSERT INTO adm_logs (registration_id, action, description) VALUES (?,'confirm','Thí sinh xác nhận nhập học')");
    $ls->bind_param('i', $regId); $ls->execute();

    $conn->commit();
    adm_json(true, 'Xác nhận nhập học thành công!', ['expiry_date' => $expiry]);
} catch (Exception $e) {
    $conn->rollback();
    adm_json(false, $e->getMessage());
}
