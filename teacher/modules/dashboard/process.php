<?php
session_start();
require_once '../../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Hết phiên làm việc']);
    exit;
}

$teacher_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_overview':
        // 1. Đếm số lớp chủ nhiệm
        $stmt1 = $conn->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
        $stmt1->execute([$teacher_id]);
        $count_homeroom = $stmt1->fetchColumn();

        // 2. Đếm số lớp đang giảng dạy (bộ môn)
        $stmt2 = $conn->prepare("SELECT COUNT(DISTINCT class_id) FROM class_subject_teachers WHERE teacher_id = ?");
        $stmt2->execute([$teacher_id]);
        $count_teaching = $stmt2->fetchColumn();

        // 3. Lấy danh sách các môn được giao (tên môn)
        $stmt3 = $conn->prepare("
            SELECT DISTINCT s.name 
            FROM class_subject_teachers cst
            JOIN subjects s ON cst.subject_id = s.id
            WHERE cst.teacher_id = ?
        ");
        $stmt3->execute([$teacher_id]);
        $subjects = $stmt3->fetchAll(PDO::FETCH_COLUMN);

        // 4. Tổng số học sinh đang dạy (tổng hợp từ các lớp bộ môn)
        $stmt4 = $conn->prepare("
            SELECT COUNT(DISTINCT cs.student_id) 
            FROM class_students cs
            JOIN class_subject_teachers cst ON cs.class_id = cst.class_id
            WHERE cst.teacher_id = ?
        ");
        $stmt4->execute([$teacher_id]);
        $total_students = $stmt4->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'counts' => [
                'homeroom' => $count_homeroom,
                'teaching' => $count_teaching,
                'subjects_count' => count($subjects),
                'students' => $total_students
            ],
            'subjects_list' => $subjects
        ]);
        break;
    
}