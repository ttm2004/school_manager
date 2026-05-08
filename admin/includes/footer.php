<?php
// Footer dùng chung cho tất cả trang admin
// Include widget thống kê truy cập (fixed position, không ảnh hưởng layout)
if (function_exists('isLoggedIn') && isLoggedIn()) {
    include_once __DIR__ . '/../../includes/analytics_widget.php';
}
?>
