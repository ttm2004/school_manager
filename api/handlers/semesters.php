<?php
/** API: /api/semesters */
requireApiAuth();

// GET /api/semesters
if ($method === 'GET' && !$action) {
    $status = trim($_GET['status'] ?? '');
    $sql    = "SELECT s.*,
                (SELECT COUNT(*) FROM course_sections cs WHERE cs.semester_id=s.id) AS section_count,
                (SELECT COUNT(*) FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id WHERE cs.semester_id=s.id AND ss.status='registered') AS enrollment_count
               FROM semesters s";
    if ($status) $sql .= " WHERE s.status = " . $conn->quote($status);
    $sql .= " ORDER BY s.id DESC";
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    apiOk($rows);
}

// GET /api/semesters/active
if ($method === 'GET' && $action === 'active') {
    $row = $conn->query(
        "SELECT * FROM semesters WHERE status IN ('active','open') ORDER BY id DESC LIMIT 1"
    )->fetch_assoc();
    apiOk($row);
}

// GET /api/semesters/{id}
if ($method === 'GET' && $id) {
    $stmt = $conn->prepare("SELECT * FROM semesters WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) apiError('Không tìm thấy học kỳ', 404);
    apiOk($row);
}

// POST /api/semesters — tạo mới
if ($method === 'POST' && !$action) {
    requireApiRole(['academic_manager']);
    $name      = trim($body['semester_name'] ?? '');
    $year      = trim($body['school_year'] ?? '');
    $status    = trim($body['status'] ?? 'upcoming');
    $startDate = $body['start_date'] ?? null;
    $endDate   = $body['end_date'] ?? null;
    $regStart  = $body['register_start'] ?? null;
    $regEnd    = $body['register_end'] ?? null;
    $gradeDl   = $body['grade_submit_deadline'] ?? null;
    $propDl    = $body['proposal_deadline'] ?? null;

    if (!$name || !$year) apiError('Tên học kỳ và năm học là bắt buộc');

    $stmt = $conn->prepare(
        "INSERT INTO semesters (semester_name, school_year, start_date, end_date,
         register_start, register_end, grade_submit_deadline, proposal_deadline, status)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('sssssssss', $name,$year,$startDate,$endDate,$regStart,$regEnd,$gradeDl,$propDl,$status);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $stmt->close();
        $row = $conn->query("SELECT * FROM semesters WHERE id=$newId")->fetch_assoc();
        apiOk($row, 'Tạo học kỳ thành công', 201);
    }
    apiError('Lỗi tạo học kỳ: ' . $conn->error);
}

// PUT /api/semesters/{id} — cập nhật
if ($method === 'PUT' && $id) {
    requireApiRole(['academic_manager']);
    $fields = ['semester_name','school_year','start_date','end_date',
               'register_start','register_end','grade_submit_deadline',
               'proposal_deadline','status'];
    $sets = []; $types = ''; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $body)) {
            $sets[]  = "`$f` = ?";
            $types  .= 's';
            $vals[]  = $body[$f] ?: null;
        }
    }
    if (empty($sets)) apiError('Không có dữ liệu cập nhật');
    $types .= 'i'; $vals[] = $id;
    $stmt = $conn->prepare("UPDATE semesters SET " . implode(',', $sets) . " WHERE id=?");
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
    $row = $conn->query("SELECT * FROM semesters WHERE id=$id")->fetch_assoc();
    apiOk($row, 'Cập nhật thành công');
}

// POST /api/semesters/{id}/open-registration
if ($method === 'POST' && $action === 'open-registration' && $id) {
    requireApiRole(['academic_manager']);
    $days     = max(1, (int)($body['days'] ?? 14));
    $regStart = date('Y-m-d H:i:s');
    $regEnd   = date('Y-m-d H:i:s', strtotime("+$days days"));
    $stmt = $conn->prepare("UPDATE semesters SET register_start=?, register_end=?, status='open' WHERE id=?");
    $stmt->bind_param('ssi', $regStart, $regEnd, $id);
    $stmt->execute();
    $stmt->close();
    apiOk(['register_end' => $regEnd], "Đã mở đăng ký đến " . date('d/m/Y H:i', strtotime($regEnd)));
}
