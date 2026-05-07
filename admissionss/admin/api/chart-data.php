<?php
require_once '../../php/config.php';

$range = isset($_GET['range']) ? intval($_GET['range']) : 7;

$data = [
    'labels' => [],
    'data' => []
];

for ($i = $range - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = $conn->query("SELECT COUNT(*) as count FROM registrations WHERE DATE(created_at) = '$date'");
    $count = $result->fetch_assoc()['count'];
    
    $data['labels'][] = date('d/m', strtotime($date));
    $data['data'][] = (int)$count;
}

header('Content-Type: application/json');
echo json_encode($data);
?>