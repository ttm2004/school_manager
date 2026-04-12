<?php
session_start();
require_once '../../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Hết phiên làm việc']);
    exit;
}

$action = $_POST['action'] ?? '';

function uploadNewsImage($file) {
    $dir = "../../../uploads/news/";
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $name = time() . '_' . rand(1000, 9999) . '.' . $ext;
    return move_uploaded_file($file["tmp_name"], $dir . $name) ? $name : null;
}

switch ($action) {
    case 'fetch':
        $page = (int)($_POST['page'] ?? 1);
        $limit = 5; $offset = ($page - 1) * $limit;
        $search = $_POST['search'] ?? '';
        $sql = "SELECT * FROM news WHERE title LIKE :s ORDER BY id DESC LIMIT :l OFFSET :o";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':s', "%$search%"); $stmt->bindValue(':l', $limit, PDO::PARAM_INT); $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $count = $conn->prepare("SELECT COUNT(*) FROM news WHERE title LIKE ?");
        $count->execute(["%$search%"]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'pagination' => ['total_pages' => ceil($count->fetchColumn() / $limit), 'current_page' => $page]]);
        break;

    case 'add':
        $img = (isset($_FILES['image']) && $_FILES['image']['error'] == 0) ? uploadNewsImage($_FILES['image']) : null;
        $stmt = $conn->prepare("INSERT INTO news (title, content, type, image_url) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$_POST['title'], $_POST['content'], $_POST['type'], $img])) {
            echo json_encode(['status' => 'success', 'message' => 'Đã đăng bài viết/slide mới']);
        }
        break;

    case 'get_one':
        $stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        break;

    case 'update':
        $sql = "UPDATE news SET title=?, content=?, type=?";
        $params = [$_POST['title'], $_POST['content'], $_POST['type']];
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $img = uploadNewsImage($_FILES['image']);
            if ($img) { $sql .= ", image_url=?"; $params[] = $img; }
        }
        $sql .= " WHERE id=?"; $params[] = $_POST['id'];
        if ($conn->prepare($sql)->execute($params)) {
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật nội dung thành công']);
        }
        break;

    case 'delete':
        if ($conn->prepare("DELETE FROM news WHERE id = ?")->execute([$_POST['id']])) {
            echo json_encode(['status' => 'success', 'message' => 'Nội dung đã được xóa']);
        }
        break;
}