<?php
require_once '../php/config.php';

if (isset($_GET['email'])) {
    $email = sanitize($_GET['email']);
    $check = $conn->query("SELECT id FROM registrations WHERE email = '$email'");
    
    header('Content-Type: application/json');
    echo json_encode([
        'exists' => $check->num_rows > 0
    ]);
} else {
    echo json_encode(['exists' => false]);
}
?>