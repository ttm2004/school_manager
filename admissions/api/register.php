<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') adm_json(false, 'Phương thức không hợp lệ');

// Xác minh CSRF token
$csrfToken = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCSRFToken($csrfToken)) {
    adm_json(false, 'Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.');
}

// Required fields
$required = ['fullname','birthday','phone','email','identification','address','graduation_year','school','major_id','method_code'];
foreach ($required as $f) {
    if (empty($_POST[$f])) adm_json(false, "Thiếu thông tin: $f");
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) adm_json(false, 'Email không hợp lệ');
if (!preg_match('/^0[0-9]{9}$/', $_POST['phone'])) adm_json(false, 'Số điện thoại không hợp lệ (10 số, bắt đầu bằng 0)');
if (!preg_match('/^[0-9]{9,12}$/', $_POST['identification'])) adm_json(false, 'CCCD/CMND không hợp lệ');

$fullname       = trim($_POST['fullname']);
$birthday       = $_POST['birthday'];
$gender         = trim($_POST['gender'] ?? '');
$phone          = trim($_POST['phone']);
$email          = trim($_POST['email']);
$identification = trim($_POST['identification']);
$address        = trim($_POST['address']);
$graduationYear = intval($_POST['graduation_year']);
$school         = trim($_POST['school']);
$majorId        = intval($_POST['major_id']);
$methodCode     = trim($_POST['method_code']);
$comboId        = !empty($_POST['combination_id']) ? intval($_POST['combination_id']) : null;
$provinceId     = !empty($_POST['province_id']) ? intval($_POST['province_id']) : null;
$districtId     = !empty($_POST['district_id']) ? intval($_POST['district_id']) : null;
$notes          = trim($_POST['notes'] ?? '');
$ip             = $_SERVER['REMOTE_ADDR'] ?? '';

$conn->begin_transaction();
try {
    // Duplicate checks
    foreach (['email'=>$email,'phone'=>$phone,'identification'=>$identification] as $col => $val) {
        $c = $conn->prepare("SELECT id FROM adm_registrations WHERE $col=?");
        $c->bind_param('s', $val); $c->execute();
        if ($c->get_result()->num_rows > 0) throw new Exception(ucfirst($col === 'identification' ? 'CCCD/CMND' : $col) . ' đã được đăng ký');
    }

    // Insert registration
    $stmt = $conn->prepare("INSERT INTO adm_registrations
        (fullname,birthday,gender,phone,email,identification,address,graduation_year,school,major_id,method_code,combination_id,province_id,district_id,notes,ip_address)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sssssssisssiiiss',
        $fullname,$birthday,$gender,$phone,$email,$identification,$address,
        $graduationYear,$school,$majorId,$methodCode,$comboId,$provinceId,$districtId,$notes,$ip);
    if (!$stmt->execute()) throw new Exception('Lỗi lưu hồ sơ: ' . $conn->error);
    $regId = $conn->insert_id;

    // Insert scores
    if (!empty($_POST['scores']) && is_array($_POST['scores'])) {
        $scores = $_POST['scores'];
        $scoreData = json_encode($scores, JSON_UNESCAPED_UNICODE);
        $total = 0;
        switch ($methodCode) {
            case 'thpt': $total = ($scores['math']??0) + ($scores['physic']??0) + ($scores['chemistry']??0); break;
            case 'hocba': $vals = array_filter($scores,'is_numeric'); $total = count($vals) ? round(array_sum($vals)/count($vals),2) : 0; break;
            case 'dgnl': $total = floatval($scores['dgnl']??0); break;
            default: $total = array_sum(array_filter($scores,'is_numeric'));
        }
        $ss = $conn->prepare("INSERT INTO adm_scores (registration_id, method_code, score_data, total_score) VALUES (?,?,?,?)");
        $ss->bind_param('issd', $regId, $methodCode, $scoreData, $total);
        $ss->execute();
    }

    // File upload
    if (!empty($_FILES['transcript']['name'])) {
        $ext = strtolower(pathinfo($_FILES['transcript']['name'], PATHINFO_EXTENSION));

        // Kiểm tra MIME type thực của file (không chỉ dựa vào extension)
        $allowedMimes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['transcript']['tmp_name']);
        finfo_close($finfo);

        $mimeValid = isset($allowedMimes[$ext]) && $allowedMimes[$ext] === $mimeType;

        if (in_array($ext, ADM_ALLOWED_EXT) && $mimeValid && $_FILES['transcript']['size'] <= ADM_MAX_FILE_SIZE) {
            // Tên file an toàn: chỉ dùng regId + timestamp, không dùng tên gốc từ user
            $fname = 'transcript_' . $regId . '_' . time() . '.' . $ext;
            $destPath = ADM_UPLOAD_DIR . 'registrations/' . $fname;
            if (move_uploaded_file($_FILES['transcript']['tmp_name'], $destPath)) {
                // Dùng prepared statement thay vì query trực tiếp
                $upStmt = $conn->prepare("UPDATE adm_registrations SET transcript_file=? WHERE id=?");
                if ($upStmt) {
                    $upStmt->bind_param('si', $fname, $regId);
                    $upStmt->execute();
                    $upStmt->close();
                }
            }
        }
    }

    $conn->commit();
    adm_json(true, 'Đăng ký thành công! Mã hồ sơ: ' . str_pad($regId, 8, '0', STR_PAD_LEFT), ['id' => $regId]);
} catch (Exception $e) {
    $conn->rollback();
    adm_json(false, $e->getMessage());
}
