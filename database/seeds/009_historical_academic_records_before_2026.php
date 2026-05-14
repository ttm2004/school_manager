<?php
/**
 * Bo sung lich su hoc tap truoc nam 2026 cho sinh vien da nhap hoc truoc 2026.
 *
 * Du lieu duoc tao theo workflow:
 * - Hoc ky qua khu
 * - Khoa de xuat mo lop
 * - Phong Dao tao duyet lop hoc phan, phong hoc, thoi khoa bieu
 * - Sinh vien dang ky hoc phan
 * - Diem hoc phan
 * - Dot thu, hoa don va thanh toan hoc phi
 *
 * Chay:
 *   php database/seeds/009_historical_academic_records_before_2026.php
 */

require_once __DIR__ . '/../../config/app.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(
    (string)config('db.host', 'localhost'),
    (string)config('db.user', 'root'),
    (string)config('db.pass', ''),
    (string)config('db.name', 'edu_management'),
    (int)config('db.port', 3306)
);
$conn->set_charset('utf8mb4');
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$stats = [
    'semesters' => 0,
    'sections' => 0,
    'registrations' => 0,
    'grades' => 0,
    'tuition_periods' => 0,
    'tuition_invoices' => 0,
    'tuition_payments' => 0,
];

function one(mysqli $conn, string $sql, string $types = '', mixed ...$params): ?array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function value(mysqli $conn, string $sql, string $types = '', mixed ...$params): mixed
{
    $row = one($conn, $sql, $types, ...$params);
    if (!$row) {
        return null;
    }
    return reset($row);
}

function execStmt(mysqli $conn, string $sql, string $types = '', mixed ...$params): int
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return max(0, $stmt->affected_rows);
}

function ensureSemester(mysqli $conn, int $year, int $term, array &$stats): int
{
    $name = 'Học kỳ ' . $term;
    $schoolYear = $year . '-' . ($year + 1);
    $existing = value($conn, "SELECT id FROM semesters WHERE semester_name = ? AND school_year = ? LIMIT 1", 'ss', $name, $schoolYear);
    if ($existing) {
        return (int)$existing;
    }

    if ($term === 1) {
        $start = $year . '-09-01';
        $end = ($year + 1) . '-01-15';
        $registerStart = $year . '-08-15 08:00:00';
        $registerEnd = $year . '-08-28 23:59:59';
    } else {
        $start = ($year + 1) . '-02-10';
        $end = ($year + 1) . '-06-20';
        $registerStart = ($year + 1) . '-01-15 08:00:00';
        $registerEnd = ($year + 1) . '-01-30 23:59:59';
    }

    $proposalStart = date('Y-m-d H:i:s', strtotime($start . ' -75 days'));
    $proposalEnd = date('Y-m-d H:i:s', strtotime($start . ' -45 days'));
    $approvalStart = date('Y-m-d H:i:s', strtotime($start . ' -44 days'));
    $approvalEnd = date('Y-m-d H:i:s', strtotime($start . ' -21 days'));
    $gradeDeadline = date('Y-m-d', strtotime($end . ' +20 days'));
    $proposalDeadline = date('Y-m-d', strtotime($start . ' -45 days'));

    execStmt(
        $conn,
        "INSERT INTO semesters
            (semester_name, school_year, register_start, register_end, start_date, end_date,
             proposal_start, proposal_end, approval_start, approval_end, status, grade_submit_deadline, proposal_deadline)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'closed', ?, ?)",
        'ssssssssssss',
        $name,
        $schoolYear,
        $registerStart,
        $registerEnd,
        $start,
        $end,
        $proposalStart,
        $proposalEnd,
        $approvalStart,
        $approvalEnd,
        $gradeDeadline,
        $proposalDeadline
    );
    $stats['semesters']++;
    return (int)$conn->insert_id;
}

function ensureTuitionPeriod(mysqli $conn, int $semesterId, string $title, string $openDate, string $dueDate, array &$stats): int
{
    $existing = value($conn, "SELECT id FROM tuition_periods WHERE semester_id = ? LIMIT 1", 'i', $semesterId);
    if ($existing) {
        execStmt(
            $conn,
            "UPDATE tuition_periods
             SET title = COALESCE(NULLIF(title, ''), ?),
                 open_date = COALESCE(open_date, ?),
                 due_date = COALESCE(due_date, ?),
                 status = CASE WHEN status = 'draft' THEN 'closed' ELSE status END
             WHERE id = ?",
            'sssi',
            $title,
            $openDate,
            $dueDate,
            (int)$existing
        );
        return (int)$existing;
    }
    execStmt(
        $conn,
        "INSERT INTO tuition_periods (semester_id, title, open_date, due_date, status, note, created_by, published_at)
         VALUES (?, ?, ?, ?, 'closed', 'Dữ liệu lịch sử trước năm 2026', 1, CONCAT(?, ' 08:00:00'))",
        'issss',
        $semesterId,
        $title,
        $openDate,
        $dueDate,
        $openDate
    );
    $stats['tuition_periods']++;
    return (int)$conn->insert_id;
}

function letterGrade(float $score): string
{
    if ($score >= 9.0) return 'A+';
    if ($score >= 8.5) return 'A';
    if ($score >= 8.0) return 'B+';
    if ($score >= 7.0) return 'B';
    if ($score >= 6.5) return 'C+';
    if ($score >= 5.5) return 'C';
    if ($score >= 5.0) return 'D+';
    if ($score >= 4.0) return 'D';
    return 'F';
}

function deterministicScore(int $studentId, int $subjectId, int $semesterOrder): array
{
    $seed = ($studentId * 17 + $subjectId * 11 + $semesterOrder * 7) % 45;
    $base = 5.0 + ($seed / 10);
    $process = min(10.0, round($base + 0.2, 1));
    $midterm = min(10.0, round($base + (($studentId % 3) - 1) * 0.3, 1));
    $final = min(10.0, round($base + (($subjectId % 4) - 1) * 0.25, 1));
    $total = round($process * 0.2 + $midterm * 0.3 + $final * 0.5, 2);
    return [$process, $midterm, $final, $total, letterGrade($total)];
}

function roomForIndex(mysqli $conn, int $index): array
{
    static $rooms = null;
    if ($rooms === null) {
        $rooms = [];
        $rs = $conn->query("SELECT id, room_code FROM classrooms WHERE status = 'active' AND room_type <> 'online' ORDER BY capacity, room_code");
        while ($row = $rs->fetch_assoc()) {
            $rooms[] = ['id' => (int)$row['id'], 'code' => $row['room_code']];
        }
        if (!$rooms) {
            $rooms[] = ['id' => null, 'code' => 'A101'];
        }
    }
    return $rooms[$index % count($rooms)];
}

function daySessionsForIndex(int $index): string
{
    $items = ['2:sang,4:sang', '3:chieu,5:chieu', '2:chieu,6:chieu', '4:sang,6:sang', '5:sang,7:sang'];
    return $items[$index % count($items)];
}

function semesterCalendarFromOrder(int $enrollmentYear, int $semesterOrder): array
{
    $yearOffset = intdiv($semesterOrder - 1, 2);
    $term = (($semesterOrder - 1) % 2) + 1;
    $academicYear = $enrollmentYear + $yearOffset;
    return [$academicYear, $term];
}

function ensureHistoricalSection(mysqli $conn, array $class, array $subject, int $semesterId, int $semesterOrder, array &$stats): int
{
    $sectionCode = 'HIST-' . $subject['subject_code'] . '-' . $class['class_code'] . '-HK' . $semesterOrder;
    $existing = value($conn, "SELECT id FROM course_sections WHERE section_code = ? LIMIT 1", 's', $sectionCode);
    if ($existing) {
        return (int)$existing;
    }

    $facultyId = (int)value(
        $conn,
        "SELECT m.faculty_id FROM majors m WHERE m.id = ? LIMIT 1",
        'i',
        (int)$class['major_id']
    );
    $teacherId = (int)(value(
        $conn,
        "SELECT id FROM teachers WHERE faculty_id = ? ORDER BY id LIMIT 1",
        'i',
        $facultyId
    ) ?: value($conn, "SELECT id FROM teachers ORDER BY id LIMIT 1"));
    if ($teacherId <= 0) {
        throw new RuntimeException('Chưa có giảng viên để tạo lớp học phần lịch sử.');
    }

    $room = roomForIndex($conn, (int)$class['id'] + (int)$subject['id'] + $semesterOrder);
    $daySessions = daySessionsForIndex((int)$class['id'] + (int)$subject['id']);
    $startDate = (string)value($conn, "SELECT start_date FROM semesters WHERE id = ?", 'i', $semesterId);
    $endDate = (string)value($conn, "SELECT end_date FROM semesters WHERE id = ?", 'i', $semesterId);
    $startDateTime = $startDate . ' 00:00:00';
    $proposalAt = date('Y-m-d H:i:s', strtotime($startDate . ' -60 days'));
    $submittedAt = date('Y-m-d H:i:s', strtotime($startDate . ' -50 days'));
    $reviewedAt = date('Y-m-d H:i:s', strtotime($startDate . ' -35 days'));
    $expected = max(25, (int)value($conn, "SELECT COUNT(*) FROM students WHERE class_id = ?", 'i', (int)$class['id']));
    $tuitionFee = (int)$subject['credits'] * 420000;

    execStmt(
        $conn,
        "INSERT INTO course_sections
            (subject_id, teacher_id, semester_id, target_cohort_id, section_code, schedule_text,
             room, classroom_id, max_students, current_students, tuition_fee, status, note,
             start_date, end_date, sessions_per_week, study_days, session_type, day_sessions, class_id,
             proposed_teacher_id, proposal_status, proposal_note, proposed_by, proposed_at,
             proposal_reviewed_by, proposal_reviewed_at, open_proposal_note, open_proposed_by,
             open_proposed_at, open_submitted_at, open_submitted_by, open_reviewed_by,
             open_reviewed_at, expected_students, min_students, teaching_mode)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 60, 0, ?, 'closed', ?,
             ?, ?, 2, NULL, NULL, ?, ?,
             ?, 'approved', ?, 1, ?,
             1, ?, ?, 1,
             ?, ?, 1, 1,
             ?, ?, 20, 'offline')",
        'iiiisssiissssiisssssssi',
        (int)$subject['id'],
        $teacherId,
        $semesterId,
        (int)($class['cohort_id'] ?? 0) ?: null,
        $sectionCode,
        'Lịch sử học phần đã được Phòng Đào tạo duyệt',
        $room['code'],
        $room['id'],
        $tuitionFee,
        'Dữ liệu lịch sử trước năm 2026',
        $startDate,
        $endDate,
        $daySessions,
        (int)$class['id'],
        $teacherId,
        'Khoa đề xuất giảng viên theo chuyên môn và kế hoạch đào tạo.',
        $proposalAt,
        $reviewedAt,
        'Khoa đề xuất mở lớp theo CTĐT khóa ' . ($class['enrollment_year'] ?? ''),
        $proposalAt,
        $submittedAt,
        $reviewedAt,
        $expected
    );
    $stats['sections']++;
    return (int)$conn->insert_id;
}

function ensureStudentSubject(mysqli $conn, int $studentId, int $sectionId, string $registerDate, array &$stats): int
{
    $existing = value(
        $conn,
        "SELECT id FROM student_subjects WHERE student_id = ? AND course_section_id = ? LIMIT 1",
        'ii',
        $studentId,
        $sectionId
    );
    if ($existing) {
        return (int)$existing;
    }
    execStmt(
        $conn,
        "INSERT INTO student_subjects (student_id, course_section_id, register_date, status)
         VALUES (?, ?, ?, 'completed')",
        'iis',
        $studentId,
        $sectionId,
        $registerDate
    );
    $stats['registrations']++;
    execStmt($conn, "UPDATE course_sections SET current_students = current_students + 1 WHERE id = ?", 'i', $sectionId);
    return (int)value(
        $conn,
        "SELECT id FROM student_subjects WHERE student_id = ? AND course_section_id = ? ORDER BY id DESC LIMIT 1",
        'ii',
        $studentId,
        $sectionId
    );
}

function ensureGrade(mysqli $conn, int $studentSubjectId, int $studentId, int $subjectId, int $semesterOrder, array &$stats): void
{
    $existing = value($conn, "SELECT id FROM grades WHERE student_subject_id = ? LIMIT 1", 'i', $studentSubjectId);
    if ($existing) {
        return;
    }
    [$process, $midterm, $final, $total, $letter] = deterministicScore($studentId, $subjectId, $semesterOrder);
    execStmt(
        $conn,
        "INSERT INTO grades
            (student_subject_id, process_score, midterm_score, final_score, total_score, letter_grade, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        'iddddss',
        $studentSubjectId,
        $process,
        $midterm,
        $final,
        $total,
        $letter,
        $letter === 'F' ? 'Không đạt, cần học lại.' : 'Đạt.'
    );
    $stats['grades']++;
}

function ensureTuitionInvoice(mysqli $conn, int $studentId, int $semesterId, int $periodId, int $credits, int $unitPrice, string $dueDate, array &$stats): void
{
    $existing = value(
        $conn,
        "SELECT id FROM tuition_invoices
         WHERE student_id = ? AND (semester_id = ? OR period_id = ?)
         LIMIT 1",
        'iii',
        $studentId,
        $semesterId,
        $periodId
    );
    if ($existing || $credits <= 0) {
        if ($existing && $credits > 0) {
            $gross = $credits * $unitPrice;
            $discount = ($studentId % 13 === 0) ? (int)round($gross * 0.1) : 0;
            $net = $gross - $discount;
            execStmt(
                $conn,
                "UPDATE tuition_invoices
                 SET total_credits = GREATEST(total_credits, ?),
                     unit_price = CASE WHEN unit_price <= 0 THEN ? ELSE unit_price END,
                     gross_amount = GREATEST(gross_amount, ?),
                     discount = GREATEST(discount, ?),
                     net_amount = GREATEST(net_amount, ?),
                     due_date = COALESCE(due_date, ?),
                     note = COALESCE(note, 'Dữ liệu học phí lịch sử trước năm 2026')
                 WHERE id = ?",
                'iiiiisi',
                $credits,
                $unitPrice,
                $gross,
                $discount,
                $net,
                $dueDate,
                (int)$existing
            );
        }
        return;
    }
    $gross = $credits * $unitPrice;
    $discount = ($studentId % 13 === 0) ? (int)round($gross * 0.1) : 0;
    $net = $gross - $discount;
    $paid = ($studentId % 11 === 0) ? (int)round($net * 0.5) : $net;
    $status = $paid >= $net ? 'paid' : 'partial';

    execStmt(
        $conn,
        "INSERT INTO tuition_invoices
            (period_id, student_id, semester_id, total_credits, unit_price,
             gross_amount, discount, net_amount, paid_amount, due_date, status, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Dữ liệu học phí lịch sử trước năm 2026', 1)",
        'iiiiiiiisss',
        $periodId,
        $studentId,
        $semesterId,
        $credits,
        $unitPrice,
        $gross,
        $discount,
        $net,
        $paid,
        $dueDate,
        $status
    );
    $invoiceId = (int)$conn->insert_id;
    $stats['tuition_invoices']++;

    if ($paid > 0) {
        execStmt(
            $conn,
            "INSERT INTO tuition_payments (invoice_id, amount, method, reference, note, paid_by, paid_at)
             VALUES (?, ?, 'bank_transfer', ?, 'Thanh toán học phí lịch sử', 1, CONCAT(?, ' 09:00:00'))",
            'iiss',
            $invoiceId,
            $paid,
            'HIST' . $studentId . '-' . $semesterId,
            date('Y-m-d', strtotime($dueDate . ' -5 days'))
        );
        $stats['tuition_payments']++;
    }
}

$conn->begin_transaction();

try {
    $rsClasses = $conn->query(
        "SELECT cl.id, cl.major_id, cl.class_code, cl.class_name, cl.school_year, cl.enrollment_year, cl.cohort_id,
                m.tuition_per_credit
         FROM classes cl
         JOIN majors m ON m.id = cl.major_id
         WHERE cl.enrollment_year IS NOT NULL AND cl.enrollment_year < 2026
         ORDER BY cl.enrollment_year, cl.id"
    );

    while ($class = $rsClasses->fetch_assoc()) {
        $enrollmentYear = (int)$class['enrollment_year'];
        if ($enrollmentYear <= 0 || $enrollmentYear >= 2026) {
            continue;
        }

        $students = [];
        $stmtStudents = $conn->prepare("SELECT id FROM students WHERE class_id = ? ORDER BY id");
        $classId = (int)$class['id'];
        $stmtStudents->bind_param('i', $classId);
        $stmtStudents->execute();
        $rsStudents = $stmtStudents->get_result();
        while ($student = $rsStudents->fetch_assoc()) {
            $students[] = (int)$student['id'];
        }
        if (!$students) {
            continue;
        }

        $maxSemesterOrder = min(7, (2025 - $enrollmentYear) * 2 + 1);
        if ($maxSemesterOrder <= 0) {
            continue;
        }

        $stmtSubjects = $conn->prepare(
            "SELECT DISTINCT c.subject_id AS id, s.subject_code, s.subject_name, COALESCE(c.credits, s.credits, 3) AS credits,
                    COALESCE(NULLIF(c.suggested_semester, 0), s.semester_order, 1) AS suggested_semester
             FROM curriculum c
             JOIN subjects s ON s.id = c.subject_id
             WHERE c.major_id = ?
               AND c.deleted_at IS NULL
               AND COALESCE(NULLIF(c.suggested_semester, 0), s.semester_order, 1) BETWEEN 1 AND ?
               AND (c.program_id IS NULL OR c.program_id = (
                    SELECT tc.program_id FROM training_cohorts tc WHERE tc.id = ? LIMIT 1
               ))
             ORDER BY suggested_semester, s.subject_code"
        );
        $majorId = (int)$class['major_id'];
        $cohortId = (int)($class['cohort_id'] ?? 0);
        $stmtSubjects->bind_param('iii', $majorId, $maxSemesterOrder, $cohortId);
        $stmtSubjects->execute();
        $subjects = $stmtSubjects->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$subjects) {
            continue;
        }

        $creditsByStudentSemester = [];
        foreach ($subjects as $subject) {
            $semesterOrder = (int)$subject['suggested_semester'];
            [$academicYear, $term] = semesterCalendarFromOrder($enrollmentYear, $semesterOrder);
            if ($academicYear > 2025 || ($academicYear === 2025 && $term > 1)) {
                continue;
            }

            $semesterId = ensureSemester($conn, $academicYear, $term, $stats);
            $sectionId = ensureHistoricalSection($conn, $class, $subject, $semesterId, $semesterOrder, $stats);
            $registerDate = (string)value($conn, "SELECT register_start FROM semesters WHERE id = ?", 'i', $semesterId);

            foreach ($students as $studentId) {
                $studentSubjectId = ensureStudentSubject($conn, $studentId, $sectionId, $registerDate, $stats);
                ensureGrade($conn, $studentSubjectId, $studentId, (int)$subject['id'], $semesterOrder, $stats);
                $creditsByStudentSemester[$studentId][$semesterId] = ($creditsByStudentSemester[$studentId][$semesterId] ?? 0) + (int)$subject['credits'];
            }
        }

        foreach ($creditsByStudentSemester as $studentId => $semesterCredits) {
            foreach ($semesterCredits as $semesterId => $credits) {
                $sem = one($conn, "SELECT school_year, semester_name, start_date FROM semesters WHERE id = ?", 'i', (int)$semesterId);
                $openDate = date('Y-m-d', strtotime($sem['start_date'] . ' -15 days'));
                $dueDate = date('Y-m-d', strtotime($sem['start_date'] . ' +30 days'));
                $periodId = ensureTuitionPeriod(
                    $conn,
                    (int)$semesterId,
                    'Thu học phí ' . $sem['semester_name'] . ' năm học ' . $sem['school_year'],
                    $openDate,
                    $dueDate,
                    $stats
                );
                ensureTuitionInvoice(
                    $conn,
                    (int)$studentId,
                    (int)$semesterId,
                    $periodId,
                    (int)$credits,
                    (int)$class['tuition_per_credit'],
                    $dueDate,
                    $stats
                );
            }
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

echo "Hoàn tất bổ sung dữ liệu lịch sử trước năm 2026.\n";
foreach ($stats as $key => $value) {
    echo "- {$key}: {$value}\n";
}
