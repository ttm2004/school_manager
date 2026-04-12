<?php
session_start();
require_once '../../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'fetch':
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = 5; 
        $offset = ($page - 1) * $limit;
        $search = isset($_POST['search']) ? $_POST['search'] : '';

        $sql = "SELECT * FROM departments WHERE name LIKE :search ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql_count = "SELECT COUNT(*) FROM departments WHERE name LIKE :search";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->execute([':search' => "%$search%"]);
        $total_records = $stmt_count->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages
            ]
        ]);
        break;

    case 'add':
        $name = $_POST['name'];
        $desc = $_POST['description'];
        
        $stmt = $conn->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $desc])) {
            echo json_encode(['status' => 'success', 'message' => 'Thêm mới thành công']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống']);
        }
        break;

    case 'get_one':
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'update':
        $id = $_POST['id'];
        $name = $_POST['name'];
        $desc = $_POST['description'];
        
        $stmt = $conn->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$name, $desc, $id])) {
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống']);
        }
        break;

    case 'delete':
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
            if ($stmt->execute([$id])) {
                echo json_encode(['status' => 'success', 'message' => 'Đã xóa thành công']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Không thể xóa vì dữ liệu đang được sử dụng']);
        }
        break;
}
?>