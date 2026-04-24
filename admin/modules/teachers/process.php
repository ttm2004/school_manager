<?php
session_start();
require_once '../../../config/db.php';

/** @var PDO $conn */

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

function uploadAvatar($file) {
    $target_dir = "../../../uploads/avatars/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed)) return null;
    $new_name = time() . '_' . rand(1000, 9999) . '.' . $extension;
    if (move_uploaded_file($file["tmp_name"], $target_dir . $new_name)) return $new_name;
    return null;
}

switch ($action) {
    case 'fetch':
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = 5; 
        $offset = ($page - 1) * $limit;
        $search = $_POST['search'] ?? '';
        
        $sql = "SELECT * FROM users WHERE role='teacher' AND (full_name LIKE :search OR username LIKE :search OR phone LIKE :search) ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':search', "%$search%"); 
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); 
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='teacher' AND (full_name LIKE :search OR username LIKE :search)");
        $total->execute([':search' => "%$search%"]);
        
        echo json_encode([
            'status' => 'success', 
            'data' => $data, 
            'pagination' => [
                'current_page' => $page, 
                'total_pages' => ceil($total->fetchColumn() / $limit)
            ]
        ]);
        break;

    case 'add':
        $username = $_POST['username'];
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->rowCount() > 0) { 
            echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập đã tồn tại!']); 
            exit; 
        }
        
        $avatar = (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) ? uploadAvatar($_FILES['avatar']) : null;
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, address, role, avatar) VALUES (?, ?, ?, ?, ?, ?, 'teacher', ?)");
        if ($stmt->execute([$_POST['username'], $_POST['password'], $_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $avatar])) 
            echo json_encode(['status' => 'success', 'message' => 'Thêm thành công']);
        else 
            echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống']);
        break;

    case 'get_one':
        $id = $_POST['id'];
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt_hr = $conn->prepare("SELECT name FROM classes WHERE teacher_id = ?");
        $stmt_hr->execute([$id]);
        $homeroom_classes = $stmt_hr->fetchAll(PDO::FETCH_COLUMN);

        $stmt_sub = $conn->prepare("
            SELECT c.name as class_name, s.name as subject_name 
            FROM class_subject_teachers cst
            JOIN classes c ON cst.class_id = c.id
            JOIN subjects s ON cst.subject_id = s.id
            WHERE cst.teacher_id = ?
        ");
        $stmt_sub->execute([$id]);
        $subject_classes = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success', 
            'data' => $user,
            'homeroom' => $homeroom_classes,
            'teaching' => $subject_classes
        ]);
        break;

    case 'update':
        $id = $_POST['id'];
        $sql = "UPDATE users SET full_name=?, email=?, phone=?, address=?";
        $params = [$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['address']];
        
        if (!empty($_POST['password'])) { 
            $sql .= ", password=?"; 
            $params[] = $_POST['password']; 
        }
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $avatar = uploadAvatar($_FILES['avatar']);
            if ($avatar) { 
                $sql .= ", avatar=?"; 
                $params[] = $avatar; 
            }
        }
        
        $sql .= " WHERE id=?"; 
        $params[] = $id;
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute($params)) 
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
        else 
            echo json_encode(['status' => 'error', 'message' => 'Lỗi cập nhật']);
        break;

    case 'delete':
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$_POST['id']])) 
                echo json_encode(['status' => 'success', 'message' => 'Đã xóa']);
        } catch (Exception $e) { 
            echo json_encode(['status' => 'error', 'message' => 'Dữ liệu ràng buộc, không thể xóa']); 
        }
        break;
}
?>