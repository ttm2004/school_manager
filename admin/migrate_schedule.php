<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');

$messages = [];

// Thêm cột schedule_data nếu chưa có
$check = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text")) {
        $messages[] = ['success', 'Đã thêm cột schedule_data vào bảng course_sections'];
    } else {
        $messages[] = ['danger', 'Lỗi thêm cột: ' . $conn->error];
    }
} else {
    $messages[] = ['info', 'Cột schedule_data đã tồn tại'];
}

// Dữ liệu lịch mẫu cho các lớp học phần
$schedules = [
    'CNTT101_01' => '[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]',
    'CNTT102_01' => '[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]',
    'CNTT201_01' => '[{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1},{"day":2,"session":"chieu","period_start":1}]',
    'CNTT202_01' => '[{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1},{"day":3,"session":"sang","period_start":1}]',
    'CNTT203_01' => '[{"day":6,"session":"chieu","period_start":1},{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1}]',
    'KTPM101_01' => '[{"day":2,"session":"chieu","period_start":1},{"day":4,"session":"chieu","period_start":1},{"day":6,"session":"chieu","period_start":1}]',
    'KTPM201_01' => '[{"day":3,"session":"sang","period_start":1},{"day":5,"session":"sang","period_start":1},{"day":7,"session":"sang","period_start":1}]',
    'QTKD101_01' => '[{"day":5,"session":"sang","period_start":1},{"day":7,"session":"sang","period_start":1},{"day":3,"session":"chieu","period_start":1}]',
    'KT101_01'   => '[{"day":6,"session":"sang","period_start":1},{"day":2,"session":"sang","period_start":6},{"day":4,"session":"sang","period_start":6}]',
    'NNA101_01'  => '[{"day":7,"session":"sang","period_start":1},{"day":3,"session":"toi","period_start":1},{"day":5,"session":"toi","period_start":1}]',
];

foreach ($schedules as $code => $data) {
    $stmt = $conn->prepare("UPDATE course_sections SET schedule_data=? WHERE section_code=?");
    $stmt->bind_param('ss', $data, $code);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $messages[] = ['success', "✅ Đã cập nhật lịch cho: <strong>$code</strong>"];
    } else {
        $messages[] = ['warning', "⚠ Không tìm thấy hoặc không cần cập nhật: $code"];
    }
    $stmt->close();
}

$messages[] = ['success', 'Migration hoàn tất! <a href="/university/admin/course_sections.php" class="alert-link">Quay lại quản lý lớp học phần</a>'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Migration - Thêm lịch học</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container" style="max-width:700px">
    <h4 class="mb-4"><i class="bi bi-database-gear me-2"></i>Migration: Thêm cột lịch học</h4>
    <?php foreach ($messages as [$type, $msg]): ?>
    <div class="alert alert-<?php echo $type; ?>"><?php echo $msg; ?></div>
    <?php endforeach; ?>
    <a href="/university/admin/course_sections.php" class="btn btn-primary">Quay lại</a>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>
