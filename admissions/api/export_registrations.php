<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();

$status = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = []; $params = []; $types = '';
if ($status !== 'all') { $where[] = "r.status=?"; $params[] = $status; $types .= 's'; }
if ($search) {
    $where[] = "(r.fullname LIKE ? OR r.phone LIKE ? OR r.identification LIKE ?)";
    $like = "%$search%"; $params = array_merge($params, [$like,$like,$like]); $types .= 'sss';
}
$wSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$sql = "SELECT r.id, r.fullname, r.birthday, r.gender, r.identification, r.phone, r.email,
    r.address, r.graduation_year, r.school, r.status, r.created_at,
    m.major_name, m.major_code, am.method_name, s.total_score
    FROM adm_registrations r
    LEFT JOIN majors m ON r.major_id = m.id
    LEFT JOIN adm_methods am ON r.method_code = am.code
    LEFT JOIN adm_scores s ON r.id = s.registration_id
    $wSQL ORDER BY r.created_at DESC";

if ($params) { $stmt = $conn->prepare($sql); $stmt->bind_param($types,...$params); $stmt->execute(); $rows = $stmt->get_result(); }
else { $rows = $conn->query($sql); }

$statusLabels = ['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ho_so_tuyen_sinh_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($out, ['Mã HS','Họ tên','Ngày sinh','Giới tính','CCCD/CMND','SĐT','Email','Địa chỉ','Năm TN','Trường THPT','Ngành','Mã ngành','Phương thức','Tổng điểm','Trạng thái','Ngày đăng ký']);
while ($r = $rows->fetch_assoc()) {
    fputcsv($out, [
        str_pad($r['id'],6,'0',STR_PAD_LEFT),
        $r['fullname'], $r['birthday'], $r['gender'],
        $r['identification'], $r['phone'], $r['email'], $r['address'],
        $r['graduation_year'], $r['school'],
        $r['major_name'], $r['major_code'], $r['method_name'],
        $r['total_score'] ?? '',
        $statusLabels[$r['status']] ?? $r['status'],
        $r['created_at'],
    ]);
}
fclose($out);
