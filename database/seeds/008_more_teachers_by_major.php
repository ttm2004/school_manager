<?php

require_once __DIR__ . '/../../config/database.php';

$teachersPerMajor = 12;
$defaultPassword = '123456';
$passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

$lastNames = [
    'Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh',
    'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Dương',
];
$middleNames = [
    'Minh', 'Thanh', 'Quốc', 'Thị', 'Anh', 'Gia',
    'Hữu', 'Ngọc', 'Tuấn', 'Thu', 'Kim', 'Đức',
];
$firstNames = [
    'An', 'Bình', 'Chi', 'Dũng', 'Hà', 'Khánh',
    'Linh', 'Nam', 'Phương', 'Quân', 'Trang', 'Vy',
];
$degrees = ['Thạc sĩ', 'Thạc sĩ', 'Tiến sĩ', 'Tiến sĩ', 'ThS. NCS'];

$specializationMap = [
    'Công nghệ thông tin' => ['Công nghệ phần mềm', 'Trí tuệ nhân tạo', 'Mạng máy tính', 'An toàn thông tin'],
    'Kỹ thuật phần mềm' => ['Kiểm thử phần mềm', 'Phân tích thiết kế hệ thống', 'DevOps', 'Quản lý dự án phần mềm'],
    'Hệ thống thông tin' => ['Cơ sở dữ liệu', 'Phân tích dữ liệu', 'ERP', 'Kho dữ liệu'],
    'Quản trị kinh doanh' => ['Quản trị chiến lược', 'Marketing', 'Quản trị nhân lực', 'Khởi nghiệp'],
    'Kế toán' => ['Kế toán tài chính', 'Kiểm toán', 'Kế toán quản trị', 'Thuế'],
    'Ngôn ngữ Anh' => ['Biên phiên dịch', 'Tiếng Anh thương mại', 'Ngôn ngữ học ứng dụng', 'Phương pháp giảng dạy tiếng Anh'],
    'Luật' => ['Luật dân sự', 'Luật kinh tế', 'Luật hành chính', 'Luật lao động'],
    'Giáo dục Tiểu học' => ['Phương pháp dạy Toán', 'Phương pháp dạy Tiếng Việt', 'Tâm lý học giáo dục', 'Quản lý lớp học'],
];

function normalizeCode(string $value): string
{
    $value = trim($value);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $converted = $converted !== false ? $converted : $value;
    $converted = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $converted));
    return $converted !== '' ? $converted : 'GV';
}

function findSpecializations(string $majorName, array $specializationMap): array
{
    foreach ($specializationMap as $key => $items) {
        if (mb_stripos($majorName, $key) !== false) {
            return $items;
        }
    }
    return [$majorName, 'Lý luận chuyên ngành', 'Phương pháp nghiên cứu', 'Ứng dụng thực tiễn'];
}

$majorSql = "SELECT m.id AS major_id, m.major_code, m.major_name, m.faculty_id, f.faculty_name
             FROM majors m
             JOIN faculties f ON f.id = m.faculty_id
             WHERE m.status = 'open'
             ORDER BY m.id";
$majors = $conn->query($majorSql);

if (!$majors || $majors->num_rows === 0) {
    echo "Không có ngành đang mở để seed giảng viên.\n";
    exit(0);
}

$roleId = null;
$roleStmt = $conn->prepare("SELECT id FROM roles WHERE code='faculty_lecturer' AND is_active=1 LIMIT 1");
if ($roleStmt) {
    $roleStmt->execute();
    $role = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    $roleId = $role ? (int)$role['id'] : null;
}

$semesterId = null;
$semesterStmt = $conn->prepare("SELECT id FROM semesters ORDER BY start_date DESC, id DESC LIMIT 1");
if ($semesterStmt) {
    $semesterStmt->execute();
    $semester = $semesterStmt->get_result()->fetch_assoc();
    $semesterStmt->close();
    $semesterId = $semester ? (int)$semester['id'] : null;
}

$createdUsers = 0;
$createdTeachers = 0;
$createdRoles = 0;
$createdWishes = 0;

$conn->begin_transaction();
try {
    while ($major = $majors->fetch_assoc()) {
        $majorId = (int)$major['major_id'];
        $facultyId = (int)$major['faculty_id'];
        $majorCode = normalizeCode($major['major_code'] ?: $major['major_name']);
        $majorPrefix = sprintf('M%02d%s', $majorId, substr($majorCode, -3));
        $specializations = findSpecializations($major['major_name'], $specializationMap);

        $subjects = [];
        $subjectStmt = $conn->prepare("SELECT id FROM subjects WHERE major_id=? ORDER BY semester_order, id LIMIT 6");
        $subjectStmt->bind_param('i', $majorId);
        $subjectStmt->execute();
        foreach ($subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $subject) {
            $subjects[] = (int)$subject['id'];
        }
        $subjectStmt->close();

        for ($i = 1; $i <= $teachersPerMajor; $i++) {
            $teacherCode = sprintf('GV%s%02d', $majorPrefix, $i);
            $username = strtolower($teacherCode);
            $fullName = $lastNames[($majorId + $i) % count($lastNames)] . ' '
                . $middleNames[($majorId * 2 + $i) % count($middleNames)] . ' '
                . $firstNames[($majorId * 3 + $i) % count($firstNames)];
            $email = strtolower($teacherCode) . '@tdmu.edu.vn';
            $phone = '09' . str_pad((string)(70000000 + $majorId * 1000 + $i), 8, '0', STR_PAD_LEFT);
            $degree = $degrees[($majorId + $i) % count($degrees)];
            $specialization = $specializations[($i - 1) % count($specializations)];

            $userStmt = $conn->prepare(
                "INSERT INTO users (username,password,full_name,email,phone,role,status)
                 VALUES (?,?,?,?,?,'teacher',1)
                 ON DUPLICATE KEY UPDATE
                    full_name=VALUES(full_name),
                    email=VALUES(email),
                    phone=VALUES(phone),
                    role='teacher',
                    status=1"
            );
            $userStmt->bind_param('sssss', $username, $passwordHash, $fullName, $email, $phone);
            $userStmt->execute();
            $createdUsers += $userStmt->affected_rows === 1 ? 1 : 0;
            $userStmt->close();

            $getUser = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
            $getUser->bind_param('s', $username);
            $getUser->execute();
            $userId = (int)($getUser->get_result()->fetch_assoc()['id'] ?? 0);
            $getUser->close();
            if ($userId <= 0) {
                throw new Exception("Không lấy được user_id cho {$username}");
            }

            $teacherStmt = $conn->prepare(
                "INSERT INTO teachers (user_id,faculty_id,teacher_code,degree,specialization)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    faculty_id=VALUES(faculty_id),
                    degree=VALUES(degree),
                    specialization=VALUES(specialization)"
            );
            $teacherStmt->bind_param('iisss', $userId, $facultyId, $teacherCode, $degree, $specialization);
            $teacherStmt->execute();
            $createdTeachers += $teacherStmt->affected_rows === 1 ? 1 : 0;
            $teacherStmt->close();

            $getTeacher = $conn->prepare("SELECT id FROM teachers WHERE teacher_code=? LIMIT 1");
            $getTeacher->bind_param('s', $teacherCode);
            $getTeacher->execute();
            $teacherId = (int)($getTeacher->get_result()->fetch_assoc()['id'] ?? 0);
            $getTeacher->close();

            if ($roleId) {
                $roleAssign = $conn->prepare(
                    "INSERT IGNORE INTO user_roles (user_id, role_id, granted_by, note)
                     VALUES (?, ?, 1, ?)"
                );
                $note = 'Giảng viên ' . $major['major_name'];
                $roleAssign->bind_param('iis', $userId, $roleId, $note);
                $roleAssign->execute();
                $createdRoles += $roleAssign->affected_rows > 0 ? 1 : 0;
                $roleAssign->close();
            }

            if ($semesterId && $teacherId && $subjects) {
                foreach (array_slice($subjects, $i % max(1, count($subjects)), 2) as $subjectId) {
                    $wish = $conn->prepare(
                        "INSERT IGNORE INTO teaching_wishes
                         (teacher_id, subject_id, semester_id, faculty_id, priority, note, status, faculty_reviewed_by, faculty_reviewed_at)
                         VALUES (?, ?, ?, ?, 2, ?, 'confirmed', 1, NOW())"
                    );
                    $wishNote = 'Seed nguyện vọng giảng dạy cho ' . $major['major_name'];
                    $wish->bind_param('iiiis', $teacherId, $subjectId, $semesterId, $facultyId, $wishNote);
                    $wish->execute();
                    $createdWishes += $wish->affected_rows > 0 ? 1 : 0;
                    $wish->close();
                }
            }
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Lỗi seed giảng viên: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Đã seed giảng viên theo ngành.\n";
echo "Tài khoản mới: {$createdUsers}\n";
echo "Hồ sơ giảng viên mới: {$createdTeachers}\n";
echo "Quyền giảng viên mới: {$createdRoles}\n";
echo "Nguyện vọng giảng dạy mới: {$createdWishes}\n";
echo "Mật khẩu mặc định: {$defaultPassword}\n";
