<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
header('Content-Type: application/json');

$majorId = intval($_GET['major_id'] ?? 0);
$year    = intval($_GET['year'] ?? date('Y'));

$quota = $conn->query("SELECT quota FROM adm_quota WHERE major_id=$majorId AND year=$year")->fetch_assoc()['quota'] ?? 100;

$scores = $conn->query("
    SELECT s.total_score FROM adm_registrations r
    JOIN adm_scores s ON r.id = s.registration_id
    WHERE r.major_id = $majorId AND YEAR(r.created_at) = $year AND s.total_score IS NOT NULL
    ORDER BY s.total_score DESC
");

$list = [];
while ($row = $scores->fetch_assoc()) $list[] = $row['total_score'];
$total = count($list);
$suggested = null;
if ($total > 0) {
    $rank = min($quota, $total);
    $suggested = number_format($list[$rank-1], 2);
}

echo json_encode(['total' => $total, 'quota' => $quota, 'suggested' => $suggested], JSON_UNESCAPED_UNICODE);
