<?php
/** API: /api/students */
requireApiAuth();
require_once __DIR__ . '/../../includes/AcademicPolicy.php';

// GET /api/students
if ($method === 'GET' && !$action) {
    $facultyId = (int)($_GET['faculty_id'] ?? 0);
    $majorId   = (int)($_GET['major_id'] ?? 0);
    $status    = trim($_GET['status'] ?? '');
    $search    = trim($_GET['q'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

    $where = ['1=1']; $types = ''; $params = [];
    if ($facultyId) { $where[] = 'f.id=?';              $types .= 'i'; $params[] = $facultyId; }
    if ($majorId)   { $where[] = 'm.id=?';              $types .= 'i'; $params[] = $majorId; }
    if ($status)    { $where[] = 's.academic_status=?'; $types .= 's'; $params[] = $status; }
    if ($search)    {
        $where[] = '(u.full_name LIKE ? OR s.student_code LIKE ? OR u.email LIKE ?)';
        $like = "%$search%"; $types .= 'sss';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $whereSQL = implode(' AND ', $where);

    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM students s JOIN users u ON s.user_id=u.id JOIN classes cl ON s.class_id=cl.id JOIN majors m ON cl.major_id=m.id JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL");
    if ($types) $stmtCnt->bind_param($types, ...$params);
    $stmtCnt->execute();
    $total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCnt->close();

    $offset = ($page - 1) * $perPage;
    $stmtData = $conn->prepare(
        "SELECT s.id, s.student_code, s.academic_status, s.enrollment_year,
                u.full_name, u.email, u.phone, u.avatar,
                m.major_name, f.faculty_name, cl.class_name
         FROM students s
         JOIN users u ON s.user_id=u.id
         JOIN classes cl ON s.class_id=cl.id
         JOIN majors m ON cl.major_id=m.id
         JOIN faculties f ON m.faculty_id=f.id
         WHERE $whereSQL
         ORDER BY f.faculty_name, u.full_name
         LIMIT ? OFFSET ?"
    );
    $allTypes = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmtData->bind_param($allTypes, ...$allParams);
    $stmtData->execute();
    $rows = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();

    apiOk(['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => (int)ceil($total/$perPage)]);
}

// GET /api/students/{id}
if ($method === 'GET' && $id && !$action) {
    $stmt = $conn->prepare(
        "SELECT s.*, u.full_name, u.email, u.phone, u.avatar, u.username,
                m.major_name, m.major_code, f.faculty_name, cl.class_name
         FROM students s
         JOIN users u ON s.user_id=u.id
         JOIN classes cl ON s.class_id=cl.id
         JOIN majors m ON cl.major_id=m.id
         JOIN faculties f ON m.faculty_id=f.id
         WHERE s.id=? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) apiError('Không tìm thấy sinh viên', 404);
    apiOk($row);
}

// GET /api/students/{id}/grades
if ($method === 'GET' && $id && $action === 'grades') {
    $semId = (int)($_GET['semester_id'] ?? 0);
    $sql = "SELECT cs.section_code, s.subject_name, s.credits, sm.semester_name,
                   g.process_score, g.midterm_score, g.final_score, g.total_score, g.letter_grade
            FROM student_subjects ss
            JOIN course_sections cs ON ss.course_section_id=cs.id
            JOIN subjects s ON cs.subject_id=s.id
            JOIN semesters sm ON cs.semester_id=sm.id
            LEFT JOIN grades g ON g.student_subject_id=ss.id
            WHERE ss.student_id=? AND ss.status IN ('registered','auto_enrolled')";
    $types = 'i'; $params = [$id];
    if ($semId) { $sql .= " AND cs.semester_id=?"; $types .= 'i'; $params[] = $semId; }
    $sql .= " ORDER BY sm.id DESC, s.subject_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Tính GPA
    $gpa = null;
    $totalCredits = 0;
    $passCredits  = 0;
    foreach ($rows as $r) {
        if ($r['total_score'] !== null) {
            $gpa = ($gpa ?? 0) + (float)$r['total_score'];
            $totalCredits += (int)$r['credits'];
            if ((float)$r['final_score'] >= 5.0) $passCredits += (int)$r['credits'];
        }
    }
    if ($gpa !== null && count($rows) > 0) {
        $gpa = round($gpa / count(array_filter($rows, fn($r) => $r['total_score'] !== null)), 2);
    }

    apiOk(['grades' => $rows, 'gpa' => $gpa, 'total_credits' => $totalCredits, 'pass_credits' => $passCredits]);
}

// GET /api/students/me — SV xem thông tin của mình
if ($method === 'GET' && $action === 'me') {
    if ($_SESSION['role'] !== 'student') apiError('Chỉ dành cho sinh viên', 403);
    $stmt = $conn->prepare(
        "SELECT s.*, u.full_name, u.email, u.phone, u.avatar,
                m.major_name, f.faculty_name, cl.class_name
         FROM students s
         JOIN users u ON s.user_id=u.id
         JOIN classes cl ON s.class_id=cl.id
         JOIN majors m ON cl.major_id=m.id
         JOIN faculties f ON m.faculty_id=f.id
         WHERE s.user_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) apiError('Không tìm thấy thông tin sinh viên', 404);
    apiOk($row);
}

// POST /api/students/{id}/register — SV đăng ký môn
if ($method === 'POST' && $id && $action === 'register') {
    if ($_SESSION['role'] !== 'student') apiError('Chỉ sinh viên mới được đăng ký', 403);

    $sectionId = (int)($body['course_section_id'] ?? 0);
    if (!$sectionId) apiError('course_section_id là bắt buộc');

    // Kiểm tra học kỳ mở đăng ký
    $sem = $conn->query("SELECT * FROM semesters WHERE status='open' AND register_start<=NOW() AND register_end>=NOW() LIMIT 1")->fetch_assoc();
    if (!$sem) apiError('Hiện không trong thời gian đăng ký học phần');

    // Kiểm tra nợ học phí
    $studentRow = $conn->query("SELECT id, data_mode, demo_batch_id FROM students WHERE user_id={$_SESSION['user_id']} LIMIT 1")->fetch_assoc();
    $myStudentId = (int)$studentRow['id'];
    $studentDataMode = (($studentRow['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
    $demoBatchId = (string)($studentRow['demo_batch_id'] ?? '');
    $policy = academicPolicyValidateStudentRegistration($conn, $myStudentId, $sectionId);
    if (!$policy['ok']) {
        apiError($policy['message']);
    }
    if (function_exists('hasTuitionDebt') && hasTuitionDebt($myStudentId)) {
        apiError('Bạn đang nợ học phí. Vui lòng đóng học phí trước khi đăng ký');
    }

    // Kiểm tra đã đăng ký chưa
    $chk = $conn->prepare("SELECT id, status FROM student_subjects WHERE student_id=? AND course_section_id=? LIMIT 1");
    $chk->bind_param('ii', $myStudentId, $sectionId);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($existing && $existing['status'] !== 'cancelled') apiError('Bạn đã đăng ký lớp học phần này rồi');

    // Kiểm tra còn chỗ
    $sec = $conn->query("SELECT max_students, current_students, status FROM course_sections WHERE id=$sectionId LIMIT 1")->fetch_assoc();
    if (!$sec || $sec['status'] !== 'open') apiError('Lớp học phần không mở đăng ký');
    if ($sec['current_students'] >= $sec['max_students']) apiError('Lớp học phần đã đầy');

    if ($existing) {
        $stmt = $conn->prepare("UPDATE student_subjects SET status='registered', register_date=NOW(), data_mode=?, demo_batch_id=? WHERE id=? AND student_id=? AND status='cancelled'");
        $stmt->bind_param('ssii', $studentDataMode, $demoBatchId, $existing['id'], $myStudentId);
    } else {
        $stmt = $conn->prepare("INSERT INTO student_subjects (student_id, course_section_id, status, data_mode, demo_batch_id) VALUES (?,?,'registered',?,?)");
        $stmt->bind_param('iiss', $myStudentId, $sectionId, $studentDataMode, $demoBatchId);
    }
    if ($stmt->execute()) {
        $conn->query("UPDATE course_sections SET current_students=current_students+1 WHERE id=$sectionId");
        $stmt->close();
        apiOk(['student_id' => $myStudentId, 'course_section_id' => $sectionId], 'Đăng ký thành công', 201);
    }
    apiError('Lỗi đăng ký: ' . $conn->error);
}

// DELETE /api/students/{id}/register/{sectionId} — Hủy đăng ký
if ($method === 'DELETE' && $id && $action === 'register') {
    if ($_SESSION['role'] !== 'student') apiError('Chỉ sinh viên mới được hủy đăng ký', 403);
    $sectionId = (int)($parts[3] ?? 0);
    $myStudentId = (int)$conn->query("SELECT id FROM students WHERE user_id={$_SESSION['user_id']} LIMIT 1")->fetch_assoc()['id'];

    $stmt = $conn->prepare("UPDATE student_subjects SET status='cancelled' WHERE student_id=? AND course_section_id=? AND status='registered'");
    $stmt->bind_param('ii', $myStudentId, $sectionId);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $conn->query("UPDATE course_sections SET current_students=GREATEST(0,current_students-1) WHERE id=$sectionId");
        $stmt->close();
        apiOk(null, 'Đã hủy đăng ký');
    }
    apiError('Không tìm thấy đăng ký hoặc đã hủy rồi');
}

