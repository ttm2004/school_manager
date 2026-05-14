<?php
/** API: /api/exam-schedules */
requireApiAuth();
require_once __DIR__ . '/../../includes/AcademicPolicy.php';

// GET /api/exam-schedules
if ($method === 'GET' && !$action) {
    $semId     = (int)($_GET['semester_id'] ?? 0);
    $facultyId = (int)($_GET['faculty_id'] ?? 0);
    $date      = trim($_GET['date'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(100, max(1, (int)($_GET['per_page'] ?? 25)));

    $where = ['1=1']; $types = ''; $params = [];
    if ($semId)     { $where[] = 'cs.semester_id=?'; $types .= 'i'; $params[] = $semId; }
    if ($facultyId) { $where[] = 'f.id=?';           $types .= 'i'; $params[] = $facultyId; }
    if ($date)      { $where[] = 'fes.exam_date=?';  $types .= 's'; $params[] = $date; }
    $whereSQL = implode(' AND ', $where);

    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM final_exam_schedules fes JOIN course_sections cs ON fes.course_section_id=cs.id JOIN subjects s ON cs.subject_id=s.id LEFT JOIN majors m ON s.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL");
    if ($types) $stmtCnt->bind_param($types, ...$params);
    $stmtCnt->execute();
    $total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCnt->close();

    $offset = ($page - 1) * $perPage;
    $stmtData = $conn->prepare(
        "SELECT fes.id, fes.exam_date, fes.start_time, fes.end_time, fes.room,
                fes.exam_form, fes.status, fes.note,
                cs.section_code, s.subject_name, s.credits,
                sm.semester_name, f.faculty_name,
                ut.full_name AS teacher_name,
                COUNT(DISTINCT ss.student_id) AS student_count
         FROM final_exam_schedules fes
         JOIN course_sections cs ON fes.course_section_id=cs.id
         JOIN subjects s ON cs.subject_id=s.id
         JOIN semesters sm ON cs.semester_id=sm.id
         LEFT JOIN majors m ON s.major_id=m.id
         LEFT JOIN faculties f ON m.faculty_id=f.id
         LEFT JOIN teachers t ON cs.teacher_id=t.id
         LEFT JOIN users ut ON t.user_id=ut.id
         LEFT JOIN student_subjects ss ON ss.course_section_id=cs.id AND ss.status IN ('registered','auto_enrolled')
         WHERE $whereSQL
         GROUP BY fes.id
         ORDER BY fes.exam_date ASC, fes.start_time ASC
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

// POST /api/exam-schedules — tạo lịch thi
if ($method === 'POST' && !$action) {
    requireApiRole(['academic_manager']);
    $sectionId = (int)($body['course_section_id'] ?? 0);
    $examDate  = trim($body['exam_date'] ?? '');
    $startTime = trim($body['start_time'] ?? '');
    $endTime   = trim($body['end_time'] ?? '');
    $room      = trim($body['room'] ?? '');
    $examForm  = trim($body['exam_form'] ?? 'Tự luận');
    $note      = trim($body['note'] ?? '');
    $status    = trim($body['status'] ?? 'scheduled');

    if (!$sectionId || !$examDate || !$startTime || !$endTime) {
        apiError('course_section_id, exam_date, start_time, end_time là bắt buộc');
    }

    // Kiểm tra ngày thi sau ngày kết thúc HK
    $semEnd = $conn->query("SELECT sm.end_date FROM course_sections cs JOIN semesters sm ON cs.semester_id=sm.id WHERE cs.id=$sectionId LIMIT 1")->fetch_assoc();
    if ($semEnd && $semEnd['end_date'] && $examDate <= $semEnd['end_date']) {
        apiError('Ngày thi phải sau ngày kết thúc học kỳ (' . date('d/m/Y', strtotime($semEnd['end_date'])) . ')');
    }

    $stmt = $conn->prepare(
        "INSERT INTO final_exam_schedules (course_section_id, exam_date, start_time, end_time, room, exam_form, note, status, data_mode, demo_batch_id)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $demoContext = academicPolicySectionDemoContext($conn, $sectionId);
    $stmt->bind_param('isssssssss', $sectionId,$examDate,$startTime,$endTime,$room,$examForm,$note,$status,$demoContext['data_mode'],$demoContext['demo_batch_id']);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $stmt->close();
        apiOk(['id' => $newId], 'Tạo lịch thi thành công', 201);
    }
    apiError('Lỗi: ' . $conn->error);
}

// PUT /api/exam-schedules/{id}
if ($method === 'PUT' && $id) {
    requireApiRole(['academic_manager']);
    $fields = ['exam_date','start_time','end_time','room','exam_form','note','status'];
    $sets = []; $types = ''; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $body)) {
            $sets[] = "`$f`=?"; $types .= 's'; $vals[] = $body[$f];
        }
    }
    if (empty($sets)) apiError('Không có dữ liệu cập nhật');
    $types .= 'i'; $vals[] = $id;
    $stmt = $conn->prepare("UPDATE final_exam_schedules SET " . implode(',', $sets) . " WHERE id=?");
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
    apiOk(['id' => $id], 'Cập nhật thành công');
}

// DELETE /api/exam-schedules/{id}
if ($method === 'DELETE' && $id) {
    requireApiRole(['academic_manager']);
    $stmt = $conn->prepare("DELETE FROM final_exam_schedules WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    apiOk(null, 'Đã xóa lịch thi');
}

