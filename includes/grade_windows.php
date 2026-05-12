<?php

function isGradeInputWindowOpen(?string $sectionEndDate, ?string $gradeDeadline): bool
{
    if (empty($sectionEndDate) || empty($gradeDeadline)) {
        return false;
    }

    $today = strtotime(date('Y-m-d'));
    $endDate = strtotime(date('Y-m-d', strtotime($sectionEndDate)));
    $deadline = strtotime(date('Y-m-d', strtotime($gradeDeadline)));

    return $today > $endDate && $today <= $deadline;
}

function gradeInputWindowMessage(array $row): string
{
    if (empty($row['section_end_date'])) {
        return 'Lop hoc phan chua co ngay ket thuc, nen chua mo nhap diem.';
    }

    if (strtotime(date('Y-m-d')) <= strtotime(date('Y-m-d', strtotime($row['section_end_date'])))) {
        return 'Lop hoc phan chua ket thuc. Bang nhap diem se hien sau ngay '
            . date('d/m/Y', strtotime($row['section_end_date'])) . ' khi Phong Dao tao mo han nop diem.';
    }

    if (empty($row['grade_submit_deadline'])) {
        return 'Phong Dao tao chua mo thoi gian nhap diem cho hoc ky nay.';
    }

    if (strtotime(date('Y-m-d')) > strtotime(date('Y-m-d', strtotime($row['grade_submit_deadline'])))) {
        return 'Da qua han nhap diem ngay ' . date('d/m/Y', strtotime($row['grade_submit_deadline']))
            . '. Vui long lien he Phong Dao tao neu can mo lai.';
    }

    return 'Ngoai thoi gian nhap diem do Phong Dao tao mo.';
}

function getTeacherOpenGradeSections(mysqli $conn, int $teacherId): array
{
    $stmt = $conn->prepare(
        "SELECT cs.id, cs.section_code, s.subject_name, sm.semester_name, sm.school_year,
                cs.end_date AS section_end_date, sm.grade_submit_deadline,
                COUNT(ss.id) AS total_students,
                SUM(CASE WHEN g.final_score IS NOT NULL THEN 1 ELSE 0 END) AS graded_count
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN semesters sm ON cs.semester_id = sm.id
         LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id AND ss.status != 'cancelled'
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE cs.teacher_id = ?
           AND cs.end_date IS NOT NULL
           AND sm.grade_submit_deadline IS NOT NULL
           AND CURDATE() > DATE(cs.end_date)
           AND CURDATE() <= DATE(sm.grade_submit_deadline)
         GROUP BY cs.id, cs.section_code, s.subject_name, sm.semester_name, sm.school_year,
                  cs.end_date, sm.grade_submit_deadline
         ORDER BY sm.grade_submit_deadline ASC, sm.school_year DESC, sm.semester_name, cs.section_code"
    );
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getGradeInputWindowForSection(mysqli $conn, int $sectionId, ?int $teacherId = null): ?array
{
    $sql =
        "SELECT cs.id, cs.teacher_id, cs.section_code, s.subject_name,
                sm.semester_name, sm.school_year,
                cs.end_date AS section_end_date, sm.grade_submit_deadline
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN semesters sm ON cs.semester_id = sm.id
         WHERE cs.id = ?";

    if ($teacherId !== null) {
        $sql .= " AND cs.teacher_id = ?";
    }

    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($teacherId !== null) {
        $stmt->bind_param('ii', $sectionId, $teacherId);
    } else {
        $stmt->bind_param('i', $sectionId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['is_grade_window_open'] = isGradeInputWindowOpen($row['section_end_date'] ?? null, $row['grade_submit_deadline'] ?? null);
    $row['grade_window_message'] = $row['is_grade_window_open'] ? '' : gradeInputWindowMessage($row);
    return $row;
}

function getGradeInputWindowForStudentSubject(mysqli $conn, int $studentSubjectId): ?array
{
    $stmt = $conn->prepare("SELECT course_section_id FROM student_subjects WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $studentSubjectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return getGradeInputWindowForSection($conn, (int)$row['course_section_id']);
}
