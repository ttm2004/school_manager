<?php
require_once '../../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$major_id = $_GET['major_id'] ?? 0;

// Lấy thông tin ngành
$major = $conn->query("SELECT quota FROM majors WHERE id = $major_id")->fetch_assoc();
$quota = $major['quota'] ?? 100;

// Lấy danh sách điểm
$scores = $conn->query("
    SELECT d.total_score
    FROM registrations r
    INNER JOIN diemtuyensinh d ON r.id = d.registration_id
    WHERE r.major = $major_id
    ORDER BY d.total_score DESC
");

$score_list = [];
while ($row = $scores->fetch_assoc()) {
    $score_list[] = $row['total_score'];
}

$total = count($score_list);
$suggested_score = null;

if ($total > 0) {
    $rank = min($quota, $total);
    $suggested_score = $score_list[$rank - 1] ?? $score_list[count($score_list) - 1];
}

echo json_encode([
    'total_applicants' => $total,
    'quota' => $quota,
    'suggested_score' => $suggested_score ? number_format($suggested_score, 2) : null
]);