<?php
/** API: /api/course-sections */
requireApiAuth();
require_once __DIR__ . '/../../includes/AcademicPolicy.php';

// GET /api/course-sections
if ($method === 'GET' && !$action) {
    $semId     = (int)($_GET['semester_id'] ?? 0);
    $facultyId = (int)($_GET['faculty_id'] ?? 0);
    $status    = trim($_GET['status'] ?? '');
    $search    = trim($_GET['q'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

    $where = ['1=1']; $types = ''; $params = [];
    if ($semId)     { $where[] = 'cs.semester_id=?'; $types .= 'i'; $params[] = $semId; }
    if ($facultyId) { $where[] = 'f.id=?';           $types .= 'i'; $params[] = $facultyId; }
    if ($status)    { $where[] = 'cs.status=?';       $types .= 's'; $params[] = $status; }
    if ($search)    {
        $where[] = '(s.subject_name LIKE ? OR cs.section_code LIKE ?)';
        $like = "%$search%"; $types .= 'ss'; $params[] = $like; $params[] = $like;
    }
    $whereSQL = implode(' AND ', $where);

    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id LEFT JOIN majors m ON s.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL");
    if ($types) $stmtCnt->bind_param($types, ...$params);
    $stmtCnt->execute();
    $total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCnt->close();

    $offset = ($page - 1) * $perPage;
    $stmtData = $conn->prepare(
        "SELECT cs.id, cs.section_code, cs.status, cs.proposal_status,
                cs.max_students, cs.current_students, cs.room, cs.day_sessions,
                cs.start_date, cs.end_date, cs.teaching_mode,
                cs.expected_students, cs.open_proposal_note,
                s.id AS subject_id, s.subject_code, s.subject_name, s.credits,
                sm.id AS semester_id, sm.semester_name,
                f.id AS faculty_id, f.faculty_name,
                t.id AS teacher_id, ut.full_name AS teacher_name, t.teacher_code
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id=s.id
         JOIN semesters sm ON cs.semester_id=sm.id
         LEFT JOIN majors m ON s.major_id=m.id
         LEFT JOIN faculties f ON m.faculty_id=f.id
         LEFT JOIN teachers t ON cs.teacher_id=t.id
         LEFT JOIN users ut ON t.user_id=ut.id
         WHERE $whereSQL
         ORDER BY f.faculty_name, s.subject_name
         LIMIT ? OFFSET ?"
    );
    $allTypes = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmtData->bind_param($allTypes, ...$allParams);
    $stmtData->execute();
    $rows = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();

    apiOk([
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ]);
}

// GET /api/course-sections/{id}
if ($method === 'GET' && $id && !$action) {
    $stmt = $conn->prepare(
        "SELECT cs.*, s.subject_code, s.subject_name, s.credits,
                sm.semester_name, f.faculty_name,
                ut.full_name AS teacher_name, t.teacher_code,
                COUNT(DISTINCT ss.student_id) AS enrolled_count
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id=s.id
         JOIN semesters sm ON cs.semester_id=sm.id
         LEFT JOIN majors m ON s.major_id=m.id
         LEFT JOIN faculties f ON m.faculty_id=f.id
         LEFT JOIN teachers t ON cs.teacher_id=t.id
         LEFT JOIN users ut ON t.user_id=ut.id
         LEFT JOIN student_subjects ss ON ss.course_section_id=cs.id AND ss.status IN ('registered','auto_enrolled')
         WHERE cs.id=?
         GROUP BY cs.id"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) apiError('Không tìm thấy lớp HP', 404);
    apiOk($row);
}

// GET /api/course-sections/{id}/students
if ($method === 'GET' && $id && $action === 'students') {
    $stmt = $conn->prepare(
        "SELECT ss.id AS enrollment_id, ss.status AS enrollment_status, ss.register_date,
                st.id AS student_id, st.student_code, u.full_name, u.email,
                g.process_score, g.midterm_score, g.final_score, g.total_score, g.letter_grade
         FROM student_subjects ss
         JOIN students st ON ss.student_id=st.id
         JOIN users u ON st.user_id=u.id
         LEFT JOIN grades g ON g.student_subject_id=ss.id
         WHERE ss.course_section_id=? AND ss.status IN ('registered','auto_enrolled')
         ORDER BY u.full_name"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    apiOk($rows);
}

// POST /api/course-sections — tạo lớp HP
if ($method === 'POST' && !$action) {
    requireApiRole(['academic_manager', 'faculty_manager']);
    $subjectId  = (int)($body['subject_id'] ?? 0);
    $semesterId = (int)($body['semester_id'] ?? 0);
    $code       = trim($body['section_code'] ?? '');
    $teacherId  = (int)($body['teacher_id'] ?? 0) ?: null;
    $room       = trim($body['room'] ?? '');
    $maxStu     = max(1, (int)($body['max_students'] ?? 40));
    $status     = trim($body['status'] ?? 'open');
    $daySession = trim($body['day_sessions'] ?? '');
    $mode       = trim($body['teaching_mode'] ?? 'offline');
    $expStu     = (int)($body['expected_students'] ?? 0) ?: null;

    if (!$subjectId || !$semesterId || !$code) {
        apiError('subject_id, semester_id, section_code là bắt buộc');
    }

    $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
    $stmt = $conn->prepare(
        "INSERT INTO course_sections
         (subject_id, teacher_id, semester_id, section_code, room, max_students,
          current_students, status, data_mode, demo_batch_id, day_sessions, teaching_mode, expected_students)
         VALUES (?,?,?,?,?,?,0,?,?,?,?,?,?)"
    );
    $stmt->bind_param('iiississsssi', $subjectId,$teacherId,$semesterId,$code,$room,$maxStu,$status,$demoContext['data_mode'],$demoContext['demo_batch_id'],$daySession,$mode,$expStu);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $stmt->close();
        $row = $conn->query("SELECT cs.*, s.subject_name FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id WHERE cs.id=$newId")->fetch_assoc();
        apiOk($row, 'Tạo lớp HP thành công', 201);
    }
    apiError('Lỗi: ' . $conn->error);
}

// PUT /api/course-sections/{id} — cập nhật
if ($method === 'PUT' && $id && !$action) {
    requireApiRole(['academic_manager', 'faculty_manager']);
    $allowed = ['teacher_id','room','max_students','status','day_sessions',
                'teaching_mode','expected_students','open_proposal_note',
                'start_date','end_date'];
    $sets = []; $types = ''; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            $sets[]  = "`$f` = ?";
            $types  .= 's';
            $vals[]  = $body[$f] !== '' ? $body[$f] : null;
        }
    }
    if (empty($sets)) apiError('Không có dữ liệu cập nhật');
    $types .= 'i'; $vals[] = $id;
    $stmt = $conn->prepare("UPDATE course_sections SET " . implode(',', $sets) . " WHERE id=?");
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
    apiOk(['id' => $id], 'Cập nhật thành công');
}

// POST /api/course-sections/{id}/propose — Khoa gửi đề xuất
if ($method === 'POST' && $id && $action === 'propose') {
    requireApiRole(['faculty_manager']);
    $note    = trim($body['note'] ?? '');
    $expStu  = (int)($body['expected_students'] ?? 0);
    $userId  = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare(
        "UPDATE course_sections SET status='proposed', open_proposed_by=?, open_proposed_at=NOW(),
         open_proposal_note=?, expected_students=? WHERE id=? AND status IN ('draft','open')"
    );
    $stmt->bind_param('isii', $userId, $note, $expStu, $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) apiError('Không thể gửi đề xuất. Kiểm tra trạng thái lớp HP.');
    $stmt->close();
    apiOk(['id' => $id, 'status' => 'proposed'], 'Đã gửi đề xuất lên Phòng Đào tạo');
}

// POST /api/course-sections/{id}/approve — Phòng ĐT duyệt
if ($method === 'POST' && $id && $action === 'approve') {
    requireApiRole(['academic_manager']);
    $room    = trim($body['room'] ?? '');
    $maxStu  = (int)($body['max_students'] ?? 40);
    $mode    = trim($body['teaching_mode'] ?? 'offline');
    $userId  = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare(
        "UPDATE course_sections SET status='open', room=?, max_students=?, teaching_mode=?,
         open_reviewed_by=?, open_reviewed_at=NOW() WHERE id=? AND status='proposed'"
    );
    $stmt->bind_param('sisii', $room, $maxStu, $mode, $userId, $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) apiError('Không thể duyệt. Lớp HP không ở trạng thái proposed.');
    $stmt->close();
    apiOk(['id' => $id, 'status' => 'open'], 'Đã duyệt mở lớp HP');
}

// POST /api/course-sections/{id}/reject — Phòng ĐT từ chối
if ($method === 'POST' && $id && $action === 'reject') {
    requireApiRole(['academic_manager']);
    $reason = trim($body['reason'] ?? '');
    if (!$reason) apiError('Vui lòng nhập lý do từ chối');
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare(
        "UPDATE course_sections SET status='cancelled', open_reject_reason=?,
         open_reviewed_by=?, open_reviewed_at=NOW() WHERE id=? AND status='proposed'"
    );
    $stmt->bind_param('sii', $reason, $userId, $id);
    $stmt->execute();
    $stmt->close();
    apiOk(['id' => $id, 'status' => 'cancelled'], 'Đã từ chối đề xuất');
}

