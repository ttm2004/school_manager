<?php
require_once '../../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$major_id = $_GET['id'] ?? 0;

// Lấy thông tin ngành
$major = $conn->query("SELECT * FROM majors WHERE id = $major_id")->fetch_assoc();

// Lấy danh sách thí sinh
$applicants = $conn->query("
    SELECT r.fullname, r.method, sc.code as combination, d.total_score
    FROM registrations r
    LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
    LEFT JOIN subject_combinations sc ON r.combination_id = sc.id
    WHERE r.major = $major_id AND d.total_score IS NOT NULL
    ORDER BY d.total_score DESC
");

$applicants_list = [];
while ($row = $applicants->fetch_assoc()) {
    $applicants_list[] = $row;
}

// Thống kê
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_applicants,
        COUNT(d.id) as has_score,
        AVG(d.total_score) as avg_score,
        MAX(d.total_score) as max_score,
        MIN(d.total_score) as min_score
    FROM registrations r
    LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
    WHERE r.major = $major_id
")->fetch_assoc();

echo json_encode([
    'code' => $major['code'],
    'name' => $major['name'],
    'total_applicants' => $stats['total_applicants'],
    'has_score' => $stats['has_score'],
    'avg_score' => number_format($stats['avg_score'] ?? 0, 2),
    'max_score' => number_format($stats['max_score'] ?? 0, 2),
    'min_score' => number_format($stats['min_score'] ?? 0, 2),
    'applicants' => $applicants_list
],JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);