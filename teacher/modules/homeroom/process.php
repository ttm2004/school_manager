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
    case 'get_my_homeroom':
        $sql = "SELECT c.id, c.name as class_name, 
                DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as created_date,
                (SELECT COUNT(*) FROM class_students WHERE class_id = c.id) as total_students
                FROM classes c 
                WHERE c.teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$teacher_id]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $classes]);
        break;

    case 'get_students':
        $class_id = $_POST['class_id'] ?? 0;
        // Lấy đầy đủ thông tin học sinh dựa trên cấu trúc bảng users
        $sql = "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.address, u.avatar, 
                DATE_FORMAT(u.created_at, '%d/%m/%Y') as created_at
                FROM users u
                JOIN class_students cs ON u.id = cs.student_id
                WHERE cs.class_id = ? AND u.role = 'student'
                ORDER BY u.full_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $students]);
        break;
}