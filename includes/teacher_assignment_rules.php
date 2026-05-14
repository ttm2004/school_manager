<?php

function getSubjectFacultyId(mysqli $conn, int $subjectId): ?int
{
    if ($subjectId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT COALESCE(m.faculty_id, cm.faculty_id) AS faculty_id
         FROM subjects s
         LEFT JOIN majors m ON s.major_id = m.id
         LEFT JOIN curriculum cur ON cur.subject_id = s.id AND cur.deleted_at IS NULL
         LEFT JOIN majors cm ON cur.major_id = cm.id
         WHERE s.id = ?
         ORDER BY m.faculty_id IS NULL ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $subjectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row['faculty_id']) ? (int)$row['faculty_id'] : null;
}

function getSectionAssignmentContext(mysqli $conn, int $sectionId): ?array
{
    if ($sectionId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT cs.id, cs.subject_id, cs.semester_id,
                COALESCE(m.faculty_id, cm.faculty_id) AS faculty_id
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN majors m ON s.major_id = m.id
         LEFT JOIN curriculum cur ON cur.subject_id = s.id AND cur.deleted_at IS NULL
         LEFT JOIN majors cm ON cur.major_id = cm.id
         WHERE cs.id = ?
         ORDER BY m.faculty_id IS NULL ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function validateTeacherAssignment(mysqli $conn, int $teacherId, int $subjectId, int $semesterId): array
{
    if ($teacherId <= 0) {
        return ['ok' => true, 'message' => ''];
    }
    if ($subjectId <= 0 || $semesterId <= 0) {
        return ['ok' => false, 'message' => 'Dữ liệu môn học hoặc học kỳ không hợp lệ.'];
    }

    $subjectFacultyId = getSubjectFacultyId($conn, $subjectId);
    if (!$subjectFacultyId) {
        return ['ok' => false, 'message' => 'Môn học chưa được gán khoa/viện nên không thể phân công giảng viên.'];
    }

    $stmtTeacher = $conn->prepare(
        "SELECT t.id, t.faculty_id, u.status
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         WHERE t.id = ?
         LIMIT 1"
    );
    if (!$stmtTeacher) {
        return ['ok' => false, 'message' => 'Không kiểm tra được thông tin giảng viên.'];
    }
    $stmtTeacher->bind_param('i', $teacherId);
    $stmtTeacher->execute();
    $teacher = $stmtTeacher->get_result()->fetch_assoc();
    $stmtTeacher->close();

    if (!$teacher || (int)$teacher['status'] !== 1) {
        return ['ok' => false, 'message' => 'Giảng viên không tồn tại hoặc đã bị khóa tài khoản.'];
    }
    if ((int)$teacher['faculty_id'] !== $subjectFacultyId) {
        return ['ok' => false, 'message' => 'Chỉ được phân công giảng viên thuộc đúng khoa/viện của môn học.'];
    }

    $wishTable = $conn->query("SHOW TABLES LIKE 'teaching_wishes'");
    if ($wishTable && $wishTable->num_rows > 0) {
        $stmtWish = $conn->prepare(
            "SELECT id, status FROM teaching_wishes
             WHERE teacher_id = ?
               AND subject_id = ?
               AND semester_id = ?
               AND faculty_id = ?
               AND status IN ('faculty_approved', 'confirmed', 'faculty_rejected', 'dept_rejected', 'cancelled')
             LIMIT 1"
        );
        if (!$stmtWish) {
            return ['ok' => false, 'message' => 'Không kiểm tra được nguyện vọng giảng dạy của giảng viên.'];
        }
        $stmtWish->bind_param('iiii', $teacherId, $subjectId, $semesterId, $subjectFacultyId);
        $stmtWish->execute();
        $wish = $stmtWish->get_result()->fetch_assoc();
        $stmtWish->close();

        if ($wish && in_array((string)$wish['status'], ['faculty_rejected', 'dept_rejected', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'Nguyện vọng giảng dạy của giảng viên cho môn này đã bị từ chối hoặc đã hủy.'];
        }
    }

    return ['ok' => true, 'message' => ''];
}

function validateTeacherAssignmentForSection(mysqli $conn, int $teacherId, int $sectionId): array
{
    if ($teacherId <= 0) {
        return ['ok' => true, 'message' => ''];
    }

    $section = getSectionAssignmentContext($conn, $sectionId);
    if (!$section) {
        return ['ok' => false, 'message' => 'Lớp học phần không hợp lệ.'];
    }

    return validateTeacherAssignment($conn, $teacherId, (int)$section['subject_id'], (int)$section['semester_id']);
}
