<?php
require_once '../php/config.php';
require_once 'includes/functions.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$major_id = (int)$_GET['major_id'];
$year = (int)$_GET['year'];

// Lấy thông tin ngành
$major = $conn->query("SELECT * FROM majors WHERE id = $major_id")->fetch_assoc();

// Lấy danh sách kết quả
$results = $conn->query("
    SELECT r.fullname, r.phone, r.email, r.identification,
           d.total_score, am.name as method_name, sc.code as combo_code,
           CASE 
               WHEN d.total_score >= cs.score THEN 'Đậu'
               ELSE 'Trượt'
           END as result,
           cs.score as benchmark_score
    FROM registrations r
    INNER JOIN diemtuyensinh d ON r.id = d.registration_id
    INNER JOIN cutoff_scores cs ON r.major = cs.major_id 
        AND r.method = cs.method_code
        AND (r.combination_id = cs.combination_id OR cs.combination_id IS NULL)
    LEFT JOIN admission_methods am ON r.method = am.code
    LEFT JOIN subject_combinations sc ON r.combination_id = sc.id
    WHERE r.major = $major_id AND YEAR(r.created_at) = $year
    ORDER BY d.total_score DESC
");

// Xuất file Excel (sử dụng thư viện PHPExcel hoặc xuất CSV đơn giản)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ket_qua_xet_tuyen_' . $major['code'] . '_' . $year . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8

// Header
fputcsv($output, ['Họ tên', 'SĐT', 'Email', 'CMND/CCCD', 'Phương thức', 'Tổ hợp', 'Điểm', 'Điểm chuẩn', 'Kết quả']);

// Data
while ($row = $results->fetch_assoc()) {
    fputcsv($output, [
        $row['fullname'],
        $row['phone'],
        $row['email'],
        $row['identification'],
        $row['method_name'],
        $row['combo_code'] ?? 'Tất cả',
        number_format($row['total_score'], 2),
        number_format($row['benchmark_score'], 2),
        $row['result']
    ]);
}

fclose($output);