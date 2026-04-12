<?php
require_once '../php/config.php';

if (!isset($_GET['province_id'])) {
    echo json_encode([]);
    exit;
}

$province_id = intval($_GET['province_id']);

// Kiểm tra bảng districts đã có dữ liệu chưa
$check = $conn->query("SHOW TABLES LIKE 'districts'");
if ($check->num_rows == 0) {
    // Trả về dữ liệu mẫu nếu chưa có bảng
    $sample_districts = [
        ['id' => 1, 'name' => 'Quận Ba Đình'],
        ['id' => 2, 'name' => 'Quận Hoàn Kiếm'],
        ['id' => 3, 'name' => 'Quận Hai Bà Trưng'],
        ['id' => 4, 'name' => 'Quận Đống Đa'],
        ['id' => 5, 'name' => 'Quận Cầu Giấy'],
        ['id' => 6, 'name' => 'Quận Thanh Xuân'],
        ['id' => 7, 'name' => 'Quận Hoàng Mai'],
        ['id' => 8, 'name' => 'Quận Long Biên'],
        ['id' => 9, 'name' => 'Huyện Từ Liêm'],
        ['id' => 10, 'name' => 'Huyện Thanh Trì']
    ];
    header('Content-Type: application/json');
    echo json_encode($sample_districts);
    exit;
}

// Lấy dữ liệu từ database
$sql = "SELECT id, name FROM districts WHERE province_id = $province_id ORDER BY name";
$result = $conn->query($sql);

$districts = [];
while ($row = $result->fetch_assoc()) {
    $districts[] = $row;
}

header('Content-Type: application/json');
echo json_encode($districts);
?>