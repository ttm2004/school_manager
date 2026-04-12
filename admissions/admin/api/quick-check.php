<?php
require_once '../../php/config.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$registration_id = (int)$data['id'];

// Lấy thông tin thí sinh và điểm chuẩn
$result = $conn->query("
    SELECT r.*, d.total_score, cs.score as benchmark_score,
           cs.method_code, cs.combination_id,
           am.name as method_name
    FROM registrations r
    INNER JOIN diemtuyensinh d ON r.id = d.registration_id
    INNER JOIN cutoff_scores cs ON r.major = cs.major_id 
        AND r.method = cs.method_code
        AND (r.combination_id = cs.combination_id OR cs.combination_id IS NULL)
    LEFT JOIN admission_methods am ON cs.method_code = am.code
    WHERE r.id = $registration_id AND cs.year = YEAR(r.created_at)
");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $passed = $row['total_score'] >= $row['benchmark_score'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'fullname' => $row['fullname'],
            'total_score' => number_format($row['total_score'], 2),
            'benchmark_score' => number_format($row['benchmark_score'], 2),
            'method' => $row['method_name'],
            'result' => $passed ? 'ĐẬU' : 'TRƯỢT',
            'note' => $passed ? 'Chúc mừng bạn đã trúng tuyển' : 'Rất tiếc bạn chưa đủ điểm'
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy thông tin hoặc chưa có điểm chuẩn'
    ]);
}