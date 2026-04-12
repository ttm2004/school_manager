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
    case 'get_my_teaching_classes':
        // Lấy danh sách Lớp - Môn học mà giáo viên được phân công
        $sql = "SELECT cst.class_id, cst.subject_id, c.name as class_name, s.name as subject_name,
                (SELECT COUNT(*) FROM student_subjects ss WHERE ss.class_id = cst.class_id AND ss.subject_id = cst.subject_id) as student_count
                FROM class_subject_teachers cst
                JOIN classes c ON cst.class_id = c.id
                JOIN subjects s ON cst.subject_id = s.id
                WHERE cst.teacher_id = ?
                ORDER BY c.name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$teacher_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;
}