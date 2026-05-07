<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Kiểm tra cột schedule_data có tồn tại chưa, nếu chưa thì tự tạo
$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}

// Reset và seed lại lịch mẫu (luôn cập nhật để đảm bảo không trùng)
$seedData = [
    // Môn CNTT - phân bổ không trùng nhau
    'CNTT101_01' => '[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]',
    'CNTT102_01' => '[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]',
    'CNTT201_01' => '[{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1},{"day":6,"session":"toi","period_start":1}]',
    'CNTT202_01' => '[{"day":3,"session":"sang","period_start":1},{"day":5,"session":"sang","period_start":1},{"day":7,"session":"sang","period_start":1}]',
    'CNTT203_01' => '[{"day":2,"session":"chieu","period_start":1},{"day":4,"session":"chieu","period_start":1},{"day":6,"session":"chieu","period_start":1}]',
    'KTPM101_01' => '[{"day":3,"session":"toi","period_start":1},{"day":5,"session":"toi","period_start":1},{"day":7,"session":"toi","period_start":1}]',
    'KTPM201_01' => '[{"day":8,"session":"sang","period_start":1},{"day":8,"session":"chieu","period_start":1},{"day":8,"session":"toi","period_start":1}]',
    // Môn khác ngành - không ảnh hưởng SV CNTT
    'QTKD101_01' => '[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]',
    'KT101_01'   => '[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]',
    'NNA101_01'  => '[{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1},{"day":6,"session":"toi","period_start":1}]',
];
foreach ($seedData as $code => $json) {
    $s = $conn->prepare("UPDATE course_sections SET schedule_data=? WHERE section_code=?");
    if ($s) { $s->bind_param('ss', $json, $code); $s->execute(); $s->close(); }
}

// Lấy tất cả môn đã đăng ký (status=registered) kèm lịch học
$stmt = $conn->prepare("
    SELECT ss.id as ss_id, cs.section_code, cs.schedule_text, cs.schedule_data,
           cs.room, cs.day_sessions, cs.start_date, cs.end_date,
           s.subject_name, s.credits,
           u.full_name as teacher_name,
           sm.semester_name, sm.school_year, sm.id as semester_id, sm.start_date as sem_start
    FROM student_subjects ss
    JOIN course_sections cs ON ss.course_section_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE ss.student_id = ? AND ss.status = 'registered'
    ORDER BY sm.school_year DESC, sm.semester_name
");
$stmt->bind_param('i', $student['id']);
$stmt->execute();
$allSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Nhóm theo học kỳ
$bySemester = [];
foreach ($allSubjects as $sub) {
    $key = $sub['semester_id'];
    $bySemester[$key]['info']      = $sub['semester_name'] . ' ' . $sub['school_year'];
    $bySemester[$key]['sem_start'] = $sub['sem_start'];
    $bySemester[$key]['subjects'][] = $sub;
}

// Lấy học kỳ đang chọn
$selectedSem = intval($_GET['semester_id'] ?? (array_key_first($bySemester) ?? 0));

$SESSIONS = [
    'sang'  => ['label'=>'Sáng',  'color'=>'#e3f2fd','border'=>'#1976d2','text'=>'#0d47a1','time'=>'7:00 - 11:30'],
    'chieu' => ['label'=>'Chiều', 'color'=>'#fff3e0','border'=>'#f57c00','text'=>'#e65100','time'=>'12:30 - 17:00'],
    'toi'   => ['label'=>'Tối',   'color'=>'#f3e5f5','border'=>'#7b1fa2','text'=>'#4a148c','time'=>'17:30 - 22:00'],
];
$DAYS = [2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'Chủ nhật'];
// Mapping thứ (2-8) → JS/PHP getDay (0=CN,1=T2...6=T7)
$DAY_TO_DOW = [2=>1,3=>2,4=>3,5=>4,6=>5,7=>6,8=>0];

// ===== TÍNH TUẦN THỰC TẾ =====
// Lấy ngày bắt đầu sớm nhất và kết thúc muộn nhất trong các môn đã đăng ký
$semStartTs = null;
$semEndTs   = null;
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (!empty($sub['start_date'])) {
            $ts = strtotime($sub['start_date']);
            if ($semStartTs === null || $ts < $semStartTs) $semStartTs = $ts;
        }
        if (!empty($sub['end_date'])) {
            $ts = strtotime($sub['end_date']);
            if ($semEndTs === null || $ts > $semEndTs) $semEndTs = $ts;
        }
    }
}
if (!$semStartTs) {
    $semStartTs = isset($bySemester[$selectedSem]['sem_start']) && $bySemester[$selectedSem]['sem_start']
        ? strtotime($bySemester[$selectedSem]['sem_start'])
        : strtotime('monday this week');
}

// Đảm bảo semStartTs là Thứ 2
$dow = (int)date('N', $semStartTs);
if ($dow != 1) $semStartTs = strtotime('last monday', $semStartTs);

// Tuần đang xem — không giới hạn, mặc định tuần hiện tại
$nowWeek     = max(0, (int)floor((time() - $semStartTs) / (7 * 86400)));
$currentWeek = max(0, intval($_GET['week'] ?? $nowWeek));

$weekStartTs = $semStartTs + ($currentWeek * 7 * 86400);
$weekEndTs   = $weekStartTs + (6 * 86400);

// Map thứ → timestamp ngày thực tế trong tuần đang xem
$dayDates = [];
for ($d = 2; $d <= 7; $d++) $dayDates[$d] = $weekStartTs + (($d - 2) * 86400);
$dayDates[8] = $weekStartTs + (6 * 86400); // CN

// ===== XÂY DỰNG LƯỚI TKB =====
// Parse day_sessions: "2:sang,4:chieu" → [day => session]
function parseDaySessions(string $ds): array {
    $result = [];
    foreach (explode(',', $ds) as $part) {
        $arr = explode(':', trim($part));
        if (count($arr) == 2 && $arr[0] && $arr[1]) {
            $result[(int)$arr[0]] = $arr[1];
        }
    }
    return $result;
}

// Fallback: parse schedule_data JSON cũ
function parseScheduleData(string $json): array {
    $slots = json_decode($json, true) ?: [];
    $result = [];
    foreach ($slots as $slot) {
        if (isset($slot['day'], $slot['session'])) {
            $result[(int)$slot['day']] = $slot['session'];
        }
    }
    return $result;
}

$timetable = [];
$conflicts  = [];

if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as &$sub) {
        // Lấy lịch học: ưu tiên day_sessions mới, fallback schedule_data cũ
        $daySessionMap = [];
        if (!empty($sub['day_sessions'])) {
            $daySessionMap = parseDaySessions($sub['day_sessions']);
        } elseif (!empty($sub['schedule_data'])) {
            $daySessionMap = parseScheduleData($sub['schedule_data']);
        }
        $sub['_day_session_map'] = $daySessionMap;

        if (empty($daySessionMap)) continue;

        // Kiểm tra môn này có học trong tuần đang xem không
        $subStart = !empty($sub['start_date']) ? strtotime($sub['start_date']) : null;
        $subEnd   = !empty($sub['end_date'])   ? strtotime($sub['end_date'])   : null;

        // Môn học trong tuần này nếu: không có ngày hoặc tuần nằm trong khoảng start-end
        $activeThisWeek = true;
        if ($subStart && $weekEndTs < $subStart) $activeThisWeek = false;   // tuần này trước ngày bắt đầu
        if ($subEnd   && $weekStartTs > $subEnd) $activeThisWeek = false;   // tuần này sau ngày kết thúc

        $sub['_active_this_week'] = $activeThisWeek;
        if (!$activeThisWeek) continue;

        foreach ($daySessionMap as $day => $session) {
            if (!isset($timetable[$day][$session])) $timetable[$day][$session] = [];
            // Kiểm tra trùng
            if (!empty($timetable[$day][$session])) {
                foreach ($timetable[$day][$session] as $existing) {
                    $key = min($existing['section_code'], $sub['section_code']).'_'.max($existing['section_code'], $sub['section_code']);
                    $already = false;
                    foreach ($conflicts as $cf) {
                        if ($cf['key'] === $key) { $already = true; break; }
                    }
                    if (!$already) {
                        $conflicts[] = ['key'=>$key,'day'=>$day,'session'=>$session,
                            'sub1'=>$existing['subject_name'],'sub2'=>$sub['subject_name'],
                            'code1'=>$existing['section_code'],'code2'=>$sub['section_code']];
                    }
                }
            }
            $timetable[$day][$session][] = $sub;
        }
    }
    unset($sub);
}

// Màu sắc
$colors = ['#1a3a6b','#f0a500','#28a745','#dc3545','#6f42c1','#17a2b8','#fd7e14','#20c997','#e83e8c','#6c757d'];
$subjectColors = [];
$colorIdx = 0;
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (!isset($subjectColors[$sub['section_code']])) {
            $subjectColors[$sub['section_code']] = $colors[$colorIdx % count($colors)];
            $colorIdx++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thời khóa biểu - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        .timetable-grid { border-collapse: collapse; width: 100%; }
        .timetable-grid th, .timetable-grid td {
            border: 1px solid #dee2e6;
            padding: 0;
            vertical-align: top;
            min-width: 120px;
        }
        .timetable-grid th { background: var(--navy); color: #fff; text-align: center; padding: 10px 6px; font-size: 0.85rem; }
        .session-header { background: #f8f9fa; font-weight: 600; font-size: 0.8rem; padding: 8px 10px; border-right: 3px solid #dee2e6; min-width: 90px; }
        .cell-empty { height: 160px; background: #fafafa; }
        .subject-card {
            margin: 4px;
            padding: 10px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            border-left: 4px solid;
            cursor: pointer;
            transition: transform 0.1s;
            min-height: 150px;
        }
        .subject-card:hover { transform: scale(1.02); }
        .subject-card .sub-name { font-weight: 700; line-height: 1.2; }
        .subject-card .sub-info { color: #555; font-size: 0.72rem; margin-top: 2px; }
        .conflict-badge { background: #dc3545; color: #fff; font-size: 0.7rem; padding: 1px 5px; border-radius: 3px; }
        .legend-dot { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
        .session-time { font-size: 0.7rem; color: #888; font-weight: normal; }
        @media print {
            .student-sidebar, .student-topbar, .no-print { display: none !important; }
            .student-main { margin-left: 0 !important; }
            .timetable-grid th, .timetable-grid td { font-size: 0.7rem; }
        }
    </style>
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
            <a href="/university/student/register_subject.php" class="sidebar-link"><i class="bi bi-journal-plus"></i> Đăng ký học phần</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Học phần của tôi</a>
            <a href="/university/student/timetable.php" class="sidebar-link active"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/student/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
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
                <span class="fw-bold text-navy"><i class="bi bi-calendar3-week me-2"></i>Thời khóa biểu</span>
            </div>
            <div class="d-flex gap-2 no-print">
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>In TKB</button>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>

        <div class="student-content">

            <!-- Chọn học kỳ -->
            <?php if (!empty($bySemester)): ?>
            <div class="d-flex gap-2 mb-4 flex-wrap no-print">
                <?php foreach ($bySemester as $semId => $semData): ?>
                <a href="?semester_id=<?php echo $semId; ?>"
                   class="btn btn-<?php echo $semId==$selectedSem?'navy':'outline-secondary'; ?> btn-sm">
                    <i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($semData['info']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Cảnh báo trùng lịch -->
            <?php if (!empty($conflicts)): ?>
            <div class="alert alert-danger mb-4">
                <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Phát hiện TRÙNG LỊCH HỌC!</div>
                <?php foreach ($conflicts as $c): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-danger">Trùng</span>
                    <strong><?php echo $DAYS[$c['day']]; ?></strong> &bull;
                    <strong><?php echo $SESSIONS[$c['session']]['label']; ?></strong>
                    (<?php echo $SESSIONS[$c['session']]['time']; ?>) &bull;
                    <span class="text-danger fw-bold"><?php echo htmlspecialchars($c['sub1']); ?></span>
                    <i class="bi bi-arrow-left-right"></i>
                    <span class="text-danger fw-bold"><?php echo htmlspecialchars($c['sub2']); ?></span>
                </div>
                <?php endforeach; ?>
                <div class="mt-2 small text-muted">Vui lòng hủy đăng ký một trong các môn bị trùng lịch.</div>
            </div>
            <?php endif; ?>

            <?php if (empty($bySemester) || !isset($bySemester[$selectedSem])): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                    Chưa đăng ký môn học nào. <a href="/university/student/register_subject.php">Đăng ký ngay</a>
                </div>
            </div>
            <?php else: ?>

            <!-- Header: Thông tin học kỳ + Navigation tuần -->
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <h6 class="fw-bold text-navy mb-0 mt-1">
                    <i class="bi bi-calendar3 me-2"></i><?php echo htmlspecialchars($bySemester[$selectedSem]['info']); ?>
                    &bull; <?php echo count($bySemester[$selectedSem]['subjects']); ?> môn đã đăng ký
                </h6>
                <div class="d-flex align-items-center gap-2 no-print">
                    <?php if ($currentWeek > 0): ?>
                    <a href="?semester_id=<?php echo $selectedSem; ?>&week=<?php echo $currentWeek-1; ?>" class="btn btn-sm btn-navy">
                        <i class="bi bi-chevron-left"></i> Tuần trước
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i> Tuần trước</button>
                    <?php endif; ?>

                    <div class="text-center px-2">
                        <div class="fw-bold text-navy" style="font-size:0.9rem">
                            <?php echo date('d/m', $weekStartTs); ?> &ndash; <?php echo date('d/m/Y', $weekEndTs); ?>
                        </div>
                        <?php
                        $isCurrentWeek = (time() >= $weekStartTs && time() <= $weekEndTs);
                        if ($isCurrentWeek): ?>
                        <span class="badge bg-success" style="font-size:0.65rem">Tuần hiện tại</span>
                        <?php endif; ?>
                    </div>

                    <a href="?semester_id=<?php echo $selectedSem; ?>&week=<?php echo $currentWeek+1; ?>" class="btn btn-sm btn-navy">
                        Tuần sau <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>



            <!-- Lưới thời khóa biểu -->
            <div class="card mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="timetable-grid">
                            <thead>
                                <tr>
                                    <th style="min-width:90px">Buổi / Ngày</th>
                                    <?php foreach ($DAYS as $dayNum => $dayName):
                                        $dayTs = $dayDates[$dayNum] ?? 0;
                                        $isToday = $dayTs && date('Y-m-d', $dayTs) == date('Y-m-d');
                                    ?>
                                    <th style="<?php echo $isToday ? 'background:#f0a500;color:#fff;' : ''; ?>">
                                        <?php echo $dayName; ?>
                                        <?php if ($dayTs): ?>
                                        <div style="font-size:0.72rem;font-weight:400;opacity:0.85"><?php echo date('d/m', $dayTs); ?></div>
                                        <?php endif; ?>
                                        <?php if ($isToday): ?><div style="font-size:0.65rem">Hôm nay</div><?php endif; ?>
                                    </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($SESSIONS as $sessionKey => $sessionInfo): ?>
                                <tr>
                                    <td class="session-header" style="border-left: 4px solid <?php echo $sessionInfo['border']; ?>; background: <?php echo $sessionInfo['color']; ?>">
                                        <div class="fw-bold" style="color:<?php echo $sessionInfo['text']; ?>"><?php echo $sessionInfo['label']; ?></div>
                                        <div class="session-time"><?php echo $sessionInfo['time']; ?></div>
                                        <div class="session-time">5 tiết/buổi</div>
                                    </td>
                                    <?php foreach ($DAYS as $dayNum => $dayName): ?>
                                    <td>
                                        <?php if (!empty($timetable[$dayNum][$sessionKey])): ?>
                                            <?php foreach ($timetable[$dayNum][$sessionKey] as $idx => $sub):
                                                $color = $subjectColors[$sub['section_code']] ?? '#1a3a6b';
                                                $isConflict = count($timetable[$dayNum][$sessionKey]) > 1;
                                            ?>
                                            <div class="subject-card"
                                                 style="background:<?php echo $color; ?>18; border-left-color:<?php echo $color; ?>;"
                                                 data-bs-toggle="tooltip"
                                                 title="<?php echo htmlspecialchars($sub['subject_name'] . ' | ' . $sub['teacher_name'] . ' | ' . $sub['room']); ?>">
                                                <?php if ($isConflict): ?>
                                                <span class="conflict-badge mb-1 d-inline-block">⚠ Trùng lịch</span>
                                                <?php endif; ?>
                                                <div class="sub-name" style="color:<?php echo $color; ?>"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                                <div class="sub-info">
                                                    <i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($sub['teacher_name']); ?><br>
                                                    <i class="bi bi-door-open-fill"></i> <?php echo htmlspecialchars($sub['room']); ?>
                                                    &bull; <i class="bi bi-book-fill"></i> <?php echo $sub['credits']; ?> TC
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="cell-empty"></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bảng chi tiết lịch học từng môn -->
            <div class="card">
                <div class="card-header"><i class="bi bi-list-ul me-2"></i>Chi tiết lịch học từng môn (6 buổi × 5 tiết)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Môn học</th>
                                    <th>Mã HP</th>
                                    <th>Giảng viên</th>
                                    <th>Phòng</th>
                                    <th>Lịch học (6 buổi × 5 tiết)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx=1; foreach ($bySemester[$selectedSem]['subjects'] as $sub):
                                    $color = $subjectColors[$sub['section_code']] ?? '#1a3a6b';
                                    $dsMap = $sub['_day_session_map'] ?? [];
                                    $SESSION_LABEL = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];
                                    $SESSION_COLOR = ['sang'=>'#f57c00','chieu'=>'#1976d2','toi'=>'#7b1fa2'];
                                    $SESSION_TIME  = ['sang'=>'7:00–11:30','chieu'=>'12:30–17:00','toi'=>'17:30–22:00'];
                                    $spw = count($dsMap);
                                    $subStart = !empty($sub['start_date']) ? date('d/m/Y', strtotime($sub['start_date'])) : '--';
                                    $subEnd   = !empty($sub['end_date'])   ? date('d/m/Y', strtotime($sub['end_date']))   : '--';
                                    $activeNow = $sub['_active_this_week'] ?? false;
                                ?>
                                <tr class="<?php echo $activeNow ? '' : 'table-secondary'; ?>">
                                    <td><?php echo $idx++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="legend-dot" style="background:<?php echo $color; ?>"></span>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                                <span class="badge bg-navy"><?php echo $sub['credits']; ?> TC</span>
                                                <?php if (!$activeNow): ?>
                                                <span class="badge bg-secondary ms-1">Không học tuần này</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($sub['section_code']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($sub['teacher_name']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($sub['room']); ?></td>
                                    <td>
                                        <?php if (!empty($dsMap)): ?>
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <?php foreach ($dsMap as $d => $sess):
                                                $bgColor = $SESSION_COLOR[$sess] ?? '#666';
                                            ?>
                                            <span class="badge"
                                                  style="background:<?php echo $bgColor; ?>; font-size:0.75rem;"
                                                  title="<?php echo $SESSION_TIME[$sess] ?? ''; ?>">
                                                <?php echo $DAYS[$d] ?? 'N'.$d; ?>
                                                <?php echo $SESSION_LABEL[$sess] ?? $sess; ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.7rem">
                                            <?php echo $spw; ?> buổi/tuần × 5 tiết
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted small fst-italic">Chưa có lịch</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
// Khởi tạo tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});
</script>
</body>
</html>
