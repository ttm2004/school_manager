<?php
/**
 * AJAX API cho enrollment.php
 * Tất cả tác vụ: enroll, cancel_enroll, create_account
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../app/Services/AdmissionsEnrollmentService.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);

header('Content-Type: application/json; charset=utf-8');

$isManager = hasRole('admissions_manager');
$canEnroll = hasPermission('admissions', 'manage_enrollment');
$dataMode = (($_POST['data_mode'] ?? $_POST['mode'] ?? 'system') === 'test') ? 'test' : 'system';

$roundPhase  = getRoundPhase($dataMode);
$activeRound = getActiveRound($dataMode);
$enrollAllowed = in_array($roundPhase, ['enrolling', 'supp_enrolling']);
$enrollLocked  = !$enrollAllowed;

$action = $_POST['action'] ?? '';

function jsonOk($data = [])  { echo json_encode(['ok' => true]  + $data); exit(); }
function jsonErr($msg)       { echo json_encode(['ok' => false, 'error' => $msg]); exit(); }

// ── ENROLL ──────────────────────────────────────────────────
if ($action === 'enroll') {
    if (!$canEnroll)    jsonErr('Bạn không có quyền xác nhận nhập học.');
    if ($enrollLocked)  jsonErr('Không thể làm thủ tục nhập học trong giai đoạn này.');

    $id = intval($_POST['id'] ?? 0);
    if (!$id) jsonErr('ID không hợp lệ.');

    $stmt = $conn->prepare("UPDATE admission_applications SET status='enrolled' WHERE id=? AND status='approved' AND data_mode=?");
    $stmt->bind_param('is', $id, $dataMode);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $stmt->close();
        // Lấy thông tin hồ sơ vừa enrolled để trả về
        $r = $conn->query("SELECT aa.*, m.major_name, m.major_code, am.method_name,
            (aa.math_score+aa.literature_score+aa.english_score) as total_score,
            (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email LIMIT 1) as has_account
            FROM admission_applications aa
            LEFT JOIN majors m ON aa.major_id=m.id
            LEFT JOIN admission_methods am ON aa.method_id=am.id
            WHERE aa.id=$id LIMIT 1");
        $row = $r ? $r->fetch_assoc() : [];
        jsonOk(['msg' => 'Đã xác nhận nhập học thành công!', 'row' => $row]);
    } else {
        $stmt->close();
        jsonErr('Không thể cập nhật. Hồ sơ có thể đã được xử lý.');
    }
}

// ── CANCEL ENROLL ────────────────────────────────────────────
if ($action === 'cancel_enroll') {
    if (!$isManager)   jsonErr('Chỉ Trưởng phòng mới có quyền hủy nhập học.');
    if ($enrollLocked) jsonErr('Không thể hủy nhập học trong giai đoạn này.');

    $id = intval($_POST['id'] ?? 0);
    if (!$id) jsonErr('ID không hợp lệ.');

    $stmt = $conn->prepare("UPDATE admission_applications SET status='approved' WHERE id=? AND status='enrolled' AND data_mode=?");
    $stmt->bind_param('is', $id, $dataMode);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $stmt->close();
        jsonOk(['msg' => 'Đã hủy nhập học.']);
    } else {
        $stmt->close();
        jsonErr('Không thể hủy. Hồ sơ có thể đã thay đổi trạng thái.');
    }
}

// ── CREATE ACCOUNT ───────────────────────────────────────────
if ($action === 'create_account') {
    if (!$canEnroll)   jsonErr('Bạn không có quyền cấp tài khoản.');
    if ($enrollLocked) jsonErr('Không thể cấp tài khoản trong giai đoạn này.');

    $app_id   = intval($_POST['app_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $autoEnrollMode = ($_POST['auto_enroll_mode'] ?? 'system') === 'test' ? 'test' : 'system';
    if (!$app_id || !$class_id) jsonErr('Vui lòng chọn lớp học.');

    $stmt = $conn->prepare("SELECT aa.*, m.major_code FROM admission_applications aa
        LEFT JOIN majors m ON aa.major_id=m.id
        WHERE aa.id=? AND aa.status='enrolled' AND aa.data_mode=?");
    $stmt->bind_param('is', $app_id, $dataMode);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$app) jsonErr('Không tìm thấy hồ sơ hoặc hồ sơ chưa được nhập học.');

    $chk = $conn->prepare('SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=?');
    $chk->bind_param('s', $app['email']);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) { $chk->close(); jsonErr('Email này đã có tài khoản sinh viên.'); }
    $chk->close();

    $classRow = AdmissionsEnrollmentService::getClassAcademicContext($conn, $class_id);
    if (!$classRow) jsonErr('Lớp hành chính không hợp lệ.');
    if ((int)$classRow['major_id'] !== (int)$app['major_id']) jsonErr('Lớp hành chính không thuộc ngành trúng tuyển.');

    $year      = (int)($classRow['enrollment_year'] ?: date('Y', strtotime($app['created_at'])));
    $majorCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $app['major_code'] ?? 'SV'));
    $attempts  = 0;
    do {
        $rand        = rand(10, 999);
        $studentCode = $year . $majorCode . $rand;
        $dup         = $conn->query("SELECT id FROM students WHERE student_code='$studentCode'");
        $attempts++;
    } while ($dup && $dup->num_rows > 0 && $attempts < 50);

    $username = strtolower($studentCode);
    $password = $studentCode;
    $hashed   = password_hash($password, PASSWORD_DEFAULT);

    $conn->begin_transaction();
    try {
        $us = $conn->prepare("INSERT INTO users (username,password,full_name,email,phone,role,status) VALUES (?,?,?,?,?,'student',1)");
        $us->bind_param('sssss', $username, $hashed, $app['full_name'], $app['email'], $app['phone']);
        if (!$us->execute()) throw new Exception($conn->error);
        $userId = $conn->insert_id;
        $us->close();

        $studentId = AdmissionsEnrollmentService::createStudentProfile($conn, $userId, $studentCode, $class_id, $app, $classRow, $autoEnrollMode);
        AdmissionsEnrollmentService::notifyFinanceNewEnrollment($conn, $studentId, $studentCode, $app['full_name']);

        $conn->commit();
        jsonOk([
            'msg'      => 'Cấp tài khoản thành công!',
            'account'  => [
                'full_name'    => $app['full_name'],
                'student_code' => $studentCode,
                'username'     => $username,
                'password'     => $password,
            ],
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        jsonErr('Lỗi: ' . $e->getMessage());
    }
}

jsonErr('Hành động không hợp lệ.');
