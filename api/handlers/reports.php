<?php
/** API: /api/reports */
requireApiRole(['academic_manager', 'academic_staff', 'faculty_manager', 'faculty_staff', 'admin']);

// GET /api/reports/dashboard
if ($method === 'GET' && $action === 'dashboard') {
    $semId = (int)($_GET['semester_id'] ?? 0);
    if (!$semId) {
        $sem = $conn->query("SELECT id FROM semesters WHERE status IN ('active','open') ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $semId = $sem ? (int)$sem['id'] : 0;
    }

    $data = [
        'total_students'  => (int)$conn->query("SELECT COUNT(*) AS c FROM students WHERE academic_status='Đang học'")->fetch_assoc()['c'],
        'total_teachers'  => (int)$conn->query("SELECT COUNT(*) AS c FROM teachers")->fetch_assoc()['c'],
        'total_faculties' => (int)$conn->query("SELECT COUNT(*) AS c FROM faculties")->fetch_assoc()['c'],
        'total_majors'    => (int)$conn->query("SELECT COUNT(*) AS c FROM majors")->fetch_assoc()['c'],
    ];

    if ($semId) {
        $data['semester_id']       = $semId;
        $data['open_sections']     = (int)$conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE semester_id=$semId AND status='open'")->fetch_assoc()['c'];
        $data['proposed_sections'] = (int)$conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE semester_id=$semId AND status='proposed'")->fetch_assoc()['c'];
        $data['total_enrollments'] = (int)$conn->query("SELECT COUNT(*) AS c FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id WHERE cs.semester_id=$semId AND ss.status IN ('registered','auto_enrolled')")->fetch_assoc()['c'];
        $data['no_teacher']        = (int)$conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE semester_id=$semId AND status='open' AND (teacher_id IS NULL OR teacher_id=0)")->fetch_assoc()['c'];
        $data['no_exam']           = (int)$conn->query("SELECT COUNT(*) AS c FROM course_sections cs LEFT JOIN final_exam_schedules fes ON cs.id=fes.course_section_id WHERE cs.semester_id=$semId AND cs.status='open' AND fes.id IS NULL")->fetch_assoc()['c'];
        $data['pending_proposals'] = (int)$conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE proposal_status='pending'")->fetch_assoc()['c'];
    }

    apiOk($data);
}

// GET /api/reports/grade-stats?semester_id=X&faculty_id=X
if ($method === 'GET' && $action === 'grade-stats') {
    $semId     = (int)($_GET['semester_id'] ?? 0);
    $facultyId = (int)($_GET['faculty_id'] ?? 0);
    if (!$semId) apiError('semester_id là bắt buộc');

    $where = ['cs.semester_id=?']; $types = 'i'; $params = [$semId];
    if ($facultyId) { $where[] = 'f.id=?'; $types .= 'i'; $params[] = $facultyId; }
    $whereSQL = implode(' AND ', $where);

    $stmt = $conn->prepare(
        "SELECT cs.id, cs.section_code, s.subject_name, f.faculty_name,
                ut.full_name AS teacher_name,
                COUNT(ss.id) AS total_students,
                SUM(CASE WHEN g.final_score IS NOT NULL THEN 1 ELSE 0 END) AS graded,
                SUM(CASE WHEN g.final_score >= 5.0 THEN 1 ELSE 0 END) AS passed,
                ROUND(AVG(g.final_score), 2) AS avg_score,
                ROUND(SUM(CASE WHEN g.final_score >= 5.0 THEN 1 ELSE 0 END) / NULLIF(COUNT(g.id),0) * 100, 1) AS pass_rate
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id=s.id
         LEFT JOIN majors m ON s.major_id=m.id
         LEFT JOIN faculties f ON m.faculty_id=f.id
         LEFT JOIN teachers t ON cs.teacher_id=t.id
         LEFT JOIN users ut ON t.user_id=ut.id
         LEFT JOIN student_subjects ss ON ss.course_section_id=cs.id AND ss.status IN ('registered','auto_enrolled')
         LEFT JOIN grades g ON g.student_subject_id=ss.id
         WHERE $whereSQL
         GROUP BY cs.id, cs.section_code, s.subject_name, f.faculty_name, ut.full_name
         ORDER BY pass_rate ASC"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    apiOk($rows);
}

// GET /api/reports/students-by-faculty
if ($method === 'GET' && $action === 'students-by-faculty') {
    $rows = $conn->query(
        "SELECT f.id, f.faculty_name, f.faculty_code,
                COUNT(DISTINCT s.id) AS total_students,
                SUM(CASE WHEN s.academic_status='Đang học' THEN 1 ELSE 0 END) AS active,
                COUNT(DISTINCT t.id) AS total_teachers,
                COUNT(DISTINCT m.id) AS total_majors
         FROM faculties f
         LEFT JOIN majors m ON m.faculty_id=f.id
         LEFT JOIN classes cl ON cl.major_id=m.id
         LEFT JOIN students s ON s.class_id=cl.id
         LEFT JOIN teachers t ON t.faculty_id=f.id
         GROUP BY f.id, f.faculty_name, f.faculty_code
         ORDER BY f.faculty_name"
    )->fetch_all(MYSQLI_ASSOC);
    apiOk($rows);
}

