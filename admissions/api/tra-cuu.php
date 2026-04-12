<?php
require_once '../php/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Không tìm thấy hồ sơ'];

if (isset($_GET['identification']) && isset($_GET['birthday'])) {

    $identification = sanitize($_GET['identification']);
    $birthday = sanitize($_GET['birthday']);

    $sql = "SELECT r.*, 
            m.name as major_name, m.code as major_code,
            am.name as method_name,
            sc.code as combination_code, sc.name as combination_name,
            p.name as province_name, d.name as district_name,

            ar.status as admission_status,
            ar.total_score as admission_score,
            ar.created_at as admission_time,

            ac.status as confirm_status,
            ac.confirmed_at,
            ac.expiry_date

            FROM registrations r
            LEFT JOIN majors m ON r.major = m.id
            LEFT JOIN admission_methods am ON r.method = am.code
            LEFT JOIN subject_combinations sc ON r.combination_id = sc.id
            LEFT JOIN provinces p ON r.province_id = p.id
            LEFT JOIN districts d ON r.district_id = d.id

            LEFT JOIN admission_results ar ON r.id = ar.registration_id
            LEFT JOIN admission_confirmation ac ON r.id = ac.registration_id

            WHERE r.identification = ? AND DATE(r.birthday) = ?
            ORDER BY r.created_at DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("SQL ERROR: " . $conn->error);
    }

    $stmt->bind_param("ss", $identification, $birthday);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $response = formatResponse($row, $conn);
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);


// ================= FORMAT =================

function formatResponse($row, $conn)
{
    // ===== SCORE =====
    $scores = [];
    $score_sql = "SELECT * FROM diemtuyensinh WHERE registration_id = ?";
    $score_stmt = $conn->prepare($score_sql);
    $score_stmt->bind_param("i", $row['id']);
    $score_stmt->execute();
    $score_result = $score_stmt->get_result();

    if ($score_data = $score_result->fetch_assoc()) {
        $score_info = json_decode($score_data['score_data'], true);

        if ($row['method'] == 'thpt') {
            $scores = [
                ['subject' => 'Toán', 'score' => $score_info['math'] ?? 0],
                ['subject' => 'Vật lý', 'score' => $score_info['physic'] ?? 0],
                ['subject' => 'Hóa học', 'score' => $score_info['chemistry'] ?? 0]
            ];
        } elseif ($row['method'] == 'dgnl') {
            $scores = [
                ['subject' => 'ĐGNL', 'score' => $score_info['dgnl'] ?? 0]
            ];
        }
    }

    // ===== ADDRESS =====
    $address = $row['address'];
    if (!empty($row['district_name'])) $address .= ', ' . $row['district_name'];
    if (!empty($row['province_name'])) $address .= ', ' . $row['province_name'];

    // ===== STATUS =====
    if (!empty($row['admission_status'])) {

        if ($row['admission_status'] == 'passed') {
            $display_status = 'admitted';
            $display_text = 'Đã trúng tuyển';
            $display_class = 'status-approved';
        } else {
            $display_status = 'rejected';
            $display_text = 'Không trúng tuyển';
            $display_class = 'status-rejected';
        }

    } else {
        $status_text = [
            'pending' => 'Đang xử lý',
            'approved' => 'Đã duyệt hồ sơ',
            'rejected' => 'Từ chối hồ sơ'
        ];

        $status_class = [
            'pending' => 'status-pending',
            'approved' => 'status-approved',
            'rejected' => 'status-rejected'
        ];

        $display_status = $row['status'];
        $display_text = $status_text[$row['status']] ?? '';
        $display_class = $status_class[$row['status']] ?? '';
    }

    // ===== ADMISSION RESULT =====
    $admission_result = null;

    if (!empty($row['admission_status'])) {

        $status = ($row['admission_status'] == 'passed') ? 'admitted' : 'rejected';

        $admission_result = [
            'status' => $status,
            'score' => $row['admission_score'],
            'confirmation' => null
        ];

        if (!empty($row['confirm_status'])) {
            $admission_result['confirmation'] = [
                'status' => $row['confirm_status'],
                'confirmed_at' => $row['confirmed_at'],
                'expiry_date' => $row['expiry_date']
            ];
        }
    }

    // ===== TIMELINE =====
    $timeline = [];

    // 1. tiếp nhận hồ sơ
    $timeline[] = [
        'date' => date('d/m/Y H:i', strtotime($row['created_at'])),
        'title' => 'Đã tiếp nhận hồ sơ',
        'description' => 'Hồ sơ đã được tiếp nhận'
    ];

    // 2. kết quả xét tuyển (FIX CHUẨN)
    if (!empty($row['admission_status'])) {

        $admission_time = !empty($row['admission_time']) 
            ? strtotime($row['admission_time']) 
            : time();

        if ($row['admission_status'] == 'passed') {
            $timeline[] = [
                'date' => date('d/m/Y H:i', $admission_time),
                'title' => 'Đã trúng tuyển',
                'description' => 'Chúc mừng bạn đã trúng tuyển 🎉'
            ];
        } else {
            $timeline[] = [
                'date' => date('d/m/Y H:i', $admission_time),
                'title' => 'Không trúng tuyển',
                'description' => 'Bạn chưa đủ điều kiện trúng tuyển'
            ];
        }
    }

    // 3. xác nhận nhập học
    if (!empty($row['confirm_status'])) {

        if ($row['confirm_status'] == 'confirmed') {
            $timeline[] = [
                'date' => date('d/m/Y H:i', strtotime($row['confirmed_at'])),
                'title' => 'Đã xác nhận nhập học',
                'description' => 'Bạn đã xác nhận nhập học thành công'
            ];
        } elseif ($row['confirm_status'] == 'expired') {
            $timeline[] = [
                'date' => date('d/m/Y H:i'),
                'title' => 'Hết hạn xác nhận',
                'description' => 'Bạn đã quá thời hạn xác nhận nhập học'
            ];
        }
    }

    return [
        'success' => true,
        'data' => [
            'id' => $row['id'],
            'registration_id' => str_pad($row['id'], 8, '0', STR_PAD_LEFT),
            'fullname' => $row['fullname'],
            'birthday' => $row['birthday'],
            'identification' => $row['identification'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'address' => $address,
            'school' => $row['school'],
            'graduation_year' => $row['graduation_year'],
            'major_name' => $row['major_name'],
            'major_code' => $row['major_code'],
            'method_name' => $row['method_name'],
            'combination_name' => $row['combination_code'] . ' - ' . $row['combination_name'],

            'status_text' => $display_text,
            'status_class' => $display_class,
            'status' => $display_status,

            'admission_result' => $admission_result,

            'scores' => $scores,
            'timeline' => $timeline,

            'created_at' => $row['created_at']
        ]
    ];
}