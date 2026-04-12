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

if ($action === 'get_grades') {
    try {
        $sql = "SELECT 
                    s.id as subject_id,
                    s.name as subject_name, 
                    c.name as class_name,
                    c.id as class_id
                FROM student_subjects ss
                JOIN subjects s ON ss.subject_id = s.id
                JOIN classes c ON ss.class_id = c.id
                WHERE ss.student_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$student_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];

        foreach ($subjects as $sub) {
            $stmt_asm = $conn->prepare("SELECT AVG(grade) FROM submissions s 
                                        JOIN assignments a ON s.assignment_id = a.id 
                                        WHERE s.student_id = ? AND a.subject_id = ? AND a.class_id = ? AND s.grade IS NOT NULL");
            $stmt_asm->execute([$student_id, $sub['subject_id'], $sub['class_id']]);
            $avg_asm = $stmt_asm->fetchColumn();

            $stmt_exam = $conn->prepare("SELECT AVG(er.score) FROM exam_results er 
                                         JOIN exams e ON er.exam_id = e.id 
                                         WHERE er.student_id = ? AND e.subject_id = ? AND e.class_id = ?");
            $stmt_exam->execute([$student_id, $sub['subject_id'], $sub['class_id']]);
            $avg_exam = $stmt_exam->fetchColumn();

            $total = null;
            $rank = 'N/A';
            $rank_class = 'bg-secondary';

            if ($avg_asm !== false && $avg_exam !== false) {
                $total = ($avg_asm * 0.2) + ($avg_exam * 0.8);
                
                if ($total >= 8.5) { $rank = 'A'; $rank_class = 'bg-success'; }
                elseif ($total >= 7.0) { $rank = 'B'; $rank_class = 'bg-primary'; }
                elseif ($total >= 5.5) { $rank = 'C'; $rank_class = 'bg-warning text-dark'; }
                elseif ($total >= 4.0) { $rank = 'D'; $rank_class = 'bg-info text-dark'; }
                else { $rank = 'F'; $rank_class = 'bg-danger'; }
            }

            $data[] = [
                'subject' => $sub['subject_name'],
                'class_name' => $sub['class_name'],
                'avg_asm' => $avg_asm !== false ? number_format($avg_asm, 1) : '-',
                'avg_exam' => $avg_exam !== false ? number_format($avg_exam, 1) : '-',
                'total' => $total !== null ? number_format($total, 1) : '-',
                'rank' => $rank,
                'rank_class' => $rank_class
            ];
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>