<?php
/** API: /api/grades */
requireApiAuth();
require_once __DIR__ . '/../../includes/grade_windows.php';

// Hàm tính điểm
function calcGrade(float $p, float $m, float $f): array {
    $total  = round($p * 0.2 + $m * 0.3 + $f * 0.5, 2);
    $letter = match(true) {
        $total >= 8.5 => 'A',
        $total >= 8.0 => 'B+',
        $total >= 7.0 => 'B',
        $total >= 6.0 => 'C+',
        $total >= 5.0 => 'C',
        $total >= 4.0 => 'D+',
        $total >= 3.5 => 'D',
        default       => 'F',
    };
    return ['total_score' => $total, 'letter_grade' => $letter];
}

function gradeDemoContext(mysqli $conn, int $studentSubjectId): array {
    $stmt = $conn->prepare("
        SELECT ss.data_mode, ss.demo_batch_id
        FROM student_subjects ss
        WHERE ss.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $studentSubjectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return [
        'data_mode' => (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system',
        'demo_batch_id' => (string)($row['demo_batch_id'] ?? ''),
    ];
}

// GET /api/grades?section_id=X
if ($method === 'GET' && !$action) {
    $sectionId = (int)($_GET['section_id'] ?? 0);
    $studentId = (int)($_GET['student_id'] ?? 0);

    if ($sectionId) {
        // Kiểm tra quyền: GV chỉ xem lớp của mình, trừ admin/academic
        if ($_SESSION['role'] === 'teacher') {
            $chk = $conn->prepare("SELECT id FROM course_sections WHERE id=? AND teacher_id=(SELECT id FROM teachers WHERE user_id=? LIMIT 1) LIMIT 1");
            $chk->bind_param('ii', $sectionId, $_SESSION['user_id']);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) apiError('Không có quyền xem điểm lớp này', 403);
            $chk->close();
        }

        $stmt = $conn->prepare(
            "SELECT ss.id AS student_subject_id, st.student_code, u.full_name,
                    g.id AS grade_id, g.process_score, g.midterm_score, g.final_score,
                    g.total_score, g.letter_grade, g.note, g.updated_at
             FROM student_subjects ss
             JOIN students st ON ss.student_id=st.id
             JOIN users u ON st.user_id=u.id
             LEFT JOIN grades g ON g.student_subject_id=ss.id
             WHERE ss.course_section_id=? AND ss.status IN ('registered','auto_enrolled')
             ORDER BY u.full_name"
        );
        $stmt->bind_param('i', $sectionId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        apiOk($rows);
    }

    if ($studentId) {
        // SV chỉ xem điểm của mình
        if ($_SESSION['role'] === 'student') {
            $myId = (int)$conn->query("SELECT id FROM students WHERE user_id={$_SESSION['user_id']} LIMIT 1")->fetch_assoc()['id'];
            if ($myId !== $studentId) apiError('Không có quyền xem điểm của sinh viên khác', 403);
        }
        $stmt = $conn->prepare(
            "SELECT ss.id AS student_subject_id, cs.section_code, s.subject_name, s.credits,
                    sm.semester_name, sm.school_year,
                    g.process_score, g.midterm_score, g.final_score, g.total_score, g.letter_grade
             FROM student_subjects ss
             JOIN course_sections cs ON ss.course_section_id=cs.id
             JOIN subjects s ON cs.subject_id=s.id
             JOIN semesters sm ON cs.semester_id=sm.id
             LEFT JOIN grades g ON g.student_subject_id=ss.id
             WHERE ss.student_id=? AND ss.status IN ('registered','auto_enrolled')
             ORDER BY sm.id DESC, s.subject_name"
        );
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        apiOk($rows);
    }

    apiError('Cần truyền section_id hoặc student_id');
}

// POST /api/grades — nhập/cập nhật điểm
if ($method === 'POST' && !$action) {
    requireApiRole(['teacher', 'academic_manager', 'admin']);

    $ssId    = (int)($body['student_subject_id'] ?? 0);
    $process = isset($body['process_score']) && $body['process_score'] !== '' ? (float)$body['process_score'] : null;
    $midterm = isset($body['midterm_score'])  && $body['midterm_score']  !== '' ? (float)$body['midterm_score']  : null;
    $final   = isset($body['final_score'])    && $body['final_score']    !== '' ? (float)$body['final_score']    : null;
    $note    = trim($body['note'] ?? '');

    if (!$ssId) apiError('student_subject_id là bắt buộc');

    // Kiểm tra grade_lock
    $window = getGradeInputWindowForStudentSubject($conn, $ssId);
    if (!$window) apiError('student_subject_id khong ton tai', 404);
    $sectionId = (int)$window['id'];
    $lockChk = $conn->query("SHOW TABLES LIKE 'grade_locks'");
    if ($lockChk && $lockChk->num_rows > 0) {
        $locked = $conn->query("SELECT id FROM grade_locks WHERE course_section_id=$sectionId LIMIT 1");
        if ($locked && $locked->num_rows > 0) {
            apiError('Điểm đã bị khóa. Liên hệ Phòng Đào tạo để mở khóa.', 403);
        }
    }

    // GV chỉ nhập điểm lớp của mình
    if ($_SESSION['role'] === 'teacher') {
        if (!$window['is_grade_window_open']) {
            apiError($window['grade_window_message'], 403);
        }
        $chk = $conn->prepare(
            "SELECT cs.id FROM course_sections cs
             JOIN student_subjects ss ON ss.course_section_id=cs.id
             WHERE ss.id=? AND cs.teacher_id=(SELECT id FROM teachers WHERE user_id=? LIMIT 1) LIMIT 1"
        );
        $chk->bind_param('ii', $ssId, $_SESSION['user_id']);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) apiError('Không có quyền nhập điểm lớp này', 403);
        $chk->close();
    }

    $total = $letter = null;
    if ($process !== null && $midterm !== null && $final !== null) {
        $calc   = calcGrade($process, $midterm, $final);
        $total  = $calc['total_score'];
        $letter = $calc['letter_grade'];
    }

    $chk = $conn->prepare("SELECT id FROM grades WHERE student_subject_id=?");
    $chk->bind_param('i', $ssId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($exists) {
        $demoContext = gradeDemoContext($conn, $ssId);
        $stmt = $conn->prepare(
            "UPDATE grades SET process_score=?, midterm_score=?, final_score=?,
             total_score=?, letter_grade=?, note=?, data_mode=?, demo_batch_id=? WHERE student_subject_id=?"
        );
        $stmt->bind_param('ddddssssi', $process,$midterm,$final,$total,$letter,$note,$demoContext['data_mode'],$demoContext['demo_batch_id'],$ssId);
    } else {
        $demoContext = gradeDemoContext($conn, $ssId);
        $stmt = $conn->prepare(
            "INSERT INTO grades (student_subject_id, process_score, midterm_score, final_score,
             total_score, letter_grade, note, data_mode, demo_batch_id) VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('iddddssss', $ssId,$process,$midterm,$final,$total,$letter,$note,$demoContext['data_mode'],$demoContext['demo_batch_id']);
    }

    if ($stmt->execute()) {
        $stmt->close();
        apiOk([
            'student_subject_id' => $ssId,
            'process_score'  => $process,
            'midterm_score'  => $midterm,
            'final_score'    => $final,
            'total_score'    => $total,
            'letter_grade'   => $letter,
        ], 'Lưu điểm thành công');
    }
    apiError('Lỗi lưu điểm: ' . $conn->error);
}

// POST /api/grades/batch — nhập điểm hàng loạt
if ($method === 'POST' && $action === 'batch') {
    requireApiRole(['teacher', 'academic_manager', 'admin']);
    $grades = $body['grades'] ?? [];
    if (empty($grades)) apiError('Danh sách điểm trống');

    $saved = 0; $errors = [];
    foreach ($grades as $g) {
        $ssId    = (int)($g['student_subject_id'] ?? 0);
        $process = isset($g['process_score']) && $g['process_score'] !== '' ? (float)$g['process_score'] : null;
        $midterm = isset($g['midterm_score'])  && $g['midterm_score']  !== '' ? (float)$g['midterm_score']  : null;
        $final   = isset($g['final_score'])    && $g['final_score']    !== '' ? (float)$g['final_score']    : null;
        $note    = trim($g['note'] ?? '');

        if (!$ssId) { $errors[] = "Invalid student_subject_id"; continue; }

        $window = getGradeInputWindowForStudentSubject($conn, $ssId);
        if (!$window) { $errors[] = "student_subject_id=$ssId khong ton tai"; continue; }
        if ($_SESSION['role'] === 'teacher') {
            if (!$window['is_grade_window_open']) {
                $errors[] = "ss_id=$ssId: " . $window['grade_window_message'];
                continue;
            }
            if ((int)$window['teacher_id'] !== (int)$conn->query("SELECT id FROM teachers WHERE user_id={$_SESSION['user_id']} LIMIT 1")->fetch_assoc()['id']) {
                $errors[] = "ss_id=$ssId: Khong co quyen nhap diem lop nay";
                continue;
            }
        }

        $total = $letter = null;
        if ($process !== null && $midterm !== null && $final !== null) {
            $calc = calcGrade($process, $midterm, $final);
            $total = $calc['total_score']; $letter = $calc['letter_grade'];
        }

        $chk = $conn->prepare("SELECT id FROM grades WHERE student_subject_id=?");
        $chk->bind_param('i', $ssId); $chk->execute();
        $exists = $chk->get_result()->fetch_assoc(); $chk->close();

        if ($exists) {
            $demoContext = gradeDemoContext($conn, $ssId);
            $stmt = $conn->prepare("UPDATE grades SET process_score=?,midterm_score=?,final_score=?,total_score=?,letter_grade=?,note=?,data_mode=?,demo_batch_id=? WHERE student_subject_id=?");
            $stmt->bind_param('ddddssssi', $process,$midterm,$final,$total,$letter,$note,$demoContext['data_mode'],$demoContext['demo_batch_id'],$ssId);
        } else {
            $demoContext = gradeDemoContext($conn, $ssId);
            $stmt = $conn->prepare("INSERT INTO grades (student_subject_id,process_score,midterm_score,final_score,total_score,letter_grade,note,data_mode,demo_batch_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iddddssss', $ssId,$process,$midterm,$final,$total,$letter,$note,$demoContext['data_mode'],$demoContext['demo_batch_id']);
        }
        if ($stmt->execute()) $saved++;
        else $errors[] = "Error for ss_id=$ssId: " . $conn->error;
        $stmt->close();
    }

    apiOk(['saved' => $saved, 'errors' => $errors], "Đã lưu $saved điểm");
}

