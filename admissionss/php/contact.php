<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate required fields
    $errors = [];
    
    if (empty($_POST['name'])) {
        $errors[] = "Vui lòng nhập họ tên";
    }
    
    if (empty($_POST['email'])) {
        $errors[] = "Vui lòng nhập email";
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ";
    }
    
    if (empty($_POST['message'])) {
        $errors[] = "Vui lòng nhập nội dung";
    }
    
    if (empty($errors)) {
        // Sanitize inputs
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
        $subject = isset($_POST['subject']) ? sanitize($_POST['subject']) : 'Liên hệ tuyển sinh';
        $message = sanitize($_POST['message']);
        
        // Insert into database
        $sql = "INSERT INTO contacts (name, email, phone, subject, message) 
                VALUES ('$name', '$email', '$phone', '$subject', '$message')";
        
        if ($conn->query($sql)) {
            // Send email to admin
            $to = ADMIN_EMAIL;
            $email_subject = "Liên hệ tuyển sinh từ $name";
            
            $email_message = "
            <html>
            <head>
                <title>Liên hệ tuyển sinh</title>
            </head>
            <body>
                <h2>Thông tin liên hệ</h2>
                <p><strong>Họ tên:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Số điện thoại:</strong> $phone</p>
                <p><strong>Tiêu đề:</strong> $subject</p>
                <p><strong>Nội dung:</strong></p>
                <p>$message</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: $name <$email>" . "\r\n";
            
            mail($to, $email_subject, $email_message, $headers);
            
            echo json_encode([
                'success' => true,
                'message' => 'Tin nhắn đã được gửi thành công!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $conn->error
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => implode('<br>', $errors)
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không hợp lệ'
    ]);
}

$conn->close();
?>