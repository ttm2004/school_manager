<?php
require_once '../php/config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$registration_id = intval($data['registration_id'] ?? 0);

if (!$registration_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin hồ sơ']);
    exit();
}

$conn->begin_transaction();

try {

    // ===== 1. LẤY THÔNG TIN =====
    $sql = "SELECT r.*, 
                   m.name as major_name, m.id as major_id,
                   am.name as method_name
            FROM registrations r
            LEFT JOIN majors m ON r.major = m.id
            LEFT JOIN admission_methods am ON r.method = am.code
            WHERE r.id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param("i", $registration_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) throw new Exception('Không tìm thấy thí sinh');

    // ===== 2. CHECK ĐÃ XÁC NHẬN =====
    $check_stmt = $conn->prepare("SELECT id FROM admission_confirmation WHERE registration_id = ?");
    $check_stmt->bind_param("i", $registration_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('Bạn đã xác nhận trước đó');
    }

    // ===== 3. TÍNH ĐIỂM =====
    $total_score = 0;

    $score_stmt = $conn->prepare("SELECT score_data FROM diemtuyensinh WHERE registration_id = ?");
    $score_stmt->bind_param("i", $registration_id);
    $score_stmt->execute();
    $score_row = $score_stmt->get_result()->fetch_assoc();

    if (!empty($score_row['score_data'])) {
        $scores = json_decode($score_row['score_data'], true);
        $total_score = array_sum($scores);
    }

    // ===== 4. EXPIRY =====
    $expiry_date = date('Y-m-d H:i:s', strtotime('+7 days'));

    // ===== 5. INSERT CONFIRM =====
    $insert_sql = "INSERT INTO admission_confirmation 
        (registration_id, fullname, phone, email, major_id, major_name, total_score, method_name, expiry_date, status, confirmed_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())";

    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) throw new Exception($conn->error);

    $insert_stmt->bind_param("isssisdss",
        $registration_id,
        $student['fullname'],
        $student['phone'],
        $student['email'],
        $student['major_id'],
        $student['major_name'],
        $total_score,
        $student['method_name'],
        $expiry_date
    );

    if (!$insert_stmt->execute()) {
        throw new Exception('Lỗi lưu xác nhận');
    }

    // ===== 6. UPDATE QUOTA =====
    $quota_stmt = $conn->prepare("UPDATE admission_quota SET confirmed = confirmed + 1 WHERE major_id = ?");
    if ($quota_stmt) {
        $quota_stmt->bind_param("i", $student['major_id']);
        $quota_stmt->execute();
    }

    // ===== 7. UPDATE STATUS =====
    $update_stmt = $conn->prepare("UPDATE registrations SET status = 'approved' WHERE id = ?");
    $update_stmt->bind_param("i", $registration_id);
    $update_stmt->execute();

    // ===== 8. LOG =====
    $log_stmt = $conn->prepare("INSERT INTO registration_logs (registration_id, action, description) VALUES (?, 'confirm', 'Xác nhận nhập học')");
    if ($log_stmt) {
        $log_stmt->bind_param("i", $registration_id);
        $log_stmt->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Xác nhận nhập học thành công'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}