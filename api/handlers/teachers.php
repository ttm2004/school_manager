<?php
/** API: /api/teachers */
requireApiAuth();

// GET /api/teachers
if ($method === 'GET' && !$action) {
    $facultyId = (int)($_GET['faculty_id'] ?? 0);
    $degree    = trim($_GET['degree'] ?? '');
    $search    = trim($_GET['q'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

    $where = ['1=1']; $types = ''; $params = [];
    if ($facultyId) { $where[] = 't.faculty_id=?'; $types .= 'i'; $params[] = $facultyId; }
    if ($degree)    { $where[] = 't.degree=?';      $types .= 's'; $params[] = $degree; }
    if ($search)    {
        $where[] = '(u.full_name LIKE ? OR t.teacher_code LIKE ?)';
        $like = "%$search%"; $types .= 'ss'; $params[] = $like; $params[] = $like;
    }
    $whereSQL = implode(' AND ', $where);

    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM teachers t JOIN users u ON t.user_id=u.id WHERE $whereSQL");
    if ($types) $stmtCnt->bind_param($types, ...$params);
    $stmtCnt->execute();
    $total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCnt->close();

    $offset = ($page - 1) * $perPage;
    $stmtData = $conn->prepare(
        "SELECT t.id, t.teacher_code, t.degree, t.specialization,
                u.full_name, u.email, u.phone, u.avatar,
                f.faculty_name, f.id AS faculty_id
         FROM teachers t
         JOIN users u ON t.user_id=u.id
         LEFT JOIN faculties f ON t.faculty_id=f.id
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

    apiOk(['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// GET /api/teachers/me
if ($method === 'GET' && $action === 'me') {
    if ($_SESSION['role'] !== 'teacher') apiError('Chỉ dành cho giảng viên', 403);
    $stmt = $conn->prepare(
        "SELECT t.*, u.full_name, u.email, u.phone, u.avatar, f.faculty_name
         FROM teachers t JOIN users u ON t.user_id=u.id LEFT JOIN faculties f ON t.faculty_id=f.id
         WHERE t.user_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) apiError('Không tìm thấy thông tin giảng viên', 404);
    apiOk($row);
}

// GET /api/teachers/{id}/courses
if ($method === 'GET' && $id && $action === 'courses') {
    $semId = (int)($_GET['semester_id'] ?? 0);
    $sql = "SELECT cs.id, cs.section_code, cs.status, cs.max_students, cs.current_students,
                   cs.room, cs.day_sessions, cs.start_date, cs.end_date,
                   s.subject_name, s.credits, sm.semester_name, sm.school_year
            FROM course_sections cs
            JOIN subjects s ON cs.subject_id=s.id
            JOIN semesters sm ON cs.semester_id=sm.id
            WHERE cs.teacher_id=?";
    $types = 'i'; $params = [$id];
    if ($semId) { $sql .= " AND cs.semester_id=?"; $types .= 'i'; $params[] = $semId; }
    $sql .= " ORDER BY sm.id DESC, s.subject_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    apiOk($rows);
}

// GET /api/teachers/{id}/workload
if ($method === 'GET' && $id && $action === 'workload') {
    $semId = (int)($_GET['semester_id'] ?? 0);
    if (!$semId) {
        $sem = $conn->query("SELECT id FROM semesters WHERE status IN ('active','open') ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $semId = $sem ? (int)$sem['id'] : 0;
    }
    $stmt = $conn->prepare(
        "SELECT SUM(s.credits) AS total_credits, COUNT(cs.id) AS total_sections
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id=s.id
         WHERE cs.teacher_id=? AND cs.semester_id=? AND cs.status IN ('open','closed')"
    );
    $stmt->bind_param('ii', $id, $semId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    apiOk([
        'teacher_id'     => $id,
        'semester_id'    => $semId,
        'total_credits'  => (int)($row['total_credits'] ?? 0),
        'total_sections' => (int)($row['total_sections'] ?? 0),
        'is_overloaded'  => (int)($row['total_credits'] ?? 0) > 20,
    ]);
}
