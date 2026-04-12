<?php
session_start();
require_once '../../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') exit;

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'fetch':
        $page = (int)($_POST['page'] ?? 1);
        $limit = 5; $offset = ($page - 1) * $limit;
        $search = $_POST['search'] ?? '';

        $sql = "SELECT * FROM subjects WHERE name LIKE :s ORDER BY id DESC LIMIT :l OFFSET :o";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':s', "%$search%"); $stmt->bindValue(':l', $limit, PDO::PARAM_INT); $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $count = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE name LIKE ?");
        $count->execute(["%$search%"]);
        
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'pagination' => ['total_pages' => ceil($count->fetchColumn() / $limit), 'current_page' => $page]]);
        break;

    case 'add':
        $stmt = $conn->prepare("INSERT INTO subjects (name) VALUES (?)");
        if($stmt->execute([$_POST['name']])) echo json_encode(['status' => 'success', 'message' => 'Đã thêm môn học']);
        break;

    case 'get_one':
        $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        break;

    case 'update':
        $stmt = $conn->prepare("UPDATE subjects SET name = ? WHERE id = ?");
        if($stmt->execute([$_POST['name'], $_POST['id']])) echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
        break;

    case 'delete':
        if($conn->prepare("DELETE FROM subjects WHERE id = ?")->execute([$_POST['id']])) echo json_encode(['status' => 'success', 'message' => 'Đã xóa môn học']);
        break;
}