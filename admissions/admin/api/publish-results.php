<?php
require_once '../../php/config.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$major_id = (int)$data['major_id'];
$year = (int)$data['year'];

// Cập nhật trạng thái công bố (cần thêm cột published vào bảng registrations hoặc tạo bảng riêng)
// Giả sử có bảng admission_results
$conn->query("
    INSERT INTO admission_results (registration_id, status, published_at)
    SELECT r.id, 
           CASE 
               WHEN d.total_score >= cs.score THEN 'passed'
               ELSE 'failed'
           END as status,
           NOW()
    FROM registrations r
    INNER JOIN diemtuyensinh d ON r.id = d.registration_id
    INNER JOIN cutoff_scores cs ON r.major = cs.major_id 
        AND r.method = cs.method_code
        AND (r.combination_id = cs.combination_id OR cs.combination_id IS NULL)
    WHERE r.major = $major_id AND YEAR(r.created_at) = $year
    ON DUPLICATE KEY UPDATE status = VALUES(status), published_at = NOW()
");

echo json_encode(['success' => true, 'message' => 'Đã công bố kết quả thành công']);