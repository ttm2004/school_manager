<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Lớp hành chính';

function academicClassColumnExists(mysqli $conn, string $column): bool
{
    $safe = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `classes` LIKE '$safe'");
    return $res && $res->num_rows > 0;
}

function academicTableColumnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    return $res && $res->num_rows > 0;
}

function academicEnsureClassModeColumns(mysqli $conn): void
{
    if (!academicClassColumnExists($conn, 'data_mode')) {
        $conn->query("ALTER TABLE `classes` ADD COLUMN `data_mode` ENUM('system','test') NOT NULL DEFAULT 'system' AFTER `cohort_id`");
    }
    if (!academicClassColumnExists($conn, 'demo_batch_id')) {
        $conn->query("ALTER TABLE `classes` ADD COLUMN `demo_batch_id` VARCHAR(64) NULL AFTER `data_mode`");
    }
    if (!academicClassColumnExists($conn, 'max_students')) {
        $conn->query("ALTER TABLE `classes` ADD COLUMN `max_students` INT NOT NULL DEFAULT 70 AFTER `school_year`");
    }
}

function academicClassIsLocked(mysqli $conn, int $classId, string $mode): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(ss.id) AS c
         FROM students s
         JOIN student_subjects ss ON ss.student_id = s.id
         WHERE s.class_id = ? AND s.data_mode = ?"
    );
    $stmt->bind_param('is', $classId, $mode);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count > 0;
}

function academicClassCanDelete(mysqli $conn, int $classId): array
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE class_id = ?");
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $students = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    if ($students > 0) {
        return ['ok' => false, 'message' => 'Không thể xóa lớp đã có sinh viên.'];
    }

    $hasClassId = $conn->query("SHOW COLUMNS FROM `course_sections` LIKE 'class_id'");
    if ($hasClassId && $hasClassId->num_rows > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM course_sections WHERE class_id = ?");
        $stmt->bind_param('i', $classId);
        $stmt->execute();
        $sections = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        if ($sections > 0) {
            return ['ok' => false, 'message' => 'Không thể xóa lớp đã được dùng trong lớp học phần.'];
        }
    }

    return ['ok' => true, 'message' => ''];
}

function academicFindOrCreateCohort(mysqli $conn, int $majorId, int $year): int
{
    $chk = $conn->query("SHOW TABLES LIKE 'training_cohorts'");
    if (!$chk || $chk->num_rows === 0) return 0;

    $stmt = $conn->prepare("SELECT id FROM training_cohorts WHERE major_id = ? AND enrollment_year = ? LIMIT 1");
    $stmt->bind_param('ii', $majorId, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int)$row['id'];

    $stmt = $conn->prepare(
        "SELECT m.major_code, m.major_name,
                (SELECT id FROM training_programs tp WHERE tp.major_id = m.id AND tp.effective_year <= ? ORDER BY tp.effective_year DESC LIMIT 1) AS program_id
         FROM majors m WHERE m.id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $year, $majorId);
    $stmt->execute();
    $major = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$major) return 0;

    $duration = 4.0;
    $programId = (int)($major['program_id'] ?? 0);
    if ($programId > 0) {
        $p = $conn->prepare("SELECT duration_years FROM training_programs WHERE id = ? LIMIT 1");
        $p->bind_param('i', $programId);
        $p->execute();
        $duration = (float)($p->get_result()->fetch_assoc()['duration_years'] ?? 4);
        $p->close();
    }

    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$major['major_code'])) . '-' . $year;
    $name = (string)$major['major_name'] . ' khóa ' . $year . '-' . ($year + (int)ceil($duration));
    $start = $year . '-08-15';
    $end = ($year + (int)ceil($duration)) . '-08-14';
    $status = 'planned';

    $ins = $conn->prepare(
        "INSERT INTO training_cohorts (major_id, enrollment_year, program_id, cohort_code, cohort_name, duration_years, start_date, expected_end_date, status)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $programParam = $programId > 0 ? $programId : null;
    $ins->bind_param('iiissdsss', $majorId, $year, $programParam, $code, $name, $duration, $start, $end, $status);
    if (!$ins->execute()) {
        $ins->close();
        return 0;
    }
    $id = (int)$conn->insert_id;
    $ins->close();
    return $id;
}

function academicClassProgramIdForCohort(mysqli $conn, int $cohortId): int
{
    if ($cohortId <= 0) return 0;
    $stmt = $conn->prepare("SELECT program_id FROM training_cohorts WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $cohortId);
    $stmt->execute();
    $programId = (int)($stmt->get_result()->fetch_assoc()['program_id'] ?? 0);
    $stmt->close();
    return $programId;
}

academicEnsureClassModeColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yêu cầu không hợp lệ.'];
        header('Location: classes.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chỉ Trưởng phòng Đào tạo mới có quyền cập nhật lớp hành chính.'];
        header('Location: classes.php'); exit();
    }

    $action = trim($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $classCode = strtoupper(trim($_POST['class_code'] ?? ''));
    $className = trim($_POST['class_name'] ?? '');
    $majorId = (int)($_POST['major_id'] ?? 0);
    $year = (int)($_POST['enrollment_year'] ?? 0);
    $schoolYear = trim($_POST['school_year'] ?? '');
    $maxStudents = max(1, min(300, (int)($_POST['max_students'] ?? 70)));
    $mode = (($_POST['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
    $redirectMode = '?mode=' . urlencode($mode);

    if ($schoolYear === '' && $year >= 2000) {
        $schoolYear = $year . '-' . ($year + 4);
    }

    if ($action === 'generate_from_round') {
        $roundId = (int)($_POST['round_id'] ?? 0);
        $majorId = (int)($_POST['major_id'] ?? 0);
        $classCount = max(1, min(20, (int)($_POST['class_count'] ?? 1)));

        $roundStmt = $conn->prepare("SELECT id, year, name, status, data_mode, demo_batch_id FROM admission_rounds WHERE id = ? LIMIT 1");
        $roundStmt->bind_param('i', $roundId);
        $roundStmt->execute();
        $round = $roundStmt->get_result()->fetch_assoc();
        $roundStmt->close();

        $majorStmt = $conn->prepare("SELECT major_code, major_name FROM majors WHERE id = ? LIMIT 1");
        $majorStmt->bind_param('i', $majorId);
        $majorStmt->execute();
        $major = $majorStmt->get_result()->fetch_assoc();
        $majorStmt->close();

        if (!$round || !$major) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng chọn đúng đợt tuyển sinh và ngành cần mở lớp.'];
            header('Location: classes.php?mode=' . urlencode($mode)); exit();
        }

        if (($round['status'] ?? '') === 'completed') {
            $_SESSION['_flash'] = ['type'=>'warning','message'=>'Đợt tuyển sinh đã hoàn tất nên không thể mở thêm lớp hành chính.'];
            header('Location: classes.php?mode=' . urlencode($mode)); exit();
        }

        $year = (int)$round['year'];
        $mode = (($round['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
        $demoBatchId = $mode === 'test' ? (string)($round['demo_batch_id'] ?? '') : '';
        $schoolYear = $year . '-' . ($year + 4);
        $cohortId = academicFindOrCreateCohort($conn, $majorId, $year);
        $cohortParam = $cohortId > 0 ? $cohortId : null;
        $majorCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$major['major_code']));
        if ($majorCode === '') $majorCode = 'NGANH';

        $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM classes WHERE major_id = ? AND enrollment_year = ? AND data_mode = ?");
        $countStmt->bind_param('iis', $majorId, $year, $mode);
        $countStmt->execute();
        $startIndex = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0) + 1;
        $countStmt->close();

        $conn->begin_transaction();
        try {
            $created = 0;
            for ($i = 0; $i < $classCount; $i++) {
                $seq = $startIndex + $i;
                $classCode = 'D' . substr((string)$year, -2) . $majorCode . str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
                $className = (string)$major['major_name'] . ' K' . substr((string)$year, -2) . ' - Lớp ' . $seq;

                $existsStmt = $conn->prepare("SELECT id FROM classes WHERE class_code = ? AND data_mode = ? LIMIT 1");
                $existsStmt->bind_param('ss', $classCode, $mode);
                $existsStmt->execute();
                $exists = $existsStmt->get_result()->fetch_assoc();
                $existsStmt->close();
                if ($exists) continue;

                $ins = $conn->prepare(
                    "INSERT INTO classes (class_code, class_name, major_id, school_year, max_students, enrollment_year, cohort_id, data_mode, demo_batch_id)
                     VALUES (?,?,?,?,?,?,?,?,?)"
                );
                $ins->bind_param('ssisiiiss', $classCode, $className, $majorId, $schoolYear, $maxStudents, $year, $cohortParam, $mode, $demoBatchId);
                if (!$ins->execute()) {
                    throw new Exception($ins->error ?: $conn->error);
                }
                $ins->close();
                $created++;
            }
            $conn->commit();
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Đã mở ' . $created . ' lớp hành chính từ đợt tuyển sinh ' . $year . ' (' . ($mode === 'test' ? 'Test/Demo' : 'Dữ liệu thật') . ').'];
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Lỗi mở lớp từ đợt tuyển sinh: ' . $e->getMessage()];
        }
        header('Location: classes.php?mode=' . urlencode($mode) . '&major_id=' . $majorId . '&year=' . $year); exit();
    }

    if ($action === 'generate_previous_test_students') {
        $mode = 'test';
        $majorId = (int)($_POST['major_id'] ?? 0);
        $year = (int)($_POST['enrollment_year'] ?? 2025);
        $studentCount = max(1, min(200, (int)($_POST['student_count'] ?? 40)));
        $maxStudents = max($studentCount, min(300, (int)($_POST['max_students'] ?? 70)));

        $majorStmt = $conn->prepare("SELECT major_code, major_name FROM majors WHERE id = ? LIMIT 1");
        $majorStmt->bind_param('i', $majorId);
        $majorStmt->execute();
        $major = $majorStmt->get_result()->fetch_assoc();
        $majorStmt->close();

        if (!$major || $year < 2000) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng chọn đúng ngành và năm tuyển sinh cần tạo dữ liệu test.'];
            header('Location: classes.php?mode=test'); exit();
        }

        $cohortId = academicFindOrCreateCohort($conn, $majorId, $year);
        $programId = academicClassProgramIdForCohort($conn, $cohortId);
        $cohortParam = $cohortId > 0 ? $cohortId : null;
        $programParam = $programId > 0 ? $programId : null;
        $majorCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$major['major_code']));
        if ($majorCode === '') $majorCode = 'NGANH';
        $demoBatchId = 'academic_prev_students_' . $year . '_' . $majorId;
        $schoolYear = $year . '-' . ($year + 4);
        $classCode = 'T' . substr((string)$year, -2) . $majorCode . '01';
        $className = (string)$major['major_name'] . ' K' . substr((string)$year, -2) . ' - Test đăng ký môn';

        $conn->begin_transaction();
        try {
            $classStmt = $conn->prepare("SELECT id FROM classes WHERE class_code = ? AND data_mode = 'test' LIMIT 1");
            $classStmt->bind_param('s', $classCode);
            $classStmt->execute();
            $classId = (int)($classStmt->get_result()->fetch_assoc()['id'] ?? 0);
            $classStmt->close();
            if ($classId <= 0) {
                $insClass = $conn->prepare(
                    "INSERT INTO classes (class_code, class_name, major_id, school_year, max_students, enrollment_year, cohort_id, data_mode, demo_batch_id)
                     VALUES (?,?,?,?,?,?,?,'test',?)"
                );
                $insClass->bind_param('ssisiiis', $classCode, $className, $majorId, $schoolYear, $maxStudents, $year, $cohortParam, $demoBatchId);
                if (!$insClass->execute()) throw new Exception($insClass->error ?: $conn->error);
                $classId = (int)$conn->insert_id;
                $insClass->close();
            }

            $created = 0;
            for ($i = 1; $i <= $studentCount; $i++) {
                $studentCode = 'T' . $year . $majorCode . sprintf('%03d', $i);
                $existsStmt = $conn->prepare("SELECT id FROM students WHERE student_code = ? LIMIT 1");
                $existsStmt->bind_param('s', $studentCode);
                $existsStmt->execute();
                $exists = $existsStmt->get_result()->fetch_assoc();
                $existsStmt->close();
                if ($exists) continue;

                $email = strtolower($studentCode) . '@student.test.tdmu.edu.vn';
                $fullName = 'Sinh viên test K' . substr((string)$year, -2) . ' ' . sprintf('%03d', $i);
                $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $userStmt->bind_param('ss', $studentCode, $email);
                $userStmt->execute();
                $userId = (int)($userStmt->get_result()->fetch_assoc()['id'] ?? 0);
                $userStmt->close();
                if ($userId <= 0) {
                    $password = password_hash($studentCode, PASSWORD_DEFAULT);
                    $insUser = $conn->prepare(
                        "INSERT INTO users (username, password, full_name, email, role, status)
                         VALUES (?, ?, ?, ?, 'student', 1)"
                    );
                    $insUser->bind_param('ssss', $studentCode, $password, $fullName, $email);
                    if (!$insUser->execute()) throw new Exception($insUser->error ?: $conn->error);
                    $userId = (int)$conn->insert_id;
                    $insUser->close();
                }

                $gender = $i % 2 === 0 ? 'Nữ' : 'Nam';
                $birthday = ($year - 18) . '-09-' . sprintf('%02d', ($i % 25) + 1);
                $expectedGradYear = $year + 4;
                $insStudent = $conn->prepare(
                    "INSERT INTO students
                        (user_id, class_id, student_code, gender, birthday, address, academic_status,
                         enrollment_year, cohort_id, training_program_id, expected_grad_year, data_mode, demo_batch_id)
                     VALUES (?, ?, ?, ?, ?, 'Bình Dương', 'Đang học', ?, ?, ?, ?, 'test', ?)"
                );
                $insStudent->bind_param(
                    'iisssiiiis',
                    $userId,
                    $classId,
                    $studentCode,
                    $gender,
                    $birthday,
                    $year,
                    $cohortParam,
                    $programParam,
                    $expectedGradYear,
                    $demoBatchId
                );
                if (!$insStudent->execute()) throw new Exception($insStudent->error ?: $conn->error);
                $insStudent->close();
                $created++;
            }

            $conn->commit();
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Đã tạo ' . $created . ' sinh viên test khóa ' . $year . ' cho ngành ' . $major['major_name'] . '. Các sinh viên này chưa đăng ký môn tự động.'];
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Lỗi tạo sinh viên test khóa trước: ' . $e->getMessage()];
        }
        header('Location: classes.php?mode=test&major_id=' . $majorId . '&year=' . $year . '&lock=editable'); exit();
    }

    if ($action === 'add') {
        if ($classCode === '' || $className === '' || $majorId <= 0 || $year < 2000) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng nhập đủ mã lớp, tên lớp, ngành và năm tuyển sinh.'];
            header('Location: classes.php' . $redirectMode); exit();
        }
        $cohortId = academicFindOrCreateCohort($conn, $majorId, $year);
        $stmt = $conn->prepare(
            "INSERT INTO classes (class_code, class_name, major_id, school_year, max_students, enrollment_year, cohort_id, data_mode)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $cohortParam = $cohortId > 0 ? $cohortId : null;
        $stmt->bind_param('ssisiiis', $classCode, $className, $majorId, $schoolYear, $maxStudents, $year, $cohortParam, $mode);
        $_SESSION['_flash'] = $stmt->execute()
            ? ['type'=>'success','message'=>'Đã thêm lớp hành chính cho khóa tuyển sinh ' . $year . '.']
            : ['type'=>'danger','message'=>'Lỗi: ' . $conn->error];
        $stmt->close();
        header('Location: classes.php' . $redirectMode); exit();
    }

    if ($action === 'edit') {
        if ($id <= 0 || $className === '') {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Dữ liệu lớp không hợp lệ.'];
            header('Location: classes.php' . $redirectMode); exit();
        }
        $rowStmt = $conn->prepare("SELECT data_mode FROM classes WHERE id = ? LIMIT 1");
        $rowStmt->bind_param('i', $id);
        $rowStmt->execute();
        $oldMode = (($rowStmt->get_result()->fetch_assoc()['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
        $rowStmt->close();
        $stmt = $conn->prepare(
            "UPDATE classes
             SET class_name=?, max_students=?
             WHERE id=?"
        );
        $stmt->bind_param('sii', $className, $maxStudents, $id);
        $_SESSION['_flash'] = $stmt->execute()
            ? ['type'=>'success','message'=>'Đã cập nhật lớp hành chính.']
            : ['type'=>'danger','message'=>'Lỗi: ' . $conn->error];
        $stmt->close();
        header('Location: classes.php?mode=' . urlencode($oldMode)); exit();
    }

    if ($action === 'delete') {
        if ($id <= 0) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Dữ liệu lớp không hợp lệ.'];
            header('Location: classes.php' . $redirectMode); exit();
        }
        $rowStmt = $conn->prepare("SELECT data_mode FROM classes WHERE id = ? LIMIT 1");
        $rowStmt->bind_param('i', $id);
        $rowStmt->execute();
        $row = $rowStmt->get_result()->fetch_assoc();
        $rowStmt->close();
        $oldMode = (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
        $deleteCheck = academicClassCanDelete($conn, $id);
        if (!$deleteCheck['ok']) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>$deleteCheck['message']];
            header('Location: classes.php?mode=' . urlencode($oldMode)); exit();
        }
        $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->bind_param('i', $id);
        $_SESSION['_flash'] = $stmt->execute()
            ? ['type'=>'success','message'=>'Đã xóa lớp hành chính.']
            : ['type'=>'danger','message'=>'Lỗi: ' . $conn->error];
        $stmt->close();
        header('Location: classes.php?mode=' . urlencode($oldMode)); exit();
    }
}

$flash = getFlash();
$filterMode = (($_GET['mode'] ?? 'system') === 'test') ? 'test' : 'system';
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterMajor = (int)($_GET['major_id'] ?? 0);
$filterYear = (int)($_GET['year'] ?? 0);
$filterLock = trim($_GET['lock'] ?? '');
$search = trim($_GET['q'] ?? '');
$viewClassId = (int)($_GET['view_class'] ?? 0);
$exportClassStudentsId = (int)($_GET['export_class_students'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

if ($exportClassStudentsId > 0) {
    $exportClassStmt = $conn->prepare(
        "SELECT c.id, c.class_code, c.class_name, c.enrollment_year, COALESCE(c.data_mode, 'system') AS data_mode,
                m.major_name, f.faculty_name
         FROM classes c
         LEFT JOIN majors m ON m.id = c.major_id
         LEFT JOIN faculties f ON f.id = m.faculty_id
         WHERE c.id = ? LIMIT 1"
    );
    $exportClassStmt->bind_param('i', $exportClassStudentsId);
    $exportClassStmt->execute();
    $exportClass = $exportClassStmt->get_result()->fetch_assoc();
    $exportClassStmt->close();

    if (!$exportClass) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không tìm thấy lớp cần xuất danh sách sinh viên.'];
        header('Location: classes.php?mode=' . urlencode($filterMode)); exit();
    }

    $exportMode = (($exportClass['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
    $exportStudentStmt = $conn->prepare(
        "SELECT s.student_code, s.gender, s.birthday, s.academic_status, s.enrollment_year,
                u.full_name, u.email,
                COUNT(DISTINCT ss.id) AS registration_count
         FROM students s
         JOIN users u ON u.id = s.user_id
         LEFT JOIN student_subjects ss ON ss.student_id = s.id AND ss.status IN ('registered','auto_enrolled','completed')
         WHERE s.class_id = ? AND s.data_mode = ?
         GROUP BY s.id, s.student_code, s.gender, s.birthday, s.academic_status, s.enrollment_year, u.full_name, u.email
         ORDER BY s.student_code"
    );
    $exportStudentStmt->bind_param('is', $exportClassStudentsId, $exportMode);
    $exportStudentStmt->execute();
    $exportStudents = $exportStudentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $exportStudentStmt->close();

    $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$exportClass['class_code']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="danh_sach_sinh_vien_' . $safeCode . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Mã lớp', 'Tên lớp', 'Khoa/Viện', 'Ngành', 'Năm tuyển sinh', 'Chế độ']);
    fputcsv($out, [
        $exportClass['class_code'],
        $exportClass['class_name'],
        $exportClass['faculty_name'] ?? '',
        $exportClass['major_name'] ?? '',
        (int)$exportClass['enrollment_year'],
        $exportMode === 'test' ? 'Test / Demo' : 'Dữ liệu thật',
    ]);
    fputcsv($out, []);
    fputcsv($out, ['STT', 'Mã SV', 'Họ tên', 'Email', 'Giới tính', 'Ngày sinh', 'Năm tuyển sinh', 'Số môn đã đăng ký', 'Trạng thái']);
    foreach ($exportStudents as $idx => $student) {
        fputcsv($out, [
            $idx + 1,
            $student['student_code'],
            $student['full_name'],
            $student['email'],
            $student['gender'] ?? '',
            !empty($student['birthday']) ? date('d/m/Y', strtotime($student['birthday'])) : '',
            (int)($student['enrollment_year'] ?? 0),
            (int)($student['registration_count'] ?? 0),
            $student['academic_status'] ?? '',
        ]);
    }
    fclose($out);
    exit();
}

$where = ["(COALESCE(c.data_mode, 'system') = ? OR EXISTS (SELECT 1 FROM students sm WHERE sm.class_id = c.id AND sm.data_mode = ?))"];
$types = 'ss';
$params = [$filterMode, $filterMode];
if ($filterFaculty > 0) { $where[] = 'f.id = ?'; $types .= 'i'; $params[] = $filterFaculty; }
if ($filterMajor > 0) { $where[] = 'm.id = ?'; $types .= 'i'; $params[] = $filterMajor; }
if ($filterYear > 0) { $where[] = 'c.enrollment_year = ?'; $types .= 'i'; $params[] = $filterYear; }
if ($search !== '') {
    $where[] = '(c.class_code LIKE ? OR c.class_name LIKE ? OR m.major_name LIKE ? OR f.faculty_name LIKE ?)';
    $like = "%$search%"; $types .= 'ssss'; array_push($params, $like, $like, $like, $like);
}
if ($filterLock === 'locked') {
    $where[] = "EXISTS (SELECT 1 FROM students sx JOIN student_subjects ssx ON ssx.student_id = sx.id WHERE sx.class_id = c.id AND sx.data_mode = ?)";
    $types .= 's';
    $params[] = $filterMode;
} elseif ($filterLock === 'editable') {
    $where[] = "NOT EXISTS (SELECT 1 FROM students sx JOIN student_subjects ssx ON ssx.student_id = sx.id WHERE sx.class_id = c.id AND sx.data_mode = ?)";
    $types .= 's';
    $params[] = $filterMode;
}
$whereSQL = implode(' AND ', $where);

$stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM classes c LEFT JOIN majors m ON c.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL");
$stmtCnt->bind_param($types, ...$params);
$stmtCnt->execute();
$total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
$stmtCnt->close();
$pag = paginateAcademic($total, $page, $perPage);

$stmt = $conn->prepare(
    "SELECT c.id, c.class_code, c.class_name, c.school_year, c.max_students, c.enrollment_year, c.major_id,
            COALESCE(c.data_mode, 'system') AS data_mode,
            m.major_name, m.major_code, f.id AS faculty_id, f.faculty_name,
            COUNT(DISTINCT s.id) AS student_count,
            COUNT(DISTINCT ss.id) AS registration_count
     FROM classes c
     LEFT JOIN majors m ON c.major_id=m.id
     LEFT JOIN faculties f ON m.faculty_id=f.id
     LEFT JOIN students s ON s.class_id=c.id AND s.data_mode=?
     LEFT JOIN student_subjects ss ON ss.student_id=s.id
     WHERE $whereSQL
     GROUP BY c.id
     ORDER BY c.enrollment_year DESC, f.faculty_name, m.major_name, c.class_code
     LIMIT ? OFFSET ?"
);
$dataTypes = 's' . $types . 'ii';
$dataParams = array_merge([$filterMode], $params, [$pag['per_page'], $pag['offset']]);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$faculties = $conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$majors = $conn->query("SELECT m.id, m.major_code, m.major_name, m.faculty_id, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id=f.id ORDER BY f.faculty_name, m.major_name")->fetch_all(MYSQLI_ASSOC);
$yearsStmt = $conn->prepare("SELECT DISTINCT enrollment_year FROM classes WHERE COALESCE(data_mode, 'system')=? AND enrollment_year IS NOT NULL ORDER BY enrollment_year DESC");
$yearsStmt->bind_param('s', $filterMode);
$yearsStmt->execute();
$years = $yearsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$yearsStmt->close();
$rounds = $conn->query("SELECT id, name, year, status, data_mode FROM admission_rounds WHERE status <> 'completed' ORDER BY year DESC, data_mode ASC, id DESC")->fetch_all(MYSQLI_ASSOC);
$defaultPreviousTestYear = $filterYear > 2000 ? $filterYear - 1 : ((int)date('Y') - 1);

$viewClass = null;
$classStudents = [];
if ($viewClassId > 0) {
    $viewStmt = $conn->prepare(
        "SELECT c.id, c.class_code, c.class_name, c.enrollment_year, COALESCE(c.data_mode, 'system') AS data_mode,
                m.major_name, f.faculty_name
         FROM classes c
         LEFT JOIN majors m ON m.id = c.major_id
         LEFT JOIN faculties f ON f.id = m.faculty_id
         WHERE c.id = ? LIMIT 1"
    );
    $viewStmt->bind_param('i', $viewClassId);
    $viewStmt->execute();
    $viewClass = $viewStmt->get_result()->fetch_assoc();
    $viewStmt->close();
    if ($viewClass) {
        $studentStmt = $conn->prepare(
            "SELECT s.student_code, s.gender, s.birthday, s.academic_status, s.enrollment_year,
                    u.full_name, u.email,
                    COUNT(DISTINCT ss.id) AS registration_count
             FROM students s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN student_subjects ss ON ss.student_id = s.id AND ss.status IN ('registered','auto_enrolled','completed')
             WHERE s.class_id = ? AND s.data_mode = ?
             GROUP BY s.id, s.student_code, s.gender, s.birthday, s.academic_status, s.enrollment_year, u.full_name, u.email
             ORDER BY s.student_code"
        );
        $viewMode = (($viewClass['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
        $studentStmt->bind_param('is', $viewClassId, $viewMode);
        $studentStmt->execute();
        $classStudents = $studentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $studentStmt->close();
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-diagram-3-fill me-2 text-navy"></i>Lớp hành chính</span>
    </div>
    <?php if (isAcademicManager()): ?>
    <div class="d-flex gap-2">
        <?php if ($filterMode === 'test'): ?>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#previousTestStudentsModal"><i class="bi bi-person-plus-fill me-1"></i>Tạo SV test khóa trước</button>
        <?php endif; ?>
        <button class="btn btn-sm btn-gold" data-bs-toggle="modal" data-bs-target="#generateModal"><i class="bi bi-calendar-plus me-1"></i>Mở lớp từ đợt tuyển sinh</button>
        <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Thêm thủ công</button>
    </div>
    <?php endif; ?>
</div>
<div class="admin-content">
<?php if ($flash): ?>
<div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-3">
    <?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small">Chế độ</label>
        <select name="mode" class="form-select form-select-sm">
            <option value="system" <?php echo $filterMode==='system'?'selected':''; ?>>Dữ liệu thật</option>
            <option value="test" <?php echo $filterMode==='test'?'selected':''; ?>>Test / Demo</option>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Khoa/Viện</label>
        <select name="faculty_id" id="filterFaculty" class="form-select form-select-sm">
            <option value="0">Tất cả</option>
            <?php foreach ($faculties as $f): ?><option value="<?php echo (int)$f['id']; ?>" <?php echo $filterFaculty===(int)$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small">Ngành</label>
        <select name="major_id" id="filterMajor" class="form-select form-select-sm">
            <option value="0">Tất cả</option>
            <?php foreach ($majors as $m): ?><option value="<?php echo (int)$m['id']; ?>" data-faculty="<?php echo (int)$m['faculty_id']; ?>" <?php echo $filterMajor===(int)$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['major_name']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Năm tuyển sinh</label>
        <select name="year" class="form-select form-select-sm"><option value="0">Tất cả</option>
            <?php foreach ($years as $y): $yy=(int)$y['enrollment_year']; ?><option value="<?php echo $yy; ?>" <?php echo $filterYear===$yy?'selected':''; ?>><?php echo $yy; ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Đăng ký môn</label>
        <select name="lock" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            <option value="editable" <?php echo $filterLock==='editable'?'selected':''; ?>>Chưa có đăng ký</option>
            <option value="locked" <?php echo $filterLock==='locked'?'selected':''; ?>>Đã có đăng ký</option>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small">Tìm kiếm</label><input name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search); ?>" placeholder="Mã lớp, tên lớp, ngành..."></div>
    <div class="col-auto"><button class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button><a href="classes.php?mode=<?php echo urlencode($filterMode); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
</form>
</div></div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-diagram-3-fill me-2"></i>Danh sách lớp hành chính <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span></span>
        <span class="badge <?php echo $filterMode==='test'?'bg-info text-dark':'bg-secondary'; ?>"><?php echo $filterMode==='test'?'Test / Demo':'Dữ liệu thật'; ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Mã lớp</th><th>Tên lớp</th><th>Khoa/Viện</th><th>Ngành</th><th>Năm tuyển sinh</th><th>Sĩ số</th><th>SV</th><th>Đăng ký môn</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr></thead>
            <tbody>
            <?php if (empty($classes)): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Chưa có lớp hành chính phù hợp bộ lọc.</td></tr>
            <?php else: foreach ($classes as $c): $locked = (int)$c['registration_count'] > 0; ?>
            <tr>
                <td><code><?php echo htmlspecialchars($c['class_code']); ?></code></td>
                <td><div class="fw-semibold small"><?php echo htmlspecialchars($c['class_name']); ?></div><div class="text-muted small"><?php echo htmlspecialchars($c['school_year'] ?: ''); ?></div></td>
                <td class="small"><?php echo htmlspecialchars($c['faculty_name'] ?? ''); ?></td>
                <td class="small"><?php echo htmlspecialchars($c['major_name'] ?? ''); ?></td>
                <td><span class="badge bg-light text-dark"><?php echo (int)$c['enrollment_year']; ?></span></td>
                <td><span class="badge bg-light text-dark"><?php echo (int)($c['max_students'] ?? 70); ?></span></td>
                <td>
                    <a class="badge bg-primary text-decoration-none"
                       href="classes.php?<?php echo htmlspecialchars(http_build_query(['mode'=>$filterMode,'faculty_id'=>$filterFaculty,'major_id'=>$filterMajor,'year'=>$filterYear,'lock'=>$filterLock,'q'=>$search,'view_class'=>(int)$c['id']])); ?>">
                        <?php echo (int)$c['student_count']; ?>
                    </a>
                </td>
                <td><span class="badge <?php echo $locked?'bg-success':'bg-secondary'; ?>"><?php echo (int)$c['registration_count']; ?></span></td>
                <td><?php if ($locked): ?><span class="badge bg-dark">Đã có đăng ký</span><?php else: ?><span class="badge bg-warning text-dark">Chưa có đăng ký</span><?php endif; ?></td>
                <td class="text-center">
                    <a class="btn btn-sm btn-outline-info"
                       title="Xem sinh viên"
                       href="classes.php?<?php echo htmlspecialchars(http_build_query(['mode'=>$filterMode,'faculty_id'=>$filterFaculty,'major_id'=>$filterMajor,'year'=>$filterYear,'lock'=>$filterLock,'q'=>$search,'view_class'=>(int)$c['id']])); ?>">
                        <i class="bi bi-people"></i>
                    </a>
                    <?php if (isAcademicManager()): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                        data-id="<?php echo (int)$c['id']; ?>"
                        data-code="<?php echo htmlspecialchars($c['class_code']); ?>"
                        data-name="<?php echo htmlspecialchars($c['class_name']); ?>"
                        data-major="<?php echo (int)$c['major_id']; ?>"
                        data-year="<?php echo (int)$c['enrollment_year']; ?>"
                        data-max-students="<?php echo (int)($c['max_students'] ?? 70); ?>"
                        data-school-year="<?php echo htmlspecialchars($c['school_year'] ?? ''); ?>"
                        data-mode="<?php echo htmlspecialchars($c['data_mode']); ?>"><i class="bi bi-pencil"></i></button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Xóa lớp hành chính này?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <input type="hidden" name="data_mode" value="<?php echo htmlspecialchars($c['data_mode']); ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Xóa lớp"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php elseif ($locked): ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled title="Lớp đã có đăng ký môn"><i class="bi bi-lock-fill"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?php echo $pag['offset']+1; ?>-<?php echo min($pag['offset']+$pag['per_page'],$pag['total']); ?> / <?php echo number_format($pag['total']); ?></small>
        <?php echo renderAcademicPagination($pag, http_build_query(['mode'=>$filterMode,'faculty_id'=>$filterFaculty,'major_id'=>$filterMajor,'year'=>$filterYear,'lock'=>$filterLock,'q'=>$search])); ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($viewClass): ?>
<?php
    $viewMode = (($viewClass['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
    $registeredStudents = 0;
    foreach ($classStudents as $student) {
        if ((int)($student['registration_count'] ?? 0) > 0) $registeredStudents++;
    }
    $exportStudentsUrl = 'classes.php?' . http_build_query(['mode' => $viewMode, 'export_class_students' => (int)$viewClass['id']]);
    $manageStudentsUrl = 'students.php?' . http_build_query(['mode' => $viewMode]);
?>
<style>
@media print {
    body * { visibility: hidden; }
    #classStudentsModal, #classStudentsModal * { visibility: visible; }
    #classStudentsModal { position: absolute; inset: 0; display: block !important; overflow: visible !important; }
    #classStudentsModal .modal-dialog { max-width: none; margin: 0; }
    #classStudentsModal .modal-content { border: 0; box-shadow: none; }
    #classStudentsModal .modal-footer, #classStudentsModal .btn, .modal-backdrop { display: none !important; }
}
</style>
<div class="modal fade" id="classStudentsModal" tabindex="-1" aria-labelledby="classStudentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-navy text-white">
                <div>
                    <h5 class="modal-title" id="classStudentsModalLabel">
                        <i class="bi bi-people-fill me-2"></i>Sinh viên lớp <?php echo htmlspecialchars($viewClass['class_code']); ?>
                    </h5>
                    <div class="small opacity-75"><?php echo htmlspecialchars($viewClass['class_name']); ?></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($viewClass['faculty_name'] ?? ''); ?></span>
                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($viewClass['major_name'] ?? ''); ?></span>
                        <span class="badge bg-light text-dark border">Khóa <?php echo (int)$viewClass['enrollment_year']; ?></span>
                        <span class="badge <?php echo $viewMode === 'test' ? 'bg-info text-dark' : 'bg-secondary'; ?>"><?php echo $viewMode === 'test' ? 'Test / Demo' : 'Dữ liệu thật'; ?></span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-success" href="<?php echo htmlspecialchars($exportStudentsUrl); ?>">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Xuất Excel
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>In danh sách
                        </button>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($manageStudentsUrl); ?>">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Mở trang sinh viên
                        </a>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-2"><div class="text-muted small">Tổng sinh viên</div><div class="fs-5 fw-bold text-navy"><?php echo number_format(count($classStudents)); ?></div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-2"><div class="text-muted small">Đã đăng ký môn</div><div class="fs-5 fw-bold text-success"><?php echo number_format($registeredStudents); ?></div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-2"><div class="text-muted small">Chưa đăng ký</div><div class="fs-5 fw-bold text-warning"><?php echo number_format(max(0, count($classStudents) - $registeredStudents)); ?></div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-2"><div class="text-muted small">Mã lớp</div><div class="fw-semibold"><code><?php echo htmlspecialchars($viewClass['class_code']); ?></code></div></div></div>
                </div>

                <?php if (empty($classStudents)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                    Lớp này chưa có sinh viên.
                </div>
                <?php else: ?>
                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px" class="text-center">#</th>
                                <th>Mã SV</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th class="text-center">Giới tính</th>
                                <th class="text-center">Ngày sinh</th>
                                <th class="text-center">Đăng ký môn</th>
                                <th class="text-center">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($classStudents as $idx => $student): ?>
                        <tr>
                            <td class="text-center text-muted small"><?php echo $idx + 1; ?></td>
                            <td><code><?php echo htmlspecialchars($student['student_code']); ?></code></td>
                            <td class="fw-semibold small"><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td class="small"><?php echo htmlspecialchars($student['email']); ?></td>
                            <td class="text-center small"><?php echo htmlspecialchars($student['gender'] ?? ''); ?></td>
                            <td class="text-center small"><?php echo !empty($student['birthday']) ? date('d/m/Y', strtotime($student['birthday'])) : '-'; ?></td>
                            <td class="text-center"><span class="badge <?php echo (int)$student['registration_count'] > 0 ? 'bg-success' : 'bg-secondary'; ?>"><?php echo (int)$student['registration_count']; ?></span></td>
                            <td class="text-center"><span class="badge bg-<?php echo ($student['academic_status'] ?? '') === 'Đang học' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($student['academic_status'] ?? ''); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto">Tác vụ trong modal áp dụng cho lớp đang xem.</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (false && $viewClass): ?>
<div class="card mt-3" id="classStudents">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-people-fill me-2"></i>
            Sinh viên lớp <?php echo htmlspecialchars($viewClass['class_code'] . ' - ' . $viewClass['class_name']); ?>
        </span>
        <span class="badge bg-light text-dark"><?php echo number_format(count($classStudents)); ?> SV</span>
    </div>
    <div class="card-body py-2 small text-muted">
        <?php echo htmlspecialchars(($viewClass['faculty_name'] ?? '') . ' | ' . ($viewClass['major_name'] ?? '') . ' | Khóa ' . (int)$viewClass['enrollment_year']); ?>
    </div>
    <?php if (empty($classStudents)): ?>
    <div class="card-body text-center text-muted py-4">Lớp này chưa có sinh viên.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Mã SV</th><th>Họ tên</th><th>Email</th><th class="text-center">Giới tính</th><th class="text-center">Ngày sinh</th><th class="text-center">Đăng ký môn</th><th class="text-center">Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($classStudents as $student): ?>
            <tr>
                <td><code><?php echo htmlspecialchars($student['student_code']); ?></code></td>
                <td class="fw-semibold small"><?php echo htmlspecialchars($student['full_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars($student['email']); ?></td>
                <td class="text-center small"><?php echo htmlspecialchars($student['gender'] ?? ''); ?></td>
                <td class="text-center small"><?php echo !empty($student['birthday']) ? date('d/m/Y', strtotime($student['birthday'])) : '—'; ?></td>
                <td class="text-center"><span class="badge <?php echo (int)$student['registration_count'] > 0 ? 'bg-success' : 'bg-secondary'; ?>"><?php echo (int)$student['registration_count']; ?></span></td>
                <td class="text-center"><span class="badge bg-<?php echo ($student['academic_status'] ?? '') === 'Đang học' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($student['academic_status'] ?? ''); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<?php if (isAcademicManager()): ?>
<?php if ($filterMode === 'test'): ?>
<div class="modal fade" id="previousTestStudentsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="generate_previous_test_students"><input type="hidden" name="data_mode" value="test">
<div class="modal-header"><h5 class="modal-title">Tạo sinh viên test khóa trước</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body row g-3">
    <div class="col-12">
        <div class="alert alert-info small mb-0">
            Dùng để tạo sinh viên demo khóa trước, ví dụ khóa 2025 khi đang test học kỳ 2026-2027. Sinh viên được tạo chưa có đăng ký môn tự động nên có thể dùng để kiểm thử luồng đăng ký học phần.
        </div>
    </div>
    <div class="col-md-7">
        <label class="form-label">Ngành *</label>
        <select name="major_id" class="form-select" required>
            <option value="">Chọn ngành</option>
            <?php foreach ($majors as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>" <?php echo $filterMajor === (int)$m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['major_name'] . ' - ' . $m['faculty_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label">Năm tuyển sinh test *</label>
        <input type="number" name="enrollment_year" min="2000" max="2100" class="form-control" value="<?php echo (int)$defaultPreviousTestYear; ?>" required>
        <div class="form-text">Nếu học kỳ test là 2026-2027, nên dùng khóa 2025 để tránh tự động đăng ký HK1.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Số sinh viên cần tạo *</label>
        <input type="number" name="student_count" min="1" max="200" class="form-control" value="40" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Sĩ số lớp *</label>
        <input type="number" name="max_students" min="1" max="300" class="form-control" value="70" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Tài khoản mặc định</label>
        <input class="form-control" value="Mã sinh viên" disabled>
    </div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-navy"><i class="bi bi-person-plus-fill me-1"></i>Tạo dữ liệu test</button></div>
</form></div></div></div>
<?php endif; ?>

<div class="modal fade" id="generateModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="generate_from_round"><input type="hidden" name="data_mode" value="<?php echo htmlspecialchars($filterMode); ?>">
<div class="modal-header"><h5 class="modal-title">Mở lớp hành chính từ đợt tuyển sinh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body row g-3">
    <div class="col-md-7">
        <label class="form-label">Đợt tuyển sinh *</label>
        <select name="round_id" id="generateRound" class="form-select" required>
            <option value="">Chọn đợt tuyển sinh</option>
            <?php foreach ($rounds as $r): ?>
            <option value="<?php echo (int)$r['id']; ?>"
                data-year="<?php echo (int)$r['year']; ?>"
                data-mode="<?php echo htmlspecialchars($r['data_mode']); ?>">
                <?php echo htmlspecialchars(($r['name'] ?? 'Đợt tuyển sinh') . ' - khóa ' . $r['year'] . ' - ' . (($r['data_mode'] ?? 'system') === 'test' ? 'Test/Demo' : 'Dữ liệu thật') . ' - ' . $r['status']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Hiển thị tất cả đợt tuyển sinh chưa hoàn tất, gồm Dữ liệu thật và Test/Demo.</div>
    </div>
    <div class="col-md-5">
        <label class="form-label">Khóa được mở</label>
        <input id="generateYearView" class="form-control" value="Chọn đợt tuyển sinh trước" disabled>
    </div>
    <div class="col-md-8">
        <label class="form-label">Ngành *</label>
        <select name="major_id" class="form-select" required>
            <option value="">Chọn ngành</option>
            <?php foreach ($majors as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['major_name'] . ' - ' . $m['faculty_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Số lớp cần mở *</label>
        <input type="number" name="class_count" min="1" max="20" class="form-control" value="1" required>
        <label class="form-label mt-2">Sĩ số/lớp *</label>
        <input type="number" name="max_students" min="1" max="300" class="form-control" value="70" required>
    </div>
    <div class="col-12">
        <?php if (empty($rounds)): ?>
        <div class="alert alert-warning small mb-0">
            Chưa có đợt tuyển sinh đang mở. Chỉ các đợt chưa hoàn tất mới được mở lớp hành chính.
        </div>
        <?php else: ?>
        <div class="alert alert-info small mb-0">
            Mã lớp và tên lớp sẽ được sinh tự động theo ngành và khóa, ví dụ <strong>D26CNTT01</strong>. Nếu ngành đã có lớp trong cùng đợt, hệ thống mở tiếp số thứ tự kế tiếp.
        </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-gold" <?php echo empty($rounds) ? 'disabled' : ''; ?>>Mở lớp</button></div>
</form></div></div></div>

<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="add">
<div class="modal-header"><h5 class="modal-title">Thêm lớp hành chính mới</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Chế độ dữ liệu</label><select name="data_mode" id="addMode" class="form-select"><option value="system" <?php echo $filterMode==='system'?'selected':''; ?>>Dữ liệu thật</option><option value="test" <?php echo $filterMode==='test'?'selected':''; ?>>Test / Demo</option></select></div>
    <div class="col-md-6"><label class="form-label">Đợt tuyển sinh</label><select id="admissionRound" class="form-select"><option value="">Chọn nhanh theo đợt tuyển sinh</option><?php foreach ($rounds as $r): ?><option value="<?php echo (int)$r['year']; ?>"><?php echo htmlspecialchars(($r['name'] ?? 'Đợt tuyển sinh') . ' - ' . $r['year'] . ' (' . $r['status'] . ')'); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Mã lớp *</label><input name="class_code" class="form-control" required placeholder="VD: CNTT26-01"></div>
    <div class="col-md-6"><label class="form-label">Tên lớp *</label><input name="class_name" class="form-control" required placeholder="VD: Công nghệ thông tin K26 - Lớp 1"></div>
    <div class="col-md-6"><label class="form-label">Ngành *</label><select name="major_id" class="form-select" required><option value="">Chọn ngành</option><?php foreach ($majors as $m): ?><option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['major_name'] . ' - ' . $m['faculty_name']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Năm tuyển sinh *</label><input type="number" name="enrollment_year" id="addYear" min="2000" max="2100" class="form-control" required value="<?php echo date('Y'); ?>"></div>
    <div class="col-md-3"><label class="form-label">Niên khóa</label><input name="school_year" id="addSchoolYear" class="form-control" placeholder="VD: 2026-2030"></div>
    <div class="col-md-3"><label class="form-label">Sĩ số *</label><input type="number" name="max_students" min="1" max="300" class="form-control" required value="70"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-navy">Lưu lớp</button></div>
</form></div></div></div>

<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
<div class="modal-header"><h5 class="modal-title">Sửa lớp hành chính</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Chế độ dữ liệu</label><select name="data_mode" id="editMode" class="form-select" disabled><option value="system">Dữ liệu thật</option><option value="test">Test / Demo</option></select></div>
    <div class="col-md-6"><label class="form-label">Ngành *</label><select name="major_id" id="editMajor" class="form-select" disabled><option value="">Chọn ngành</option><?php foreach ($majors as $m): ?><option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['major_name'] . ' - ' . $m['faculty_name']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Mã lớp *</label><input name="class_code" id="editCode" class="form-control" readonly></div>
    <div class="col-md-6"><label class="form-label">Tên lớp *</label><input name="class_name" id="editName" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Sĩ số *</label><input type="number" name="max_students" id="editMaxStudents" min="1" max="300" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Năm tuyển sinh *</label><input type="number" name="enrollment_year" id="editYear" min="2000" max="2100" class="form-control" readonly></div>
    <div class="col-md-3"><label class="form-label">Niên khóa</label><input name="school_year" id="editSchoolYear" class="form-control" readonly></div>
    <div class="col-12"><div class="alert alert-warning small mb-0"><i class="bi bi-lock-fill me-1"></i>Chỉ được sửa tên lớp và sĩ số. Mã lớp, ngành, khóa và chế độ dữ liệu là khóa định danh của hệ thống.</div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-navy">Cập nhật</button></div>
</form></div></div></div>
<script>
const addYear = document.getElementById('addYear');
const addSchoolYear = document.getElementById('addSchoolYear');
document.getElementById('generateRound')?.addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    const year = opt?.dataset.year || '';
    const mode = opt?.dataset.mode === 'test' ? 'Test/Demo' : 'Dữ liệu thật';
    document.getElementById('generateYearView').value = year ? `Khóa ${year} - ${mode}` : 'Chọn đợt tuyển sinh trước';
});
document.getElementById('admissionRound')?.addEventListener('change', function() {
    if (!this.value) return;
    addYear.value = this.value;
    addSchoolYear.value = `${this.value}-${parseInt(this.value, 10) + 4}`;
});
addYear?.addEventListener('input', function() {
    const y = parseInt(this.value || 0, 10);
    if (y >= 2000) addSchoolYear.value = `${y}-${y + 4}`;
});
document.getElementById('editModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value = b.dataset.id;
    document.getElementById('editMode').value = b.dataset.mode;
    document.getElementById('editMajor').value = b.dataset.major;
    document.getElementById('editCode').value = b.dataset.code;
    document.getElementById('editName').value = b.dataset.name;
    document.getElementById('editYear').value = b.dataset.year;
    document.getElementById('editMaxStudents').value = b.dataset.maxStudents || 70;
    document.getElementById('editSchoolYear').value = b.dataset.schoolYear;
});
</script>
<?php endif; ?>
<script>
document.getElementById('filterFaculty')?.addEventListener('change', function() {
    const faculty = this.value;
    const major = document.getElementById('filterMajor');
    Array.from(major.options).forEach(opt => {
        opt.hidden = opt.value !== '0' && faculty !== '0' && opt.dataset.faculty !== faculty;
    });
    if (major.selectedOptions[0]?.hidden) major.value = '0';
});
document.addEventListener('DOMContentLoaded', function() {
    const classStudentsModal = document.getElementById('classStudentsModal');
    if (classStudentsModal && window.bootstrap) {
        new bootstrap.Modal(classStudentsModal).show();
    }
});
</script>
<?php include 'includes/footer.php'; ?>
