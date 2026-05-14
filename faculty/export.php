<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Khong co quyen truy cap.');
}

$type       = trim($_GET['type'] ?? '');
$semesterId = (int)($_GET['semester_id'] ?? 0);

// Helper: output CSV
function outputCSV(array $headers, array $rows, string $filename): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
}

if ($type === 'teachers') {
    $stmtT = $conn->prepare(
        "SELECT t.teacher_code, u.full_name, t.degree, t.specialization, u.email, u.phone
         FROM teachers t JOIN users u ON t.user_id = u.id
         WHERE t.faculty_id = ?
         ORDER BY u.full_name ASC"
    );
    $stmtT->bind_param('i', $facultyId);
    $stmtT->execute();
    $rows = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtT->close();

    logAudit($conn, $userId, 'export', 'faculty', 'teachers', 0, null, json_encode(['count' => count($rows)]), $ip);

    outputCSV(
        ['Ma GV', 'Ho ten', 'Hoc vi', 'Chuyen nganh', 'Email', 'So dien thoai'],
        array_map(fn($r) => [$r['teacher_code'], $r['full_name'], $r['degree'], $r['specialization'], $r['email'], $r['phone']], $rows),
        'giang_vien_' . date('Ymd') . '.csv'
    );
    exit();
}

if ($type === 'students') {
    $stmtS = $conn->prepare(
        "SELECT s.student_code, u.full_name, m.major_name, c.class_name, s.academic_status, s.enrollment_year,
                AVG(g.total_score) AS gpa
         FROM students s
         JOIN users u ON s.user_id = u.id
         JOIN classes c ON s.class_id = c.id
         JOIN majors m ON c.major_id = m.id
         LEFT JOIN student_subjects ss ON ss.student_id = s.id
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE m.faculty_id = ?
         GROUP BY s.id, s.student_code, u.full_name, m.major_name, c.class_name, s.academic_status, s.enrollment_year
         ORDER BY u.full_name ASC"
    );
    $stmtS->bind_param('i', $facultyId);
    $stmtS->execute();
    $rows = $stmtS->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtS->close();

    logAudit($conn, $userId, 'export', 'faculty', 'students', 0, null, json_encode(['count' => count($rows)]), $ip);

    outputCSV(
        ['Ma SV', 'Ho ten', 'Nganh', 'Lop', 'Trang thai', 'Nam nhap hoc', 'GPA'],
        array_map(fn($r) => [
            $r['student_code'], $r['full_name'], $r['major_name'],
            $r['class_name'] ?? '', $r['academic_status'], $r['enrollment_year'],
            $r['gpa'] !== null ? round((float)$r['gpa'], 2) : ''
        ], $rows),
        'sinh_vien_' . date('Ymd') . '.csv'
    );
    exit();
}

if ($type === 'grades' && $semesterId > 0) {
    $stmtG = $conn->prepare(
        "SELECT cs.section_code, s.subject_name, u.full_name AS teacher_name,
                COUNT(g.id) AS total_enrolled,
                CASE WHEN COUNT(g.id) > 0 THEN ROUND(SUM(CASE WHEN g.final_score >= 5.0 THEN 1 ELSE 0 END) / COUNT(g.id) * 100, 1) ELSE NULL END AS pass_rate,
                ROUND(AVG(g.final_score), 2) AS avg_score
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE m.faculty_id = ? AND cs.semester_id = ?
         GROUP BY cs.id, cs.section_code, s.subject_name, teacher_name
         ORDER BY s.subject_name ASC"
    );
    $stmtG->bind_param('ii', $facultyId, $semesterId);
    $stmtG->execute();
    $rows = $stmtG->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtG->close();

    logAudit($conn, $userId, 'export', 'faculty', 'grades', 0, null, json_encode(['semester_id' => $semesterId, 'count' => count($rows)]), $ip);

    outputCSV(
        ['Ma lop', 'Mon hoc', 'Giang vien', 'Si so', 'Ti le dat (%)', 'Diem TB'],
        array_map(fn($r) => [
            $r['section_code'], $r['subject_name'], $r['teacher_name'] ?? '',
            $r['total_enrolled'], $r['pass_rate'] ?? '', $r['avg_score'] ?? ''
        ], $rows),
        'ket_qua_hoc_tap_' . date('Ymd') . '.csv'
    );
    exit();
}

if ($type === 'exams' && $semesterId > 0) {
    $stmtE = $conn->prepare(
        "SELECT s.subject_name, cs.section_code, fes.exam_date,
                fes.start_time AS exam_time_start, fes.end_time AS exam_time_end, fes.room,
                u.full_name AS teacher_name
         FROM final_exam_schedules fes
         JOIN course_sections cs ON fes.course_section_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users u ON t.user_id = u.id
         WHERE m.faculty_id = ? AND cs.semester_id = ?
         ORDER BY fes.exam_date ASC, fes.start_time ASC"
    );
    $stmtE->bind_param('ii', $facultyId, $semesterId);
    $stmtE->execute();
    $rows = $stmtE->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtE->close();

    logAudit($conn, $userId, 'export', 'faculty', 'final_exam_schedules', 0, null, json_encode(['semester_id' => $semesterId, 'count' => count($rows)]), $ip);

    outputCSV(
        ['Mon hoc', 'Ma lop', 'Ngay thi', 'Gio bat dau', 'Gio ket thuc', 'Phong thi', 'Giang vien'],
        array_map(fn($r) => [
            $r['subject_name'], $r['section_code'], $r['exam_date'],
            $r['exam_time_start'], $r['exam_time_end'], $r['room'], $r['teacher_name'] ?? ''
        ], $rows),
        'lich_thi_' . date('Ymd') . '.csv'
    );
    exit();
}

if ($type === 'reports') {
    // Tong hop thong ke
    $stmtT = $conn->prepare("SELECT COUNT(*) AS c FROM teachers WHERE faculty_id = ?");
    $stmtT->bind_param('i', $facultyId);
    $stmtT->execute();
    $totalTeachers = (int)($stmtT->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtT->close();

    $stmtSV = $conn->prepare("SELECT COUNT(*) AS c FROM students s JOIN classes cl ON s.class_id = cl.id JOIN majors m ON cl.major_id = m.id WHERE m.faculty_id = ? AND s.academic_status = 'dang hoc'");
    $stmtSV->bind_param('i', $facultyId);
    $stmtSV->execute();
    $totalStudents = (int)($stmtSV->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtSV->close();

    $stmtM = $conn->prepare("SELECT COUNT(*) AS c FROM majors WHERE faculty_id = ?");
    $stmtM->bind_param('i', $facultyId);
    $stmtM->execute();
    $totalMajors = (int)($stmtM->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtM->close();

    logAudit($conn, $userId, 'export', 'faculty', 'reports', 0, null, json_encode(['semester_id' => $semesterId]), $ip);

    $rows = [
        ['Tong giang vien', $totalTeachers],
        ['Tong sinh vien dang hoc', $totalStudents],
        ['Tong nganh dao tao', $totalMajors],
    ];

    outputCSV(['Chi tieu', 'Gia tri'], $rows, 'bao_cao_' . date('Ymd') . '.csv');
    exit();
}

// Invalid type
http_response_code(400);
die('Loai xuat khong hop le.');
