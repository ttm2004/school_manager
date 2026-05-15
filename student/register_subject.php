<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../app/Services/StudentRegistrationService.php';
requireRole('student');

// Lấy thông tin sinh viên
$stmt = $conn->prepare(
    "SELECT s.*, u.full_name,
            cl.cohort_id AS class_cohort_id,
            tc.program_id AS class_program_id
     FROM students s
     JOIN users u ON s.user_id=u.id
     LEFT JOIN classes cl ON cl.id = s.class_id
     LEFT JOIN training_cohorts tc ON tc.id = cl.cohort_id
     WHERE s.user_id=?"
);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
$studentDataMode = ($student['data_mode'] ?? 'system') === 'test' ? 'test' : 'system';
requireNoTuitionLock((int)$student['id']);
$studentCohortForList = (int)($student['cohort_id'] ?: ($student['class_cohort_id'] ?? 0));
$studentProgramId = (int)($student['training_program_id'] ?: ($student['class_program_id'] ?? 0));
$hasCourseSectionClassId = academicPolicyColumnExists($conn, 'course_sections', 'class_id');
$classScopeSql = $hasCourseSectionClassId ? "AND (? = 1 OR cs.class_id IS NULL OR cs.class_id = ?)" : "";
$commonColCheck = $conn->query("SHOW COLUMNS FROM subjects LIKE 'is_common'");
if ($commonColCheck && $commonColCheck->num_rows == 0) {
    $conn->query("ALTER TABLE subjects ADD COLUMN is_common TINYINT(1) NOT NULL DEFAULT 0");
}

// Tự động thêm cột schedule_data nếu chưa có
$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}
$success = $error = '';

function studentRegistrationWindowOpenForSemester(mysqli $conn, int $semesterId): bool
{
    $stmt = $conn->prepare(
        "SELECT id FROM semesters
         WHERE id = ? AND status='open' AND register_start <= NOW() AND register_end >= NOW()
         LIMIT 1"
    );
    $stmt->bind_param('i', $semesterId);
    $stmt->execute();
    $open = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $open;
}

// Xử lý đăng ký / hủy
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.';
    }
    $action = $_POST['action'] ?? '';
    $section_id = intval($_POST['section_id'] ?? 0);

    if (empty($error) && $action === 'cancel') {
        $ssId = (int)($_POST['ss_id'] ?? 0);
        if (!$ssId) {
            $error = 'Không tìm thấy học phần cần hủy.';
        } else {
            $conn->begin_transaction();
            try {
                $getStmt = $conn->prepare(
                    "SELECT ss.course_section_id, cs.semester_id
                     FROM student_subjects ss
                     JOIN course_sections cs ON ss.course_section_id = cs.id
                     WHERE ss.id = ? AND ss.student_id = ? AND ss.status = 'registered'
                     LIMIT 1"
                );
                $getStmt->bind_param('ii', $ssId, $student['id']);
                $getStmt->execute();
                $ssRow = $getStmt->get_result()->fetch_assoc();
                $getStmt->close();

                if (!$ssRow) {
                    throw new RuntimeException('Không tìm thấy học phần đang đăng ký để hủy.');
                }
                if (!studentRegistrationWindowOpenForSemester($conn, (int)$ssRow['semester_id'])) {
                    throw new RuntimeException('Đã hết thời gian hủy đăng ký học phần.');
                }

                $updReg = $conn->prepare("UPDATE student_subjects SET status='cancelled' WHERE id=? AND student_id=? AND status = 'registered'");
                $updReg->bind_param('ii', $ssId, $student['id']);
                $updReg->execute();
                if ($updReg->affected_rows <= 0) {
                    throw new RuntimeException('Không thể hủy học phần này.');
                }
                $updReg->close();

                $sectionId = (int)$ssRow['course_section_id'];
                $updSection = $conn->prepare(
                    "UPDATE course_sections
                     SET current_students = GREATEST(0, current_students - 1),
                         status = CASE WHEN status = 'full' THEN 'open' ELSE status END
                     WHERE id = ?"
                );
                $updSection->bind_param('i', $sectionId);
                $updSection->execute();
                $updSection->close();

                syncStudentTuitionInvoiceFromRegistrations((int)$student['id'], (int)$ssRow['semester_id']);
                $conn->commit();
                $success = 'Hủy đăng ký thành công. Bạn có thể đăng ký lại trong thời gian đăng ký.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }

    if (empty($error) && $action === 'register' && $section_id) {
        $registrationPolicy = StudentRegistrationService::validateRegistration($conn, (int)$student['id'], $section_id);
        if (!$registrationPolicy['ok']) {
            $error = $registrationPolicy['message'];
        } else {
        // Kiểm tra đúng học kỳ của lớp học phần đang đăng ký.
        $semStmt = $conn->prepare(
            "SELECT sm.*
             FROM semesters sm
             JOIN course_sections cs ON cs.semester_id = sm.id
             WHERE cs.id = ?
               AND sm.status='open'
               AND sm.register_start <= NOW()
               AND sm.register_end >= NOW()
             LIMIT 1"
        );
        $semStmt->bind_param('i', $section_id);
        $semStmt->execute();
        $sem = $semStmt->get_result()->fetch_assoc();
        $semStmt->close();
        if (!$sem) {
            $error = 'Hiện tại không trong thời gian đăng ký học phần.';
        } else {
            // Chỉ khóa đăng ký khi hóa đơn đã quá hạn mà sinh viên chưa đóng đủ.
            $tuitionLock = getTuitionLockStatus((int)$student['id']);
            if (!empty($tuitionLock['locked'])) {
                $error = '⚠️ ' . htmlspecialchars($tuitionLock['message']) . ' <a href="/university/student/tuition.php" class="alert-link">Xem chi tiết học phí</a>';
            } else {
            // Kiểm tra trạng thái đăng ký cũ cùng lớp. Nếu đã hủy thì cho đăng ký lại.
            $chk = $conn->prepare("SELECT id, status FROM student_subjects WHERE student_id=? AND course_section_id=? LIMIT 1");
            $chk->bind_param('ii', $student['id'], $section_id);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();

            // Kiểm tra đã đăng ký môn học này trong cùng học kỳ chưa (dù khác lớp)
            $dupSubject = null;
            if (!$exists) {
                $dupChk = $conn->prepare("
                    SELECT s.subject_name, cs2.section_code
                    FROM student_subjects ss
                    JOIN course_sections cs2 ON ss.course_section_id = cs2.id
                    JOIN course_sections cs1 ON cs1.id = ?
                    JOIN subjects s ON cs2.subject_id = s.id
                    WHERE ss.student_id = ?
                      AND ss.status != 'cancelled'
                      AND cs2.subject_id = cs1.subject_id
                      AND cs2.semester_id = cs1.semester_id
                    LIMIT 1
                ");
                $dupChk->bind_param('ii', $section_id, $student['id']);
                $dupChk->execute();
                $dupSubject = $dupChk->get_result()->fetch_assoc();
                $dupChk->close();
            }

            if ($exists && $exists['status'] !== 'cancelled') {
                $error = 'Bạn đã đăng ký học phần này rồi.';
            } elseif ($dupSubject) {
                $error = 'Bạn đã đăng ký môn <strong>' . htmlspecialchars($dupSubject['subject_name']) . '</strong> ở lớp <strong>' . htmlspecialchars($dupSubject['section_code']) . '</strong> trong học kỳ này rồi.';
            } else {
                // Kiểm tra còn chỗ
                $secChk = $conn->prepare("SELECT cs.*, s.subject_name FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id WHERE cs.id=? AND cs.status!='closed' AND cs.current_students < cs.max_students");
                $secChk->bind_param('i', $section_id);
                $secChk->execute();
                $sec = $secChk->get_result()->fetch_assoc();
                $secChk->close();

                if (!$sec) {
                    $error = 'Lớp học phần đã đầy hoặc không còn mở đăng ký.';
                } else {
                    // ===== KIỂM TRA TRÙNG LỊCH =====
                    $conflictMsg = '';

                    // Lấy lịch của lớp muốn đăng ký — ưu tiên day_sessions mới
                    $newDayMap = []; // [day => session]
                    if (!empty($sec['day_sessions'])) {
                        foreach (explode(',', $sec['day_sessions']) as $p) {
                            $a = explode(':', trim($p));
                            if (count($a)==2) $newDayMap[(int)$a[0]] = $a[1];
                        }
                    } elseif (!empty($sec['schedule_data'])) {
                        $slots = json_decode($sec['schedule_data'], true) ?: [];
                        foreach ($slots as $sl) $newDayMap[(int)$sl['day']] = $sl['session'];
                    } elseif (!empty($sec['schedule_text'])) {
                        $slots = parseScheduleTextToSlots($sec['schedule_text']);
                        foreach ($slots as $sl) $newDayMap[(int)$sl['day']] = $sl['session'];
                    }

                    $semId = $conn->query("SELECT semester_id FROM course_sections WHERE id=".intval($section_id))->fetch_assoc()['semester_id'] ?? 0;

                    if ($semId && !empty($newDayMap)) {
                        $regStmt = $conn->prepare("
                            SELECT cs.day_sessions, cs.schedule_data, cs.schedule_text, s.subject_name, cs.section_code
                            FROM student_subjects ss
                            JOIN course_sections cs ON ss.course_section_id = cs.id
                            JOIN subjects s ON cs.subject_id = s.id
                            WHERE ss.student_id = ? AND ss.status IN ('registered','auto_enrolled') AND cs.semester_id = ?
                        ");
                        if ($regStmt) {
                            $regStmt->bind_param('ii', $student['id'], $semId);
                            $regStmt->execute();
                            $regResult = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $regStmt->close();

                            $dayNames2     = [2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'Chủ nhật'];
                            $sessionNames2 = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];

                            foreach ($regResult as $reg) {
                                $existMap = [];
                                if (!empty($reg['day_sessions'])) {
                                    foreach (explode(',', $reg['day_sessions']) as $p) {
                                        $a = explode(':', trim($p));
                                        if (count($a)==2) $existMap[(int)$a[0]] = $a[1];
                                    }
                                } elseif (!empty($reg['schedule_data'])) {
                                    $slots = json_decode($reg['schedule_data'], true) ?: [];
                                    foreach ($slots as $sl) $existMap[(int)$sl['day']] = $sl['session'];
                                } elseif (!empty($reg['schedule_text'])) {
                                    $slots = parseScheduleTextToSlots($reg['schedule_text']);
                                    foreach ($slots as $sl) $existMap[(int)$sl['day']] = $sl['session'];
                                }
                                foreach ($newDayMap as $d => $s) {
                                    if (isset($existMap[$d]) && $existMap[$d] === $s) {
                                        $conflictMsg .= "Trùng lịch <strong>"
                                            .($dayNames2[$d]??'N'.$d).' '.($sessionNames2[$s]??$s)
                                            ."</strong> với môn <strong>".htmlspecialchars($reg['subject_name'])."</strong>. ";
                                    }
                                }
                            }
                        }
                    }

                    if ($conflictMsg) {
                        $error = '⚠️ Không thể đăng ký! ' . $conflictMsg . '<a href="/university/student/timetable.php" class="alert-link ms-1">Xem thời khóa biểu</a>';
                    } else {
                        $conn->begin_transaction();
                        try {
                            $stmtLock = $conn->prepare(
                                "UPDATE course_sections
                                 SET current_students = current_students + 1,
                                     status = CASE WHEN current_students + 1 >= max_students THEN 'full' ELSE status END
                                 WHERE id = ?
                                   AND status IN ('open','full')
                                   AND current_students < max_students"
                            );
                            $stmtLock->bind_param('i', $section_id);
                            $stmtLock->execute();
                            $updated = $stmtLock->affected_rows;
                            $stmtLock->close();

                            if ($updated <= 0) {
                                throw new RuntimeException('Lớp học phần đã đầy hoặc không còn mở đăng ký.');
                            }

                            if ($exists && $exists['status'] === 'cancelled') {
                                $ins = $conn->prepare("UPDATE student_subjects SET status='registered', register_date=NOW(), data_mode=?, demo_batch_id=? WHERE id=? AND student_id=? AND status='cancelled'");
                                $demoBatchId = (string)($student['demo_batch_id'] ?? '');
                                $ins->bind_param('ssii', $studentDataMode, $demoBatchId, $exists['id'], $student['id']);
                                if (!$ins->execute() || $ins->affected_rows <= 0) {
                                    throw new RuntimeException('Lỗi đăng ký lại: ' . $conn->error);
                                }
                            } else {
                                $ins = $conn->prepare("INSERT INTO student_subjects (student_id, course_section_id, status, data_mode, demo_batch_id) VALUES (?,?,'registered',?,?)");
                                $demoBatchId = (string)($student['demo_batch_id'] ?? '');
                                $ins->bind_param('iiss', $student['id'], $section_id, $studentDataMode, $demoBatchId);
                                if (!$ins->execute()) {
                                    throw new RuntimeException('Lỗi đăng ký: ' . $conn->error);
                                }
                            }
                            $ins->close();

                            syncStudentTuitionInvoiceFromRegistrations((int)$student['id'], (int)$semId);
                            $conn->commit();
                            $success = '✅ Đăng ký học phần <strong>' . htmlspecialchars($sec['subject_name']) . '</strong> thành công! <a href="/university/student/timetable.php" class="alert-link">Xem thời khóa biểu</a>';
                        } catch (Throwable $e) {
                            $conn->rollback();
                            $error = $e->getMessage();
                        }
                    }
                }
            }
            } // end hasTuitionDebt check
        }
        }
    }

    // ── PRG: redirect sau POST để tránh F5 gửi lại form ──
    if (!empty($success) || !empty($error)) {
        $_SESSION['_flash'] = [
            'type'    => !empty($success) ? 'success' : 'danger',
            'message' => !empty($success) ? $success : $error,
        ];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($qs ? '?' . $qs : ''));
        exit();
    }
}

// Hàm parse schedule_text → slots JSON (dùng cho cả hiển thị và kiểm tra trùng lịch)
function parseScheduleTextToSlots(string $text): array {
    $slots = [];
    $dayMap = [
        'thứ 2'=>2,'thu 2'=>2,'t2'=>2,
        'thứ 3'=>3,'thu 3'=>3,'t3'=>3,
        'thứ 4'=>4,'thu 4'=>4,'t4'=>4,
        'thứ 5'=>5,'thu 5'=>5,'t5'=>5,
        'thứ 6'=>6,'thu 6'=>6,'t6'=>6,
        'thứ 7'=>7,'thu 7'=>7,'t7'=>7,
        'chủ nhật'=>8,'chu nhat'=>8,'cn'=>8,
    ];
    $parts = preg_split('/[;]+/', $text);
    foreach ($parts as $part) {
        $part = trim(strtolower($part));
        $dayNum = null;
        foreach ($dayMap as $key => $num) {
            if (str_contains($part, $key)) { $dayNum = $num; break; }
        }
        if (!$dayNum) continue;
        preg_match('/tiết\s*(\d+)/ui', $part, $m);
        $periodStart = isset($m[1]) ? intval($m[1]) : 1;
        if ($periodStart <= 5)      $session = 'sang';
        elseif ($periodStart <= 10) $session = 'chieu';
        else                        $session = 'toi';
        $slots[] = ['day'=>$dayNum,'session'=>$session,'period_start'=>$periodStart];
    }
    return $slots;
}

$semesterModeSql = $studentDataMode === 'test'
    ? "AND (sm.data_mode = 'test' OR LOWER(sm.semester_name) LIKE '%test%')"
    : "AND COALESCE(sm.data_mode, 'system') = 'system' AND LOWER(sm.semester_name) NOT LIKE '%test%'";

// Lấy học kỳ đang mở đăng ký (ưu tiên học kỳ có lớp học phần)
$semester = $conn->query("
    SELECT sm.* FROM semesters sm
    WHERE sm.status = 'open'
      AND sm.register_start <= NOW()
      AND sm.register_end >= NOW()
      $semesterModeSql
      AND EXISTS (SELECT 1 FROM course_sections cs WHERE cs.semester_id = sm.id AND cs.status != 'closed')
    ORDER BY sm.id DESC LIMIT 1
")->fetch_assoc();

// Nếu không có học kỳ nào đang mở đăng ký có lớp HP, lấy học kỳ mở bất kỳ
if (!$semester) {
    $semesterNameSql = $studentDataMode === 'test'
        ? "AND (data_mode = 'test' OR LOWER(semester_name) LIKE '%test%')"
        : "AND COALESCE(data_mode, 'system') = 'system' AND LOWER(semester_name) NOT LIKE '%test%'";
    $semester = $conn->query("SELECT * FROM semesters WHERE status='open' $semesterNameSql ORDER BY id DESC LIMIT 1")->fetch_assoc();
}

$now = time();
$regOpen = false;
$regMsg  = '';
$autoRegistrationLocked = false;
$semesterOrderForList = null;

if ($semester) {
    $rs = $semester['register_start'] ? strtotime($semester['register_start']) : 0;
    $re = $semester['register_end']   ? strtotime($semester['register_end'])   : 0;
    if ($rs && $re && $now >= $rs && $now <= $re) {
        $regOpen = true;
    } elseif ($rs && $now < $rs) {
        $regMsg = 'Thời gian đăng ký chưa bắt đầu. Mở lúc: <strong>' . date('d/m/Y H:i', $rs) . '</strong>';
    } elseif ($re && $now > $re) {
        $regMsg = 'Thời gian đăng ký đã kết thúc lúc: <strong>' . date('d/m/Y H:i', $re) . '</strong>';
    } else {
        $regMsg = 'Chưa thiết lập thời gian đăng ký. Vui lòng liên hệ phòng đào tạo.';
    }
    $semesterOrder = !empty($student['enrollment_year'])
        ? academicPolicyCurriculumSemesterOrder((int)$student['enrollment_year'], $semester)
        : null;
    $semesterOrderForList = $semesterOrder;
    $autoRegistrationLocked = $semesterOrder === 1;
}

// Danh sách lớp học phần có thể đăng ký
$sections = [];
if ($semester) {
    $semesterOrderForListParam = (int)($semesterOrderForList ?? 0);
    $allowFutureSubjectsForList = $studentDataMode === 'test' ? 1 : 0;
    $relaxSectionScopeForList = $studentDataMode === 'test' ? 1 : 0;
    $stmt = $conn->prepare("
        SELECT cs.*, s.subject_code, s.subject_name, s.credits, s.subject_type, s.is_common,
               (
                   SELECT ss_state.status
                   FROM student_subjects ss_state
                   WHERE ss_state.student_id = ? AND ss_state.course_section_id = cs.id
                   ORDER BY ss_state.id DESC
                   LIMIT 1
               ) AS my_status,
               (
                   SELECT ss_state.id
                   FROM student_subjects ss_state
                   WHERE ss_state.student_id = ? AND ss_state.course_section_id = cs.id
                   ORDER BY ss_state.id DESC
                   LIMIT 1
               ) AS my_ss_id,
               EXISTS (
                   SELECT 1 FROM student_subjects ss_pass
                   JOIN course_sections cs_pass ON cs_pass.id = ss_pass.course_section_id
                   JOIN grades g_pass ON g_pass.student_subject_id = ss_pass.id
                   WHERE ss_pass.student_id = ? AND cs_pass.subject_id = cs.subject_id AND g_pass.final_score >= 5
                   LIMIT 1
               ) AS has_passed,
               EXISTS (
                   SELECT 1 FROM student_subjects ss_fail
                   JOIN course_sections cs_fail ON cs_fail.id = ss_fail.course_section_id
                   JOIN grades g_fail ON g_fail.student_subject_id = ss_fail.id
                   WHERE ss_fail.student_id = ? AND cs_fail.subject_id = cs.subject_id AND g_fail.final_score < 5
                   LIMIT 1
               ) AS has_failed,
               COALESCE(u.full_name, 'Chưa phân công') as teacher_name, COALESCE(t.degree, '') AS degree,
               cs.start_date, cs.end_date,
               tc.cohort_code, tc.enrollment_year, tc.duration_years,
               tm.major_name AS target_major_name
        FROM course_sections cs
        JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN teachers t ON cs.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN training_cohorts tc ON cs.target_cohort_id = tc.id
        LEFT JOIN majors tm ON tc.major_id = tm.id
        WHERE cs.semester_id = ?
          AND COALESCE(cs.data_mode, 'system') = ?
          AND cs.status IN ('open','full')
          AND (? = 1 OR cs.target_cohort_id IS NULL OR cs.target_cohort_id = ?)
          $classScopeSql
          AND EXISTS (
              SELECT 1
              FROM curriculum cur
              WHERE cur.subject_id = cs.subject_id
                AND cur.major_id = (SELECT cl.major_id FROM classes cl WHERE cl.id = ? LIMIT 1)
                AND (cur.program_id IS NULL OR cur.program_id = ? OR ? = 0)
                AND (? = 1 OR ? = 0 OR cur.suggested_semester <= ? OR COALESCE(cur.allow_off_semester, 0) = 1)
                AND cur.deleted_at IS NULL
          )
        ORDER BY s.subject_name, cs.section_code, cs.id
    ");
    if ($hasCourseSectionClassId) {
        $stmt->bind_param('iiiiisiiiiiiiiii', $student['id'], $student['id'], $student['id'], $student['id'], $semester['id'], $studentDataMode, $relaxSectionScopeForList, $studentCohortForList, $relaxSectionScopeForList, $student['class_id'], $student['class_id'], $studentProgramId, $studentProgramId, $allowFutureSubjectsForList, $semesterOrderForListParam, $semesterOrderForListParam);
    } else {
        $stmt->bind_param('iiiiisiiiiiiii', $student['id'], $student['id'], $student['id'], $student['id'], $semester['id'], $studentDataMode, $relaxSectionScopeForList, $studentCohortForList, $student['class_id'], $studentProgramId, $studentProgramId, $allowFutureSubjectsForList, $semesterOrderForListParam, $semesterOrderForListParam);
    }
    $stmt->execute();
    $sections = $stmt->get_result();
    $stmt->close();
}

// Nếu học kỳ đang mở đăng ký nhưng không có lớp nào → thử lấy học kỳ có lớp học phần
if ($semester && (!$sections || $sections->num_rows == 0)) {
    $altSem = $conn->query("
        SELECT DISTINCT sm.* FROM semesters sm
        JOIN course_sections cs ON cs.semester_id = sm.id
        WHERE sm.status = 'open'
          AND COALESCE(sm.data_mode, 'system') = '" . $conn->real_escape_string($studentDataMode) . "'
        ORDER BY sm.id DESC LIMIT 1
    ")->fetch_assoc();
    if ($altSem && $altSem['id'] != $semester['id']) {
        $semester = $altSem;
        $rs = $semester['register_start'] ? strtotime($semester['register_start']) : 0;
        $re = $semester['register_end']   ? strtotime($semester['register_end'])   : 0;
        $regOpen = $rs && $re && $now >= $rs && $now <= $re;
        $semesterOrderForList = !empty($student['enrollment_year'])
            ? academicPolicyCurriculumSemesterOrder((int)$student['enrollment_year'], $semester)
            : null;
        $semesterOrderForListParam = (int)($semesterOrderForList ?? 0);
        $allowFutureSubjectsForList = $studentDataMode === 'test' ? 1 : 0;
        $relaxSectionScopeForList = $studentDataMode === 'test' ? 1 : 0;
        $stmt = $conn->prepare("
            SELECT cs.*, s.subject_code, s.subject_name, s.credits, s.subject_type, s.is_common,
                   (
                       SELECT ss_state.status
                       FROM student_subjects ss_state
                       WHERE ss_state.student_id = ? AND ss_state.course_section_id = cs.id
                       ORDER BY ss_state.id DESC
                       LIMIT 1
                   ) AS my_status,
                   (
                       SELECT ss_state.id
                       FROM student_subjects ss_state
                       WHERE ss_state.student_id = ? AND ss_state.course_section_id = cs.id
                       ORDER BY ss_state.id DESC
                       LIMIT 1
                   ) AS my_ss_id,
                   EXISTS (
                       SELECT 1 FROM student_subjects ss_pass
                       JOIN course_sections cs_pass ON cs_pass.id = ss_pass.course_section_id
                       JOIN grades g_pass ON g_pass.student_subject_id = ss_pass.id
                       WHERE ss_pass.student_id = ? AND cs_pass.subject_id = cs.subject_id AND g_pass.final_score >= 5
                       LIMIT 1
                   ) AS has_passed,
                   EXISTS (
                       SELECT 1 FROM student_subjects ss_fail
                       JOIN course_sections cs_fail ON cs_fail.id = ss_fail.course_section_id
                       JOIN grades g_fail ON g_fail.student_subject_id = ss_fail.id
                       WHERE ss_fail.student_id = ? AND cs_fail.subject_id = cs.subject_id AND g_fail.final_score < 5
                       LIMIT 1
                   ) AS has_failed,
                   COALESCE(u.full_name, 'Chưa phân công') as teacher_name, COALESCE(t.degree, '') AS degree,
                   cs.start_date, cs.end_date,
                   tc.cohort_code, tc.enrollment_year, tc.duration_years,
                   tm.major_name AS target_major_name
            FROM course_sections cs
            JOIN subjects s ON cs.subject_id = s.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN training_cohorts tc ON cs.target_cohort_id = tc.id
            LEFT JOIN majors tm ON tc.major_id = tm.id
            WHERE cs.semester_id = ?
              AND COALESCE(cs.data_mode, 'system') = ?
              AND cs.status IN ('open','full')
              AND (? = 1 OR cs.target_cohort_id IS NULL OR cs.target_cohort_id = ?)
              $classScopeSql
              AND EXISTS (
                  SELECT 1
                  FROM curriculum cur
                  WHERE cur.subject_id = cs.subject_id
                    AND cur.major_id = (SELECT cl.major_id FROM classes cl WHERE cl.id = ? LIMIT 1)
                    AND (cur.program_id IS NULL OR cur.program_id = ? OR ? = 0)
                    AND (? = 1 OR ? = 0 OR cur.suggested_semester <= ? OR COALESCE(cur.allow_off_semester, 0) = 1)
                    AND cur.deleted_at IS NULL
              )
            ORDER BY s.subject_name, cs.section_code, cs.id
        ");
        if ($hasCourseSectionClassId) {
            $stmt->bind_param('iiiiisiiiiiiiiii', $student['id'], $student['id'], $student['id'], $student['id'], $semester['id'], $studentDataMode, $relaxSectionScopeForList, $studentCohortForList, $relaxSectionScopeForList, $student['class_id'], $student['class_id'], $studentProgramId, $studentProgramId, $allowFutureSubjectsForList, $semesterOrderForListParam, $semesterOrderForListParam);
        } else {
            $stmt->bind_param('iiiiisiiiiiiii', $student['id'], $student['id'], $student['id'], $student['id'], $semester['id'], $studentDataMode, $relaxSectionScopeForList, $studentCohortForList, $student['class_id'], $studentProgramId, $studentProgramId, $allowFutureSubjectsForList, $semesterOrderForListParam, $semesterOrderForListParam);
        }
        $stmt->execute();
        $sections = $stmt->get_result();
        $stmt->close();
    }
}

$availableSections = $sections instanceof mysqli_result ? $sections->fetch_all(MYSQLI_ASSOC) : [];
$subjectsWithOwnClassSection = [];
$subjectsWithOwnCohortSection = [];
foreach ($availableSections as $sectionForScope) {
    if (!empty($sectionForScope['class_id']) && (int)$sectionForScope['class_id'] === (int)$student['class_id']) {
        $subjectsWithOwnClassSection[(int)$sectionForScope['subject_id']] = true;
    }
    if (!empty($sectionForScope['target_cohort_id']) && (int)$sectionForScope['target_cohort_id'] === $studentCohortForList) {
        $subjectsWithOwnCohortSection[(int)$sectionForScope['subject_id']] = true;
    }
}
$registeredSubjects = [];
$registeredCredits = 0;
$registeredFee = 0;
if ($semester) {
    $stmtRegList = $conn->prepare("
        SELECT ss.id AS ss_id, ss.status AS reg_status, ss.register_date,
               cs.id AS section_id, cs.section_code, cs.day_sessions, cs.schedule_data, cs.schedule_text,
               cs.room, cs.tuition_fee, cs.start_date, cs.end_date,
               s.subject_code, s.subject_name, s.credits, s.subject_type, s.is_common,
               COALESCE(u.full_name, 'Chưa phân công') AS teacher_name, COALESCE(t.degree, '') AS degree
        FROM student_subjects ss
        JOIN course_sections cs ON ss.course_section_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN teachers t ON cs.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE ss.student_id = ?
          AND ss.status IN ('registered','auto_enrolled')
          AND cs.semester_id = ?
        ORDER BY s.subject_name, cs.section_code
    ");
    $stmtRegList->bind_param('ii', $student['id'], $semester['id']);
    $stmtRegList->execute();
    $registeredSubjects = $stmtRegList->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRegList->close();
    foreach ($registeredSubjects as $regSub) {
        $registeredCredits += (int)$regSub['credits'];
        $registeredFee += (float)$regSub['tuition_fee'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Đăng ký học phần - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy">Đăng ký học phần</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">
            <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <?php if ($semester): ?>
            <div class="alert alert-<?php echo $regOpen ? 'success' : 'warning'; ?> d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-<?php echo $regOpen ? 'unlock-fill' : 'lock-fill'; ?> fs-5"></i>
                <div>
                    <strong><?php echo htmlspecialchars($semester['semester_name'] . ' ' . $semester['school_year']); ?></strong>
                    <?php if ($regOpen): ?>
                    &bull; <span class="fw-bold text-success">Đang mở đăng ký</span>
                    &bull; Hạn đăng ký: <strong><?php echo date('d/m/Y H:i', strtotime($semester['register_end'])); ?></strong>
                    <?php if ($autoRegistrationLocked): ?>
                    &bull; <span class="fw-bold text-primary">HK1 năm nhất được đăng ký tự động</span>
                    <?php endif; ?>
                    <?php else: ?>
                    &bull; <span class="fw-bold text-danger">Chưa mở đăng ký</span>
                    <?php if ($regMsg): ?>&bull; <?php echo $regMsg; ?><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Hiện tại chưa có học kỳ nào đang mở.</div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span><i class="bi bi-journal-plus me-2"></i>Danh sách học phần có thể đăng ký</span>
                    <div class="d-flex flex-wrap gap-2">
                        <input type="search" id="courseSearch" class="form-control form-control-sm" style="width:220px" placeholder="Tìm mã môn, tên môn, nhóm...">
                        <select id="courseFilter" class="form-select form-select-sm" style="width:190px">
                            <option value="own_class" selected>Lớp của tôi</option>
                            <option value="all">Tất cả môn mở</option>
                            <option value="registered">Đã đăng ký</option>
                            <option value="cohort">Mở đúng khóa/lớp</option>
                            <option value="ctdt">Chưa học trong CTĐT</option>
                            <option value="failed">Đã rớt - học lại</option>
                            <option value="available">Còn chỗ</option>
                            <option value="conflict">Trùng lịch</option>
                        </select>
                        <select id="groupFilter" class="form-select form-select-sm" style="width:150px">
                            <option value="all">Tất cả nhóm/tổ</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Mã HP</th>
                                    <th>Mã môn</th>
                                    <th>Tên môn học</th>
                                    <th>Dành cho</th>
                                    <th>TC</th>
                                    <th>Giảng viên</th>
                                    <th>Lịch học (6 buổi × 5 tiết)</th>
                                    <th>Phòng</th>
                                    <th>Sĩ số</th>
                                    <th>Học phí</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $dayNames = [2=>'T2',3=>'T3',4=>'T4',5=>'T5',6=>'T6',7=>'T7',8=>'CN'];
                                $sessionColors = ['sang'=>'#f57c00','chieu'=>'#1976d2','toi'=>'#7b1fa2'];
                                $sessionLabels = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];
                                $sessionTimes  = ['sang'=>'7:00–11:30','chieu'=>'12:30–17:00','toi'=>'17:30–22:00'];

                                // Helper: parse day_sessions "2:sang,4:chieu" → [day=>session]
                                function parseDaySessionsReg(string $ds): array {
                                    $r = [];
                                    foreach (academicPolicyScheduleTokens($ds) as $p) {
                                        $a = explode(':', trim($p), 2);
                                        if (count($a)==2 && $a[0] && $a[1]) $r[(int)$a[0]] = $a[1];
                                    }
                                    return $r;
                                }

                                // Lấy lịch các môn đã đăng ký để kiểm tra trùng
                                $mySlots = []; // [day][session] = true
                                if ($semester) {
                                    $myReg = $conn->prepare("
                                        SELECT cs.day_sessions, cs.schedule_data, cs.schedule_text
                                        FROM student_subjects ss
                                        JOIN course_sections cs ON ss.course_section_id=cs.id
                                        WHERE ss.student_id=? AND ss.status IN ('registered','auto_enrolled') AND cs.semester_id=?
                                    ");
                                    if ($myReg) {
                                        $myReg->bind_param('ii', $student['id'], $semester['id']);
                                        $myReg->execute();
                                        $myRegResult = $myReg->get_result()->fetch_all(MYSQLI_ASSOC);
                                        $myReg->close();
                                        foreach ($myRegResult as $r) {
                                            // Ưu tiên day_sessions mới
                                            if (!empty($r['day_sessions'])) {
                                                $dsMap = parseDaySessionsReg($r['day_sessions']);
                                                foreach ($dsMap as $d => $s) $mySlots[$d][$s] = true;
                                            } elseif (!empty($r['schedule_data'])) {
                                                $slots = json_decode($r['schedule_data'], true) ?: [];
                                                foreach ($slots as $sl) $mySlots[$sl['day']][$sl['session']] = true;
                                            } elseif (!empty($r['schedule_text'])) {
                                                $slots = parseScheduleTextToSlots($r['schedule_text']);
                                                foreach ($slots as $sl) $mySlots[$sl['day']][$sl['session']] = true;
                                            }
                                        }
                                    }
                                }

                                if (!empty($availableSections)): foreach ($availableSections as $sec):
                                $isFull = $sec['current_students'] >= $sec['max_students'];
                                $isCohort = !empty($sec['target_cohort_id']);
                                $hasOwnClassForSubject = !empty($subjectsWithOwnClassSection[(int)$sec['subject_id']]);
                                $hasOwnCohortForSubject = !empty($subjectsWithOwnCohortSection[(int)$sec['subject_id']]);
                                $isExactClass = !empty($sec['class_id']) && (int)$sec['class_id'] === (int)$student['class_id'];
                                $isOwnCohort = !$hasOwnClassForSubject
                                    && !empty($sec['target_cohort_id'])
                                    && (int)$sec['target_cohort_id'] === $studentCohortForList;
                                $isGlobalFallback = !$hasOwnClassForSubject
                                    && !$hasOwnCohortForSubject
                                    && empty($sec['class_id'])
                                    && empty($sec['target_cohort_id']);
                                $isOwnClass = $isExactClass || $isOwnCohort || $isGlobalFallback;
                                $isFailed = !empty($sec['has_failed']);
                                $isPassed = !empty($sec['has_passed']);
                                $isRegistered = ($sec['my_status'] ?? '') === 'registered';

                                // Lấy lịch của lớp này — ưu tiên day_sessions mới
                                $secDayMap = []; // [day => session]
                                if (!empty($sec['day_sessions'])) {
                                    $secDayMap = parseDaySessionsReg($sec['day_sessions']);
                                } elseif (!empty($sec['schedule_data'])) {
                                    $slots = json_decode($sec['schedule_data'], true) ?: [];
                                    foreach ($slots as $sl) $secDayMap[(int)$sl['day']] = $sl['session'];
                                } elseif (!empty($sec['schedule_text'])) {
                                    $slots = parseScheduleTextToSlots($sec['schedule_text']);
                                    foreach ($slots as $sl) $secDayMap[(int)$sl['day']] = $sl['session'];
                                }

                                // Kiểm tra trùng lịch
                                $hasConflict = false;
                                $conflictDetails = [];
                                foreach ($secDayMap as $d => $s) {
                                    if (!empty($mySlots[$d][$s])) {
                                        $hasConflict = true;
                                        $conflictDetails[] = ($dayNames[$d] ?? 'N'.$d).' '.($sessionLabels[$s] ?? $s);
                                    }
                                }
                                ?>
                                <tr class="course-row <?php echo $isRegistered ? 'table-success' : ($isFull ? 'table-secondary' : ($hasConflict ? 'table-warning' : '')); ?>"
                                    data-search="<?php echo htmlspecialchars(mb_strtolower(($sec['subject_code'] ?? '') . ' ' . $sec['subject_name'] . ' ' . $sec['section_code'] . ' ' . ($sec['cohort_code'] ?? '') . ' ' . ($sec['target_major_name'] ?? ''), 'UTF-8')); ?>"
                                    data-group="<?php echo htmlspecialchars($sec['section_code']); ?>"
                                    data-own-class="<?php echo $isOwnClass ? '1' : '0'; ?>"
                                    data-cohort="<?php echo $isCohort ? '1' : '0'; ?>"
                                    data-failed="<?php echo $isFailed ? '1' : '0'; ?>"
                                    data-passed="<?php echo $isPassed ? '1' : '0'; ?>"
                                    data-registered="<?php echo $isRegistered ? '1' : '0'; ?>"
                                    data-full="<?php echo $isFull ? '1' : '0'; ?>"
                                    data-conflict="<?php echo $hasConflict ? '1' : '0'; ?>">
                                    <td class="fw-bold text-navy small"><?php echo htmlspecialchars($sec['section_code']); ?></td>
                                    <td><code><?php echo htmlspecialchars($sec['subject_code']); ?></code></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($sec['subject_name']); ?></div>
                                        <span class="badge bg-<?php echo $sec['subject_type']=='Bắt buộc'?'danger':'info'; ?> small"><?php echo $sec['subject_type']; ?></span>
                                        <?php if (!empty($sec['is_common'])): ?><span class="badge bg-primary small">Môn chung</span><?php endif; ?>
                                        <?php if ($isFailed): ?><span class="badge bg-warning text-dark small">Học lại</span><?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($sec['cohort_code'])): ?>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($sec['cohort_code']); ?></div>
                                        <div class="text-muted"><?php echo htmlspecialchars($sec['target_major_name'] ?? ''); ?></div>
                                        <?php else: ?>
                                        <span class="text-muted">Theo CTĐT ngành của bạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-navy"><?php echo $sec['credits']; ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars(trim(($sec['degree'] ? $sec['degree'] . '. ' : '') . $sec['teacher_name'])); ?></td>
                                    <td class="small">
                                        <?php if (!empty($secDayMap)): ?>
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <?php foreach ($secDayMap as $d => $s):
                                                $isConflict = !empty($mySlots[$d][$s]);
                                                $bgColor = $isConflict ? '#dc3545' : ($sessionColors[$s] ?? '#666');
                                            ?>
                                            <span class="badge"
                                                  style="background:<?php echo $bgColor; ?>; font-size:0.75rem;"
                                                  title="<?php echo $isConflict ? 'Trùng lịch!' : ($sessionTimes[$s] ?? ''); ?>">
                                                <?php echo ($dayNames[$d] ?? 'N'.$d); ?>
                                                <?php echo ($sessionLabels[$s] ?? $s); ?>
                                                <?php if ($isConflict): ?>⚠<?php endif; ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.7rem">
                                            <?php echo count($secDayMap); ?> buổi/tuần × 5 tiết
                                        </div>
                                        <?php
                                        $sdStart = !empty($sec['start_date']) ? date('d/m/Y', strtotime($sec['start_date'])) : null;
                                        $sdEnd   = !empty($sec['end_date'])   ? date('d/m/Y', strtotime($sec['end_date']))   : null;
                                        if ($sdStart || $sdEnd): ?>
                                        <div class="text-muted" style="font-size:0.7rem; margin-top:2px;">
                                            <i class="bi bi-calendar-range"></i>
                                            <?php echo $sdStart ?? '--'; ?> → <?php echo $sdEnd ?? '--'; ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted small fst-italic">Chưa có lịch</span>
                                        <?php endif; ?>
                                        <?php if ($hasConflict): ?>
                                        <div class="text-danger fw-bold" style="font-size:0.72rem">
                                            ⚠ Trùng: <?php echo implode(', ', $conflictDetails); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($sec['room']); ?></td>
                                    <td class="text-center">
                                        <span class="<?php echo $isFull?'text-danger fw-bold':'text-success'; ?>">
                                            <?php echo $sec['current_students']; ?>/<?php echo $sec['max_students']; ?>
                                        </span>
                                    </td>
                                    <td class="small text-success fw-bold"><?php echo number_format($sec['tuition_fee'],0,',','.'); ?>đ</td>
                                    <td>
                                        <?php if ($isRegistered): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Đã đăng ký</span>
                                        <?php elseif ($isPassed): ?>
                                        <span class="badge bg-success">Đã học đạt</span>
                                        <?php elseif ($hasConflict): ?>
                                        <span class="badge bg-danger" title="<?php echo implode(', ', $conflictDetails); ?>">
                                            ⚠ Trùng lịch
                                        </span>
                                        <?php elseif ($autoRegistrationLocked): ?>
                                        <span class="badge bg-info text-dark">HK1 đăng ký tự động</span>
                                        <?php elseif (!$isFull && $regOpen): ?>
                                        <form method="POST">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="register">
                                            <input type="hidden" name="section_id" value="<?php echo $sec['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-gold">
                                                <i class="bi bi-plus-circle me-1"></i>Đăng ký
                                            </button>
                                        </form>
                                        <?php elseif ($isFull): ?>
                                        <span class="badge bg-secondary">Đã đầy</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark">Chưa mở</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="11" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Không có học phần nào để đăng ký
                                </td></tr>
                                <?php endif; ?>
                                <tr id="noFilterRows" style="display:none"><td colspan="11" class="text-center text-muted py-4">Không có học phần phù hợp bộ lọc.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-journal-check me-2"></i>Học phần đã đăng ký</span>
                    <span class="badge bg-success fs-6"><?php echo $registeredCredits; ?> tín chỉ</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Mã môn</th>
                                    <th>Tên môn học</th>
                                    <th>Nhóm/tổ</th>
                                    <th>TC</th>
                                    <th>Giảng viên</th>
                                    <th>Lịch học</th>
                                    <th>Phòng</th>
                                    <th>Học phí</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($registeredSubjects)): foreach ($registeredSubjects as $sub):
                                    $dayMap = [];
                                    if (!empty($sub['day_sessions'])) {
                                        $dayMap = parseDaySessionsReg($sub['day_sessions']);
                                    } elseif (!empty($sub['schedule_data'])) {
                                        $slots = json_decode($sub['schedule_data'], true) ?: [];
                                        foreach ($slots as $sl) $dayMap[(int)$sl['day']] = $sl['session'];
                                    } elseif (!empty($sub['schedule_text'])) {
                                        $slots = parseScheduleTextToSlots($sub['schedule_text']);
                                        foreach ($slots as $sl) $dayMap[(int)$sl['day']] = $sl['session'];
                                    }
                                ?>
                                <tr>
                                    <td class="fw-bold text-navy small"><?php echo htmlspecialchars($sub['subject_code']); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                        <span class="badge bg-<?php echo $sub['subject_type']=='Bắt buộc'?'danger':'info'; ?> small"><?php echo htmlspecialchars($sub['subject_type']); ?></span>
                                        <?php if (!empty($sub['is_common'])): ?><span class="badge bg-primary small">Môn chung</span><?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($sub['section_code']); ?></td>
                                    <td class="text-center"><span class="badge bg-navy"><?php echo (int)$sub['credits']; ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars(trim(($sub['degree'] ? $sub['degree'] . '. ' : '') . $sub['teacher_name'])); ?></td>
                                    <td class="small">
                                        <?php foreach ($dayMap as $d => $s): ?>
                                        <span class="badge me-1" style="background:<?php echo $sessionColors[$s] ?? '#666'; ?>"><?php echo ($dayNames[$d] ?? 'N'.$d) . ' ' . ($sessionLabels[$s] ?? $s); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (!empty($sub['start_date']) || !empty($sub['end_date'])): ?>
                                        <div class="text-muted" style="font-size:.7rem"><?php echo !empty($sub['start_date']) ? date('d/m/Y', strtotime($sub['start_date'])) : '--'; ?> → <?php echo !empty($sub['end_date']) ? date('d/m/Y', strtotime($sub['end_date'])) : '--'; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($sub['room']); ?></td>
                                    <td class="small text-success fw-bold"><?php echo number_format((float)$sub['tuition_fee'],0,',','.'); ?>đ</td>
                                    <td>
                                        <?php if (($sub['reg_status'] ?? '') === 'auto_enrolled'): ?>
                                        <span class="badge bg-success">Tự động HK1</span>
                                        <?php else: ?>
                                        <span class="badge bg-primary">Đã đăng ký</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($regOpen && ($sub['reg_status'] ?? '') === 'registered'): ?>
                                        <form method="POST">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="ss_id" value="<?php echo (int)$sub['ss_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Hủy</button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted small">Chỉ xem</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">Chưa đăng ký học phần nào trong học kỳ này.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($registeredSubjects)): ?>
                    <div class="border-top px-4 py-3 bg-light d-flex flex-wrap gap-4">
                        <div><span class="text-muted small">Tổng môn:</span> <strong><?php echo count($registeredSubjects); ?></strong></div>
                        <div><span class="text-muted small">Tổng tín chỉ:</span> <strong><?php echo $registeredCredits; ?></strong></div>
                        <div><span class="text-muted small">Tạm tính học phí:</span> <strong class="text-success"><?php echo number_format($registeredFee,0,',','.'); ?>đ</strong></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
const courseRows = [...document.querySelectorAll('.course-row')];
const searchInput = document.getElementById('courseSearch');
const courseFilter = document.getElementById('courseFilter');
const groupFilter = document.getElementById('groupFilter');
const emptyRow = document.getElementById('noFilterRows');

if (groupFilter) {
    const groups = [...new Set(courseRows.map(row => row.dataset.group).filter(Boolean))].sort();
    groups.forEach(group => {
        const option = document.createElement('option');
        option.value = group;
        option.textContent = group;
        groupFilter.appendChild(option);
    });
}

function applyCourseFilters() {
    const keyword = (searchInput?.value || '').trim().toLowerCase();
    const mode = courseFilter?.value || 'all';
    const group = groupFilter?.value || 'all';
    let visible = 0;

    courseRows.forEach(row => {
        let ok = true;
        if (keyword && !row.dataset.search.includes(keyword)) ok = false;
        if (group !== 'all' && row.dataset.group !== group) ok = false;
        if (mode === 'own_class' && row.dataset.ownClass !== '1') ok = false;
        if (mode === 'cohort' && row.dataset.cohort !== '1') ok = false;
        if (mode === 'registered' && row.dataset.registered !== '1') ok = false;
        if (mode === 'ctdt' && row.dataset.passed === '1') ok = false;
        if (mode === 'failed' && row.dataset.failed !== '1') ok = false;
        if (mode === 'available' && row.dataset.full === '1') ok = false;
        if (mode === 'conflict' && row.dataset.conflict !== '1') ok = false;
        row.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });
    if (emptyRow) emptyRow.style.display = visible === 0 && courseRows.length > 0 ? '' : 'none';
}

[searchInput, courseFilter, groupFilter].forEach(el => el?.addEventListener('input', applyCourseFilters));
[courseFilter, groupFilter].forEach(el => el?.addEventListener('change', applyCourseFilters));
applyCourseFilters();
</script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>

