<?php
session_start();
require_once '../../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$major_id = intval($_GET['major_id']);
$year = intval($_GET['year']);

// Lấy thông tin ngành
$major = $conn->query("SELECT * FROM majors WHERE id = $major_id")->fetch_assoc();

// Lấy danh sách thí sinh
$sql = "SELECT r.*, d.total_score, m.name as major_name, m.code as major_code,
        CASE 
            WHEN d.total_score >= cs.score THEN 'Đậu'
            ELSE 'Trượt'
        END as result
        FROM registrations r
        LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
        LEFT JOIN cutoff_scores cs ON r.major = cs.major_id AND cs.year = $year
        JOIN majors m ON r.major = m.id
        WHERE r.major = $major_id AND YEAR(r.created_at) = $year
        ORDER BY d.total_score DESC";

$result = $conn->query($sql);

// Set headers cho file Excel
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ketqua_' . $major['code'] . '_' . $year . '.csv"');

// Tạo file CSV
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM cho UTF-8

// Header
fputcsv($output, ['STT', 'Mã HS', 'Họ tên', 'CCCD/CMND', 'SĐT', 'Ngành', 'Tổng điểm', 'Kết quả']);

// Dữ liệu
$stt = 1;
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $stt++,
        '#' . str_pad($row['id'], 6, '0', STR_PAD_LEFT),
        $row['fullname'],
        $row['identification'],
        $row['phone'],
        $row['major_name'],
        number_format($row['total_score'] ?? 0, 2),
        $row['result'] ?? 'Chưa xét'
    ]);
}

fclose($output);
?>