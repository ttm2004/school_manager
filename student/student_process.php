<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Hết phiên làm việc hoặc sai quyền']);
    exit;
}

$action = $_POST['action'] ?? '';
$student_id = (int)$_SESSION['user_id'];
$class_id = (int)($_POST['class_id'] ?? 0);
$subject_id = (int)($_POST['subject_id'] ?? 0);

$limit = 6;
$page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$offset = ($page - 1) * $limit;

switch ($action) {
    case 'get_my_courses':
        try {
            $countStmt = $conn->prepare("SELECT COUNT(*) FROM student_subjects WHERE student_id = ?");
            $countStmt->execute([$student_id]);
            $total_items = $countStmt->fetchColumn();
            $total_pages = ceil($total_items / $limit);

            $sql = "SELECT c.name as class_name, s.name as subject_name, ss.class_id, ss.subject_id 
                    FROM student_subjects ss
                    INNER JOIN classes c ON ss.class_id = c.id
                    INNER JOIN subjects s ON ss.subject_id = s.id
                    WHERE ss.student_id = ? 
                    LIMIT $limit OFFSET $offset";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$student_id]);

            echo json_encode([
                'status' => 'success',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total_pages' => $total_pages,
                'current_page' => $page
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'get_assignments':
        $stmtInfo = $conn->prepare("SELECT c.name as class_name, s.name as subject_name FROM classes c, subjects s WHERE c.id = ? AND s.id = ?");
        $stmtInfo->execute([$class_id, $subject_id]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        $countStmt = $conn->prepare("SELECT COUNT(*) FROM assignments WHERE class_id = ? AND subject_id = ?");
        $countStmt->execute([$class_id, $subject_id]);
        $total_items = $countStmt->fetchColumn();
        $total_pages = ceil($total_items / $limit);

        $stmt = $conn->prepare("SELECT a.*, DATE_FORMAT(a.deadline, '%d/%m/%Y %H:%i') as deadline_f, 
                                       s.grade, s.submitted_at, s.file_path as sub_file, s.external_link as sub_link
                                FROM assignments a 
                                LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
                                WHERE a.class_id = ? AND a.subject_id = ? 
                                ORDER BY a.deadline ASC 
                                LIMIT $limit OFFSET $offset");
        $stmt->execute([$student_id, $class_id, $subject_id]);

        echo json_encode([
            'status' => 'success',
            'info' => $info,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total_pages' => $total_pages,
            'current_page' => $page,
            'now' => date('Y-m-d H:i:s')
        ]);
        break;

    case 'submit_work':
        $aid = (int)$_POST['assignment_id'];
        $link = $_POST['external_link'] ?? null;
        $fname = null;
        if (!empty($_FILES['attachment']['name'])) {
            $dir = "../../../uploads/submissions/";
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = time() . '_' . $_FILES["attachment"]["name"];
            move_uploaded_file($_FILES["attachment"]["tmp_name"], $dir . $fname);
        }
        $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, external_link) 
                                VALUES (?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), external_link=VALUES(external_link), submitted_at=NOW()");
        $stmt->execute([$aid, $student_id, $fname, $link]);
        echo json_encode(['status' => 'success']);
        break;

    case 'get_attendance':
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM lesson_logs WHERE class_id = ? AND subject_id = ? AND is_completed = 1");
        $countStmt->execute([$class_id, $subject_id]);
        $total_items = $countStmt->fetchColumn();
        $total_pages = ceil($total_items / $limit);

        $stmt = $conn->prepare("SELECT l.lesson_date, l.start_time, l.end_time, COALESCE(a.status, 0) as status 
                                FROM lesson_logs l 
                                LEFT JOIN attendance a ON l.id = a.lesson_log_id AND a.student_id = ?
                                WHERE l.class_id = ? AND l.subject_id = ? AND l.is_completed = 1 
                                ORDER BY l.lesson_date DESC 
                                LIMIT $limit OFFSET $offset");
        $stmt->execute([$student_id, $class_id, $subject_id]);

        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total_pages' => $total_pages,
            'current_page' => $page
        ]);
        break;

    case 'get_exams':
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM exams WHERE class_id = ? AND subject_id = ?");
        $countStmt->execute([$class_id, $subject_id]);
        $total_items = $countStmt->fetchColumn();
        $total_pages = ceil($total_items / $limit);

        $stmt = $conn->prepare("SELECT e.*, er.score 
                                FROM exams e 
                                LEFT JOIN exam_results er ON e.id = er.exam_id AND er.student_id = ? 
                                WHERE e.class_id = ? AND e.subject_id = ? 
                                LIMIT $limit OFFSET $offset");
        $stmt->execute([$student_id, $class_id, $subject_id]);

        echo json_encode([
            'status' => 'success',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total_pages' => $total_pages,
            'current_page' => $page
        ]);
        break;
    case 'submit_exam':
        try {
            $exam_id = (int)$_POST['exam_id'];
            $answers = $_POST['answers'] ?? []; // Mảng [question_id => selected_option]

            // 1. Lấy đáp án đúng từ database để đối chiếu
            $stmt = $conn->prepare("SELECT id, correct_option FROM exam_questions WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$questions) {
                echo json_encode(['status' => 'error', 'message' => 'Đề thi không có câu hỏi']);
                exit;
            }

            $total_questions = count($questions);
            $correct_answers = 0;

            // 2. Duyệt qua từng câu hỏi để chấm điểm
            foreach ($questions as $q) {
                $q_id = $q['id'];
                // Kiểm tra xem sinh viên có chọn đáp án cho câu này không và có đúng không
                if (isset($answers[$q_id]) && trim(strtoupper($answers[$q_id])) === trim(strtoupper($q['correct_option']))) {
                    $correct_answers++;
                }
            }

            // 3. Tính điểm trên thang điểm 10
            $score = ($correct_answers / $total_questions) * 10;
            $score = round($score, 2); // Làm tròn 2 chữ số thập phân

            // 4. Lưu kết quả vào bảng exam_results
            $stmtInsert = $conn->prepare("
                INSERT INTO exam_results (exam_id, student_id, total_questions, correct_answers, score, completed_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->execute([$exam_id, $student_id, $total_questions, $correct_answers, $score]);

            // 5. Trả về kết quả cho Frontend hiển thị SweetAlert
            echo json_encode([
                'status' => 'success',
                'score' => $score,
                'correct' => $correct_answers,
                'total' => $total_questions
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi chấm điểm: ' . $e->getMessage()]);
        }
        break;
    case 'get_class_announcements':
        $stmt = $conn->prepare("
            SELECT ca.*, u.full_name as teacher_name 
            FROM class_announcements ca
            JOIN users u ON ca.teacher_id = u.id
            WHERE ca.class_id = ? AND ca.subject_id = ?
            ORDER BY ca.created_at DESC
        ");
        $stmt->execute([$class_id, $subject_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
}
