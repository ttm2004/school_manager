<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once '../../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'get_schedule') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    try {
        $sql = "SELECT l.id, l.lesson_date, l.start_time, l.end_time, l.room_name, 
                       s.name as subject_name, c.name as class_name
                FROM lesson_logs l
                JOIN student_subjects ss ON l.class_id = ss.class_id AND l.subject_id = ss.subject_id
                JOIN subjects s ON l.subject_id = s.id
                JOIN classes c ON l.class_id = c.id
                WHERE ss.student_id = ? 
                AND l.lesson_date BETWEEN ? AND ?
                ORDER BY l.lesson_date ASC, l.start_time ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$student_id, $start_date, $end_date]);
        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $sql_today = "SELECT l.start_time, l.end_time, s.name as subject_name, c.name as class_name
                      FROM lesson_logs l
                      JOIN student_subjects ss ON l.class_id = ss.class_id AND l.subject_id = ss.subject_id
                      JOIN subjects s ON l.subject_id = s.id
                      JOIN classes c ON l.class_id = c.id
                      WHERE ss.student_id = ? AND l.lesson_date = ?
                      ORDER BY l.start_time ASC";
        
        $stmt_today = $conn->prepare($sql_today);
        $stmt_today->execute([$student_id, $today]);
        $today_classes = $stmt_today->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $schedule,
            'today_classes' => $today_classes
        ]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>