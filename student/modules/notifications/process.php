<?php
session_start();
require_once '../../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'get_notifications') {
    try {
        $sql = "SELECT 
                    ca.id,
                    ca.content,
                    DATE_FORMAT(ca.created_at, '%H:%i %d/%m/%Y') as created_at_fmt,
                    u.full_name as teacher_name,
                    u.avatar as teacher_avatar,
                    c.name as class_name,
                    s.name as subject_name
                FROM class_announcements ca
                JOIN student_subjects ss ON ca.class_id = ss.class_id AND ca.subject_id = ss.subject_id
                JOIN users u ON ca.teacher_id = u.id
                JOIN classes c ON ca.class_id = c.id
                JOIN subjects s ON ca.subject_id = s.id
                WHERE ss.student_id = ?
                ORDER BY ca.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$student_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>