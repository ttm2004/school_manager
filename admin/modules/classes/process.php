<?php
session_start();
require_once '../../../config/db.php';

/** @var PDO $conn */

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Hết phiên làm việc']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'fetch':
        $page = (int)($_POST['page'] ?? 1);
        $limit = 5; $offset = ($page - 1) * $limit;
        $search = $_POST['search'] ?? '';
        $dept_filter = $_POST['dept_id'] ?? '';

        $where = "WHERE c.name LIKE :s";
        if (!empty($dept_filter)) $where .= " AND c.department_id = :d";

        $sql = "SELECT c.*, d.name as dept_name, u.full_name as teacher_name 
                FROM classes c 
                LEFT JOIN departments d ON c.department_id = d.id
                LEFT JOIN users u ON c.teacher_id = u.id 
                $where ORDER BY c.id DESC LIMIT :l OFFSET :o";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':s', "%$search%");
        if (!empty($dept_filter)) $stmt->bindValue(':d', $dept_filter);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql_c = "SELECT COUNT(*) FROM classes c $where";
        $count = $conn->prepare($sql_c);
        $count->bindValue(':s', "%$search%");
        if (!empty($dept_filter)) $count->bindValue(':d', $dept_filter);
        $count->execute();

        echo json_encode(['status' => 'success', 'data' => $data, 'pagination' => ['total_pages' => ceil($count->fetchColumn() / $limit), 'current_page' => $page]]);
        break;

    case 'get_metadata':
        $depts = $conn->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
        $teachers = $conn->query("SELECT id, full_name FROM users WHERE role='teacher'")->fetchAll(PDO::FETCH_ASSOC);
        // THÊM: Lấy danh sách môn học
        $subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success', 
            'depts' => $depts, 
            'teachers' => $teachers,
            'subjects' => $subjects
        ]);
        break;

    case 'add':
        $stmt = $conn->prepare("INSERT INTO classes (name, department_id, teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['department_id'], $_POST['teacher_id']]);
        echo json_encode(['status' => 'success', 'message' => 'Tạo lớp học mới thành công']);
        break;

    case 'get_one':
        $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        break;

    case 'update':
        $stmt = $conn->prepare("UPDATE classes SET name=?, department_id=?, teacher_id=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['department_id'], $_POST['teacher_id'], $_POST['id']]);
        echo json_encode(['status' => 'success', 'message' => 'Cập nhật thông tin lớp thành công']);
        break;

    case 'delete':
        try {
            $conn->prepare("DELETE FROM classes WHERE id = ?")->execute([$_POST['id']]);
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa lớp học']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Không thể xóa lớp đang có dữ liệu liên quan']);
        }
        break;

    // THÊM: Lấy danh sách môn học đã phân công cho lớp
    case 'get_assignments':
        $class_id = $_POST['class_id'];
        $stmt = $conn->prepare("SELECT subject_id, teacher_id FROM class_subject_teachers WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    // THÊM: Lưu phân công giáo viên bộ môn
    case 'save_assignments':
        $class_id = $_POST['class_id'];
        $subject_ids = $_POST['subject_ids'] ?? [];
        $teacher_ids = $_POST['teacher_ids'] ?? [];

        try {
            $conn->beginTransaction();

            // 1. Xóa tất cả phân công cũ của lớp này
            $stmt_del = $conn->prepare("DELETE FROM class_subject_teachers WHERE class_id = ?");
            $stmt_del->execute([$class_id]);

            // 2. Chèn phân công mới
            if (!empty($subject_ids)) {
                $stmt_ins = $conn->prepare("INSERT INTO class_subject_teachers (class_id, subject_id, teacher_id) VALUES (?, ?, ?)");
                for ($i = 0; $i < count($subject_ids); $i++) {
                    if (!empty($subject_ids[$i]) && !empty($teacher_ids[$i])) {
                        $stmt_ins->execute([$class_id, $subject_ids[$i], $teacher_ids[$i]]);
                    }
                }
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật phân công bộ môn thành công']);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
        }
        break;
}