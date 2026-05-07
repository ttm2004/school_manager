<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');

// Tao file CSV mau de tai ve
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="mau_chuong_trinh_dao_tao.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
// BOM UTF-8 de Excel hien thi dung tieng Viet
fwrite($out, "\xEF\xBB\xBF");

// Header
fputcsv($out, ['subject_code','subject_name','credits','subject_type','semester_order','theory_periods','practice_periods','description']);

// Du lieu mau
$samples = [
    ['CNTT101','Nhap mon lap trinh',3,'required',1,30,30,'Mon hoc co ban ve tu duy lap trinh'],
    ['CNTT102','Co so du lieu',3,'required',2,30,30,'Mo hinh quan he SQL thiet ke CSDL'],
    ['CNTT103','Mang may tinh',3,'required',3,45,0,'Giao thuc TCP/IP mo hinh OSI'],
    ['CNTT201','Lap trinh Web',3,'required',4,30,30,'HTML CSS JavaScript PHP MySQL'],
    ['CNTT_CD1','Chuyen de tu chon 1',3,'elective',5,30,30,'Chu de chuyen sau tu chon'],
    ['DC_TTHCM','Tu tuong Ho Chi Minh',2,'general',1,30,0,'Tu tuong dao duc Ho Chi Minh'],
];
foreach ($samples as $row) fputcsv($out, $row);
fclose($out);
exit;
