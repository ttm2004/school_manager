<?php

$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

$sql = "SELECT 
        COUNT(*) as total,
        SUM(status = 'pending') as pending,
        SUM(status = 'approved') as approved,
        SUM(status = 'rejected') as rejected
        FROM registrations";

$result = $conn->query($sql);

if ($result) {
    $stats = $result->fetch_assoc();
}
?>