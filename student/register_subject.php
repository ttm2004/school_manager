<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

// Lấy thông tin sinh viên
$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Tự động thêm cột schedule_data nếu chưa có
$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}
// Tự động seed lịch mẫu nếu chưa có
// Luôn cập nhật lịch mẫu để đảm bảo không trùng
$seedData = [
    'CNTT101_01'=>'[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]',
    'CNTT102_01'=>'[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]',
    'CNTT201_01'=>'[{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1},{"day":6,"session":"toi","period_start":1}]',
    'CNTT202_01'=>'[{"day":3,"session":"sang","period_start":1},{"day":5,"session":"sang","period_start":1},{"day":7,"session":"sang","period_start":1}]',
    'CNTT203_01'=>'[{"day":2,"session":"chieu","period_start":1},{"day":4,"session":"chieu","period_start":1},{"day":6,"session":"chieu","period_start":1}]',
    'KTPM101_01'=>'[{"day":3,"session":"toi","period_start":1},{"day":5,"session":"toi","period_start":1},{"day":7,"session":"toi","period_start":1}]',
    'KTPM201_01'=>'[{"day":8,"session":"sang","period_start":1},{"day":8,"session":"chieu","period_start":1},{"day":8,"session":"toi","period_start":1}]',
    'QTKD101_01'=>'[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]',
    'KT101_01'  =>'[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]',
    'NNA101_01' =>'[{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1},{"day":6,"session":"toi","period_start":1}]',
];
foreach ($seedData as $code => $json) {
    $s = $conn->prepare("UPDATE course_sections SET schedule_data=? WHERE section_code=?");
    if ($s) { $s->bind_param('ss', $json, $code); $s->execute(); $s->close(); }
}

$success = $error = '';

// Xử lý đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $section_id = intval($_POST['section_id'] ?? 0);

    if ($action === 'register' && $section_id) {
        // Kiểm tra học kỳ mở
        $sem = $conn->query("SELECT * FROM semesters WHERE status='open' AND register_start <= NOW() AND register_end >= NOW() LIMIT 1")->fetch_assoc();
        if (!$sem) {
            $error = 'Hiện tại không trong thời gian đăng ký học phần.';
        } else {
            // Kiểm tra đã đăng ký chưa (cùng lớp)
            $chk = $conn->prepare("SELECT id FROM student_subjects WHERE student_id=? AND course_section_id=?");
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
                      AND cs2.subject_id = cs1.subject_id
                      AND cs2.semester_id = cs1.semester_id
                    LIMIT 1
                ");
                $dupChk->bind_param('ii', $section_id, $student['id']);
                $dupChk->execute();
                $dupSubject = $dupChk->get_result()->fetch_assoc();
                $dupChk->close();
            }

            if ($exists) {
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
                            WHERE ss.student_id = ? AND ss.status = 'registered' AND cs.semester_id = ?
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
                        $ins = $conn->prepare("INSERT INTO student_subjects (student_id, course_section_id, status) VALUES (?,?,'registered')");
                        $ins->bind_param('ii', $student['id'], $section_id);
                        if ($ins->execute()) {
                            $conn->query("UPDATE course_sections SET current_students = current_students + 1 WHERE id=$section_id");
                            $success = '✅ Đăng ký học phần <strong>' . htmlspecialchars($sec['subject_name']) . '</strong> thành công! <a href="/university/student/timetable.php" class="alert-link">Xem thời khóa biểu</a>';
                        } else {
                            $error = 'Lỗi đăng ký: ' . $conn->error;
                        }
                        $ins->close();
                    }
                }
            }
        }
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

// Lấy học kỳ đang mở đăng ký (ưu tiên học kỳ có lớp học phần)
$semester = $conn->query("
    SELECT sm.* FROM semesters sm
    WHERE sm.status = 'open'
      AND sm.register_start <= NOW()
      AND sm.register_end >= NOW()
      AND EXISTS (SELECT 1 FROM course_sections cs WHERE cs.semester_id = sm.id AND cs.status != 'closed')
    ORDER BY sm.id DESC LIMIT 1
")->fetch_assoc();

// Nếu không có học kỳ nào đang mở đăng ký có lớp HP, lấy học kỳ mở bất kỳ
if (!$semester) {
    $semester = $conn->query("SELECT * FROM semesters WHERE status='open' ORDER BY id DESC LIMIT 1")->fetch_assoc();
}

$now = time();
$regOpen = false;
$regMsg  = '';

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
}

// Danh sách lớp học phần có thể đăng ký
$sections = [];
if ($semester) {
    $stmt = $conn->prepare("
        SELECT cs.*, s.subject_name, s.credits, s.subject_type,
               u.full_name as teacher_name, t.degree,
               cs.start_date, cs.end_date
        FROM course_sections cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN teachers t ON cs.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE cs.semester_id = ?
          AND cs.status != 'closed'
          AND cs.id NOT IN (
              SELECT course_section_id FROM student_subjects
              WHERE student_id = ? AND status != 'cancelled'
          )
        ORDER BY s.subject_name
    ");
    $stmt->bind_param('ii', $semester['id'], $student['id']);
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
        ORDER BY sm.id DESC LIMIT 1
    ")->fetch_assoc();
    if ($altSem && $altSem['id'] != $semester['id']) {
        $semester = $altSem;
        $rs = $semester['register_start'] ? strtotime($semester['register_start']) : 0;
        $re = $semester['register_end']   ? strtotime($semester['register_end'])   : 0;
        $regOpen = $rs && $re && $now >= $rs && $now <= $re;
        $stmt = $conn->prepare("
            SELECT cs.*, s.subject_name, s.credits, s.subject_type,
                   u.full_name as teacher_name, t.degree,
                   cs.start_date, cs.end_date
            FROM course_sections cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN teachers t ON cs.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE cs.semester_id = ?
              AND cs.status != 'closed'
              AND cs.id NOT IN (
                  SELECT course_section_id FROM student_subjects
                  WHERE student_id = ? AND status != 'cancelled'
              )
            ORDER BY s.subject_name
        ");
        $stmt->bind_param('ii', $semester['id'], $student['id']);
        $stmt->execute();
        $sections = $stmt->get_result();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký học phần - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="sidebar-brand-text"><div>Cổng Sinh viên</div><small><?php echo htmlspecialchars($student['student_code']); ?></small></div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/student/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a href="/university/student/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Hồ sơ cá nhân</a>
            <a href="/university/student/register_subject.php" class="sidebar-link active"><i class="bi bi-journal-plus"></i> Đăng ký học phần</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Học phần của tôi</a>
            <a href="/university/student/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Kết quả học tập</a>
            <a href="/university/student/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Đánh giá giảng viên</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chủ</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </nav>
    </div>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy">Đăng ký học phần</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">
            <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <?php if ($semester): ?>
            <div class="alert alert-<?php echo $regOpen ? 'success' : 'warning'; ?> d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-<?php echo $regOpen ? 'unlock-fill' : 'lock-fill'; ?> fs-5"></i>
                <div>
                    <strong><?php echo htmlspecialchars($semester['semester_name'] . ' ' . $semester['school_year']); ?></strong>
                    <?php if ($regOpen): ?>
                    &bull; <span class="fw-bold text-success">Đang mở đăng ký</span>
                    &bull; Hạn đăng ký: <strong><?php echo date('d/m/Y H:i', strtotime($semester['register_end'])); ?></strong>
                    <?php else: ?>
                    &bull; <span class="fw-bold text-danger">Chưa mở đăng ký</span>
                    <?php if ($regMsg): ?>&bull; <?php echo $regMsg; ?><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Hiện tại chưa có học kỳ nào đang mở.</div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header"><i class="bi bi-journal-plus me-2"></i>Danh sách học phần có thể đăng ký</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Mã HP</th>
                                    <th>Tên môn học</th>
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
                                    foreach (explode(',', $ds) as $p) {
                                        $a = explode(':', trim($p));
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
                                        WHERE ss.student_id=? AND ss.status='registered' AND cs.semester_id=?
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

                                if ($sections && $sections->num_rows > 0): while ($sec = $sections->fetch_assoc()):
                                $isFull = $sec['current_students'] >= $sec['max_students'];

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
                                <tr class="<?php echo $isFull ? 'table-secondary' : ($hasConflict ? 'table-warning' : ''); ?>">
                                    <td class="fw-bold text-navy small"><?php echo htmlspecialchars($sec['section_code']); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($sec['subject_name']); ?></div>
                                        <span class="badge bg-<?php echo $sec['subject_type']=='Bắt buộc'?'danger':'info'; ?> small"><?php echo $sec['subject_type']; ?></span>
                                    </td>
                                    <td class="text-center"><span class="badge bg-navy"><?php echo $sec['credits']; ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars($sec['degree'] . '. ' . $sec['teacher_name']); ?></td>
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
                                        <?php if ($hasConflict): ?>
                                        <span class="badge bg-danger" title="<?php echo implode(', ', $conflictDetails); ?>">
                                            ⚠ Trùng lịch
                                        </span>
                                        <?php elseif (!$isFull && $regOpen): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="register">
                                            <input type="hidden" name="section_id" value="<?php echo $sec['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-gold"
                                                onclick="return confirm('Đăng ký học phần <?php echo htmlspecialchars($sec['subject_name']); ?>?')">
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
                                <?php endwhile; else: ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Không có học phần nào để đăng ký
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
