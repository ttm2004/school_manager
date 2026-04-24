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

function uploadAvatar($file)
{
    $dir = "../../../uploads/avatars/";
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $name = time() . '_' . rand(1000, 9999) . '.' . $ext;
    return move_uploaded_file($file["tmp_name"], $dir . $name) ? $name : null;
}

switch ($action) {
    case 'fetch':
        $page = (int)($_POST['page'] ?? 1);
        $limit = 5;
        $offset = ($page - 1) * $limit;
        $search = $_POST['search'] ?? '';
        $dept_filter = $_POST['dept_id'] ?? '';
        $class_id = $_POST['class_id'] ?? '';

        $where = "u.role='student'";
        $params = [];

        if (!empty($search)) {
            $where .= " AND (u.full_name LIKE :s OR u.username LIKE :s OR u.phone LIKE :s)";
            $params[':s'] = "%$search%";
        }

        if (!empty($dept_filter)) {
            $where .= " AND u.department_id = :d";
            $params[':d'] = $dept_filter;
        }

        if (!empty($class_id)) {
            $where .= " AND EXISTS (SELECT 1 FROM class_students cs WHERE cs.student_id = u.id AND cs.class_id = :c)";
            $params[':c'] = $class_id;
        }

        $sql = "SELECT u.*, d.name as dept_name,
                (SELECT GROUP_CONCAT(c.name) FROM class_students cs JOIN classes c ON cs.class_id = c.id WHERE cs.student_id = u.id) as classes 
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE $where 
                ORDER BY u.id DESC LIMIT $limit OFFSET $offset";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql_count = "SELECT COUNT(*) FROM users u WHERE $where";
        $count_stmt = $conn->prepare($sql_count);
        foreach ($params as $key => $val) {
            $count_stmt->bindValue($key, $val);
        }
        $count_stmt->execute();
        $total_records = $count_stmt->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'total_pages' => ceil($total_records / $limit),
                'current_page' => $page
            ]
        ]);
        break;

    case 'add':
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$_POST['username']]);
        if ($check->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập đã tồn tại']);
            exit;
        }
        $avatar = (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) ? uploadAvatar($_FILES['avatar']) : null;
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, address, role, department_id, avatar) VALUES (?, ?, ?, ?, ?, ?, 'student', ?, ?)");
        if ($stmt->execute([$_POST['username'], $_POST['password'], $_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['department_id'], $avatar])) {
            echo json_encode(['status' => 'success', 'message' => 'Thêm học sinh mới thành công']);
        }
        break;

    case 'get_one':
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $user]);
        break;

    case 'update':
        $sql = "UPDATE users SET full_name=?, email=?, phone=?, address=?, department_id=?";
        $params = [$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['department_id']];
        if (!empty($_POST['password'])) {
            $sql .= ", password=?";
            $params[] = $_POST['password'];
        }
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $av = uploadAvatar($_FILES['avatar']);
            if ($av) {
                $sql .= ", avatar=?";
                $params[] = $av;
            }
        }
        $sql .= " WHERE id=?";
        $params[] = $_POST['id'];
        if ($conn->prepare($sql)->execute($params)) {
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật hồ sơ thành công']);
        }
        break;

    case 'delete':
        if ($conn->prepare("DELETE FROM users WHERE id = ?")->execute([$_POST['id']])) {
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa học sinh khỏi hệ thống']);
        }
        break;

    case 'get_metadata':
        $depts = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $classes = $conn->query("SELECT id, name FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'depts' => $depts, 'classes' => $classes]);
        break;

    case 'get_classes_by_dept':
        $dept_id = $_POST['dept_id'];
        $stmt = $conn->prepare("SELECT id, name FROM classes WHERE department_id = ? ORDER BY name");
        $stmt->execute([$dept_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'get_student_classes':
        $stmt = $conn->prepare("SELECT class_id FROM class_students WHERE student_id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        break;

    case 'save_student_classes':
        $student_id = $_POST['student_id'] ?? 0;
        $class_ids = $_POST['class_ids'] ?? [];
        if (empty($student_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu ID học sinh']);
            exit;
        }
        try {
            $conn->beginTransaction();
            $stmt_del = $conn->prepare("DELETE FROM class_students WHERE student_id = ?");
            $stmt_del->execute([$student_id]);
            if (!empty($class_ids)) {
                $unique_class_ids = array_unique($class_ids);
                $stmt_ins = $conn->prepare("INSERT INTO class_students (student_id, class_id) VALUES (?, ?)");
                foreach ($unique_class_ids as $class_id) {
                    if (!empty($class_id)) {
                        $stmt_ins->execute([$student_id, $class_id]);
                    }
                }
            }
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật lớp học thành công']);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Lỗi SQL: ' . $e->getMessage()]);
        }
        break;

    case 'get_class_subjects':
        $class_id = $_POST['class_id'];
        $sql = "SELECT DISTINCT s.id, s.name 
                FROM class_subject_teachers cst 
                JOIN subjects s ON cst.subject_id = s.id 
                WHERE cst.class_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$class_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'get_student_subjects':
        $student_id = $_POST['student_id'];
        $stmt = $conn->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
        $stmt->execute([$student_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        break;

    case 'save_student_subjects':
        $student_id = $_POST['student_id'];
        $class_id = $_POST['class_id'];
        $subject_ids = $_POST['subject_ids'] ?? [];
        try {
            $conn->beginTransaction();
            $stmt_del = $conn->prepare("DELETE FROM student_subjects WHERE student_id = ? AND class_id = ?");
            $stmt_del->execute([$student_id, $class_id]);
            if(!empty($subject_ids)){
                $stmt_ins = $conn->prepare("INSERT INTO student_subjects (student_id, class_id, subject_id) VALUES (?, ?, ?)");
                foreach($subject_ids as $sid){
                    $stmt_ins->execute([$student_id, $class_id, $sid]);
                }
            }
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Đăng ký môn học thành công']);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống']);
        }
        break;
}