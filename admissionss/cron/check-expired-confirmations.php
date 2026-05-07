<?php
require_once '../php/config.php';

// Cập nhật các xác nhận đã hết hạn
$sql = "UPDATE admission_confirmation 
        SET status = 'expired' 
        WHERE status = 'pending' AND expiry_date < NOW()";

if ($conn->query($sql)) {
    echo date('Y-m-d H:i:s') . " - Đã cập nhật " . $conn->affected_rows . " bản ghi hết hạn\n";
    
    // Lấy danh sách các thí sinh bị hết hạn để gửi email thông báo
    $expired_sql = "SELECT ac.*, r.email 
                    FROM admission_confirmation ac
                    JOIN registrations r ON ac.registration_id = r.id
                    WHERE ac.status = 'expired' AND ac.expiry_date < NOW() - INTERVAL 1 DAY";
    
    $expired_result = $conn->query($expired_sql);
    
    while ($row = $expired_result->fetch_assoc()) {
        // Gửi email thông báo (tùy chọn)
        // sendExpiredNotification($row['email'], $row['fullname']);
        
        echo "  - Thí sinh: {$row['fullname']} (ID: {$row['registration_id']})\n";
    }
}

// Ghi log
$log = date('Y-m-d H:i:s') . " - Checked expired confirmations: " . $conn->affected_rows . " records updated\n";
file_put_contents('cron_log.txt', $log, FILE_APPEND);