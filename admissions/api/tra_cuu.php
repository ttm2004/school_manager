<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['identification'], $_GET['birthday'])) {
    adm_json(false, 'Thiếu thông tin tra cứu');
}

$identification = adm_sanitize($_GET['identification']);
$birthday       = adm_sanitize($_GET['birthday']);

$stmt = $conn->prepare("
    SELECT r.*,
        m.major_name, m.major_code,
        am.method_name,
        sc.code as combo_code, sc.name as combo_name,
        p.name as province_name, d.name as district_name,
        ar.status as result_status, ar.total_score as result_score, ar.cutoff_score, ar.created_at as result_time,
        ac.status as confirm_status, ac.confirmed_at, ac.expiry_date
    FROM adm_registrations r
    LEFT JOIN majors m ON r.major_id = m.id
    LEFT JOIN adm_methods am ON r.method_code = am.code
    LEFT JOIN adm_subject_combinations sc ON r.combination_id = sc.id
    LEFT JOIN adm_provinces p ON r.province_id = p.id
    LEFT JOIN adm_districts d ON r.district_id = d.id
    LEFT JOIN adm_results ar ON r.id = ar.registration_id
    LEFT JOIN adm_confirmations ac ON r.id = ac.registration_id
    WHERE r.identification = ? AND DATE(r.birthday) = ?
    ORDER BY r.created_at DESC LIMIT 1
");
$stmt->bind_param('ss', $identification, $birthday);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    adm_json(false, 'Không tìm thấy hồ sơ. Vui lòng kiểm tra lại CCCD và ngày sinh.');
}

// Scores
$scores = [];
$sr = $conn->query("SELECT * FROM adm_scores WHERE registration_id={$row['id']}")->fetch_assoc();
if ($sr) {
    $scoreData = json_decode($sr['score_data'], true);
    foreach ($scoreData as $k => $v) {
        $labels = ['math'=>'Toán','literature'=>'Văn','english'=>'Anh','physic'=>'Vật lý','chemistry'=>'Hóa','biology'=>'Sinh','dgnl'=>'ĐGNL'];
        $scores[] = ['subject' => $labels[$k] ?? ucfirst($k), 'score' => $v];
    }
}

// Status display
$statusMap = [
    'pending'  => ['text'=>'Đang xử lý', 'class'=>'status-pending'],
    'approved' => ['text'=>'Đã duyệt hồ sơ', 'class'=>'status-approved'],
    'rejected' => ['text'=>'Từ chối hồ sơ', 'class'=>'status-rejected'],
];
if ($row['result_status'] === 'passed') {
    $displayStatus = ['text'=>'Trúng tuyển 🎉', 'class'=>'status-admitted'];
} elseif ($row['result_status'] === 'failed') {
    $displayStatus = ['text'=>'Không trúng tuyển', 'class'=>'status-rejected'];
} else {
    $displayStatus = $statusMap[$row['status']] ?? ['text'=>'Đang xử lý','class'=>'status-pending'];
}

// Timeline
$timeline = [['date' => date('d/m/Y H:i', strtotime($row['created_at'])), 'title' => 'Tiếp nhận hồ sơ', 'desc' => 'Hồ sơ đã được tiếp nhận thành công']];
if ($row['result_status']) {
    $t = $row['result_time'] ? date('d/m/Y H:i', strtotime($row['result_time'])) : date('d/m/Y');
    $timeline[] = ['date' => $t, 'title' => $row['result_status']==='passed' ? 'Trúng tuyển' : 'Không trúng tuyển',
        'desc' => $row['result_status']==='passed' ? 'Chúc mừng bạn đã trúng tuyển!' : 'Bạn chưa đủ điều kiện trúng tuyển'];
}
if ($row['confirm_status'] === 'confirmed') {
    $timeline[] = ['date' => date('d/m/Y H:i', strtotime($row['confirmed_at'])), 'title' => 'Xác nhận nhập học', 'desc' => 'Đã xác nhận nhập học thành công'];
}

// Address
$addr = $row['address'] ?? '';
if ($row['district_name']) $addr .= ', ' . $row['district_name'];
if ($row['province_name']) $addr .= ', ' . $row['province_name'];

// Admission result
$admissionResult = null;
if ($row['result_status']) {
    $admissionResult = [
        'status'       => $row['result_status'],
        'score'        => $row['result_score'],
        'cutoff_score' => $row['cutoff_score'],
        'confirmation' => $row['confirm_status'] ? [
            'status'       => $row['confirm_status'],
            'confirmed_at' => $row['confirmed_at'],
            'expiry_date'  => $row['expiry_date'],
        ] : null,
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'id'              => $row['id'],
        'registration_id' => str_pad($row['id'], 8, '0', STR_PAD_LEFT),
        'fullname'        => $row['fullname'],
        'birthday'        => $row['birthday'],
        'identification'  => $row['identification'],
        'phone'           => $row['phone'],
        'email'           => $row['email'],
        'address'         => $addr,
        'school'          => $row['school'],
        'graduation_year' => $row['graduation_year'],
        'major_name'      => $row['major_name'],
        'major_code'      => $row['major_code'],
        'method_name'     => $row['method_name'],
        'combination_name'=> $row['combo_code'] ? $row['combo_code'].' - '.$row['combo_name'] : null,
        'status'          => $row['status'],
        'status_text'     => $displayStatus['text'],
        'status_class'    => $displayStatus['class'],
        'scores'          => $scores,
        'admission_result'=> $admissionResult,
        'timeline'        => $timeline,
        'created_at'      => $row['created_at'],
    ]
], JSON_UNESCAPED_UNICODE);
