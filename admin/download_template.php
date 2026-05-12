<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="mau_chuong_trinh_dao_tao.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'STT',
    'Mã MH',
    'Tên môn học',
    'Chuyên ngành',
    'Số tín chỉ',
    'Môn bắt buộc',
    'Đã học',
    'Tổng tiết',
    'Lý thuyết',
    'Thực hành',
    'Học kỳ',
    'Năm học',
]);

$samples = [
    [1, 'KETO023', 'Nhập môn ngành Kế toán', 'Kế toán', 2, 'Có', 'Có', 60, 0, 60, 'Học kỳ 1', '2022-2023'],
    [2, 'LING127', 'Luật kinh tế', 'Kế toán', 2, 'Có', 'Có', 30, 30, 0, 'Học kỳ 1', '2022-2023'],
    [3, 'KTCH001', 'Phương pháp nghiên cứu khoa học', 'Kế toán', 3, 'Có', 'Có', 45, 45, 0, 'Học kỳ 2', '2022-2023'],
    [4, 'KETO010', 'Kế toán tài chính 1', 'Kế toán', 2, 'Có', 'Có', 30, 30, 0, 'Học kỳ 1', '2023-2024'],
    [5, 'KETO005', 'Kế toán excel', 'Kế toán', 2, 'Không', 'Không', 60, 0, 60, 'Học kỳ 2', '2024-2025'],
];

foreach ($samples as $row) {
    fputcsv($out, $row);
}

fclose($out);
exit;
