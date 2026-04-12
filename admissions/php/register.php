<?php
require_once 'config.php';

header('Content-Type: application/json');

// bật debug khi cần
ini_set('display_errors', 1);
error_reporting(E_ALL);

function response($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Phương thức không hợp lệ');
}

/* =============================
   VALIDATE INPUT
============================= */

$required = [
    'fullname',
    'birthday',
    'phone',
    'email',
    'identification',
    'address',
    'graduation_year',
    'school',
    'major',
    'method'
];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        response(false, "Thiếu thông tin: $field");
    }
}

/* =============================
   VALIDATE EMAIL
============================= */

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    response(false, 'Email không hợp lệ');
}

/* =============================
   VALIDATE PHONE
============================= */

if (!preg_match('/^(0)[0-9]{9}$/', $_POST['phone'])) {
    response(false, 'Số điện thoại không hợp lệ');
}

/* =============================
   VALIDATE CCCD
============================= */

if (!preg_match('/^[0-9]{12}$/', $_POST['identification'])) {
    response(false, 'CCCD không hợp lệ (phải 12 số)');
}

/* =============================
   SANITIZE DATA
============================= */

$fullname = trim($_POST['fullname']);
$birthday = $_POST['birthday'];
$phone = trim($_POST['phone']);
$email = trim($_POST['email']);
$identification = trim($_POST['identification']);
$address = trim($_POST['address']);
$graduation_year = intval($_POST['graduation_year']);
$school = trim($_POST['school']);
$major = trim($_POST['major']);
$method = trim($_POST['method']);

$conn->begin_transaction();

try {

    /* =============================
       CHECK DUPLICATE EMAIL
    ============================= */

    $stmt = $conn->prepare("SELECT id FROM registrations WHERE email=?");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        response(false, 'Email đã được đăng ký');
    }

    /* =============================
       CHECK DUPLICATE PHONE
    ============================= */

    $stmt = $conn->prepare("SELECT id FROM registrations WHERE phone=?");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        response(false, 'Số điện thoại đã được đăng ký');
    }

    /* =============================
        CHECK DUPLICATE CCCD
    ============================= */

    $stmt = $conn->prepare("SELECT id FROM registrations WHERE identification=?");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $identification);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        response(false, 'CCCD đã được đăng ký');
    }
    /* =============================
       INSERT REGISTRATION
    ============================= */

    $sql = "INSERT INTO registrations 
    (fullname,birthday,phone,email,identification,address,graduation_year,school,major,method)
    VALUES (?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssssssssss",
        $fullname,
        $birthday,
        $phone,
        $email,
        $identification,
        $address,
        $graduation_year,
        $school,
        $major,
        $method
    );

    if (!$stmt->execute()) {
        throw new Exception("Lỗi khi lưu dữ liệu đăng ký tuyển sinh: " . $stmt->error);
    }

    $registration_id = $conn->insert_id;

    /* =============================
       INSERT SCORE
    ============================= */

    if (isset($_POST['scores']) && is_array($_POST['scores'])) {

        $scores = $_POST['scores'];

        $score_data = json_encode($scores, JSON_UNESCAPED_UNICODE);

        $total_score = calculateTotalScore($method, $scores);

        $stmt = $conn->prepare("
            INSERT INTO diemtuyensinh
            (registration_id,method,score_data,total_score)
            VALUES (?,?,?,?)
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "issd",
            $registration_id,
            $method,
            $score_data,
            $total_score
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
    }

    /* =============================
       HANDLE FILE UPLOAD
    ============================= */

    if (!empty($_FILES['transcript']['name'])) {

        $upload_dir = "../uploads/";

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = time() . '_' . basename($_FILES['transcript']['name']);

        move_uploaded_file(
            $_FILES['transcript']['tmp_name'],
            $upload_dir . $filename
        );

        $conn->query("
            UPDATE registrations
            SET file_path='$filename'
            WHERE id=$registration_id
        ");
    }

    /* =============================
       NEWSLETTER
    ============================= */

    if (isset($_POST['newsletter'])) {

        $stmt = $conn->prepare("
            SELECT id FROM newsletter WHERE email=?
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 0) {

            $stmt = $conn->prepare("
                INSERT INTO newsletter(email)
                VALUES(?)
            ");

            $stmt->bind_param("s", $email);
            $stmt->execute();
        }
    }

    $conn->commit();

    response(
        true,
        "Đăng ký thành công! Mã hồ sơ: " .
            str_pad($registration_id, 8, '0', STR_PAD_LEFT),
        ['id' => $registration_id]
    );
} catch (Exception $e) {

    $conn->rollback();

    response(false, "Lỗi hệ thống: " . $e->getMessage());
}


/* =============================
   CALCULATE SCORE
============================= */

function calculateTotalScore($method, $scores)
{

    switch ($method) {

        case 'thpt':

            return ($scores['math'] ?? 0) +
                ($scores['physic'] ?? 0) +
                ($scores['chemistry'] ?? 0);

        case 'hocba':

            $sum = 0;
            $count = 0;

            foreach ($scores as $v) {

                if (is_numeric($v)) {
                    $sum += $v;
                    $count++;
                }
            }

            return $count ? round($sum / $count, 2) : 0;

        case 'dgnl':

            return floatval($scores['dgnl'] ?? 0);

        default:

            return 0;
    }
}
