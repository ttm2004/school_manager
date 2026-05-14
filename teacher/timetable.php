<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/grade_windows.php';
require_once '../academic/includes/academic_helpers.php';
requireRole('teacher');

$stmt = $conn->prepare("SELECT t.*, u.full_name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Đảm bảo cột schedule_data tồn tại
$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}

// Lấy tất cả lớp học phần được phân công kèm lịch học
$stmt = $conn->prepare("
    SELECT cs.id as section_id, cs.section_code, cs.schedule_text, cs.schedule_data,
           cs.day_sessions, cs.start_date, cs.end_date,
           cs.room, cs.max_students, cs.current_students, cs.status,
           s.subject_name, s.credits, s.subject_code,
           COALESCE(NULLIF(s.total_periods,0), s.theory_periods + s.practice_periods, s.credits * 15, 45) AS total_periods,
           sm.semester_name, sm.school_year, sm.id as semester_id, sm.start_date as sem_start, sm.end_date as sem_end,
           sm.grade_submit_deadline
    FROM course_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    WHERE cs.teacher_id = ?
    ORDER BY sm.school_year DESC, sm.semester_name, s.subject_name
");
$stmt->bind_param('i', $teacher['id']);
$stmt->execute();
$allCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/**
 * Fallback: parse schedule_text thành schedule_data JSON
 * Hỗ trợ format: "Thứ 2, tiết 1-3", "Thứ 3, tiết 4-6", v.v.
 */
function parseScheduleText(string $text): array {
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
    // Tách nhiều lịch phân cách bởi ";" hoặc ","
    $parts = preg_split('/[;]+/', $text);
    foreach ($parts as $part) {
        $part = trim(strtolower($part));
        $dayNum = null;
        foreach ($dayMap as $key => $num) {
            if (str_contains($part, $key)) { $dayNum = $num; break; }
        }
        if (!$dayNum) continue;
        // Lấy số tiết đầu
        preg_match('/tiết\s*(\d+)/ui', $part, $m);
        $periodStart = isset($m[1]) ? intval($m[1]) : 1;
        // Xác định buổi theo tiết
        if ($periodStart <= 5)      $session = 'sang';
        elseif ($periodStart <= 10) $session = 'chieu';
        else                        $session = 'toi';
        $slots[] = ['day' => $dayNum, 'session' => $session, 'period_start' => $periodStart];
    }
    return $slots;
}

// Nhóm theo học kỳ
$bySemester = [];
foreach ($allCourses as $c) {
    $key = $c['semester_id'];
    $bySemester[$key]['info']       = $c['semester_name'] . ' ' . $c['school_year'];
    $bySemester[$key]['start_date'] = $c['sem_start'];
    $bySemester[$key]['end_date']   = $c['sem_end'];
    $bySemester[$key]['courses'][]  = $c;
}

$selectedSem = intval($_GET['semester_id'] ?? (array_key_first($bySemester) ?? 0));
$scheduleChanges = [];
if (isset($bySemester[$selectedSem])) {
    $scheduleChanges = academicScheduleChangesBySection(
        $conn,
        array_column($bySemester[$selectedSem]['courses'], 'section_id')
    );
}

$SESSIONS = [
    'sang'  => ['label'=>'Sáng',  'color'=>'#e3f2fd','border'=>'#1976d2','text'=>'#0d47a1','time'=>'7:00 - 11:30'],
    'chieu' => ['label'=>'Chiều', 'color'=>'#fff3e0','border'=>'#f57c00','text'=>'#e65100','time'=>'12:30 - 17:00'],
    'toi'   => ['label'=>'Tối',   'color'=>'#f3e5f5','border'=>'#7b1fa2','text'=>'#4a148c','time'=>'17:30 - 22:00'],
];
$DAYS = [2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'Chủ nhật'];

if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['courses'] as &$c) {
        $dates = academicScheduleSectionDates(
            $c['start_date'] ?: ($c['sem_start'] ?? null),
            $c['day_sessions'] ?? '',
            (int)($c['total_periods'] ?? 45),
            5,
            $bySemester[$selectedSem]['end_date'] ?? null
        );
        if ($dates) {
            $dateSessions = [];
            foreach ($dates as $date) {
                $dow = (int)date('N', strtotime($date));
                $dayKey = $dow === 7 ? 8 : $dow + 1;
                $dayMapForDate = !empty($c['day_sessions']) ? parseDaySessionsTeacher($c['day_sessions']) : [];
                if (isset($dayMapForDate[$dayKey])) {
                    $dateSessions[$date][] = ['day' => $dayKey, 'session' => $dayMapForDate[$dayKey], 'room' => $c['room']];
                }
            }
            foreach ($scheduleChanges[(int)$c['section_id']] ?? [] as $change) {
                unset($dateSessions[$change['original_date']]);
                $newDay = (int)date('N', strtotime($change['new_date']));
                $newDay = $newDay === 7 ? 8 : $newDay + 1;
                $dateSessions[$change['new_date']][] = [
                    'day' => $newDay,
                    'session' => academicScheduleNormalizeSession((string)$change['new_day_session']),
                    'room' => $change['room'] ?: $c['room'],
                    'changed' => true,
                ];
            }
            ksort($dateSessions);
            $c['_class_dates'] = $dates;
            $c['_date_sessions'] = $dateSessions;
            $c['_effective_start'] = $dates[0];
            $effectiveDates = array_keys($dateSessions);
            $c['_effective_end'] = $effectiveDates ? end($effectiveDates) : end($dates);
        } else {
            $c['_class_dates'] = [];
            $c['_date_sessions'] = [];
            $c['_effective_start'] = $c['start_date'] ?: ($c['sem_start'] ?? null);
            $c['_effective_end'] = $c['end_date'] ?: ($c['sem_end'] ?? null);
        }
    }
    unset($c);
}

// ===== TÍNH TUẦN THEO THỜI GIAN HỌC KỲ =====
$semStartTs = !empty($bySemester[$selectedSem]['start_date'])
    ? strtotime($bySemester[$selectedSem]['start_date'])
    : strtotime('monday this week');
$semEndTs = !empty($bySemester[$selectedSem]['end_date'])
    ? strtotime($bySemester[$selectedSem]['end_date'])
    : null;

// Đảm bảo là Thứ 2
$dow = (int)date('N', $semStartTs);
if ($dow != 1) $semStartTs = strtotime('next monday', $semStartTs);

$totalWeeks = 20;
if ($semEndTs) {
    $totalWeeks = max(1, (int)ceil(($semEndTs - $semStartTs + 86400) / (7 * 86400)));
}

$nowWeek     = max(0, (int)floor((time() - $semStartTs) / (7 * 86400)));
$currentWeek = max(0, min(max(0, $totalWeeks - 1), intval($_GET['week'] ?? $nowWeek)));

$weekStartTs = $semStartTs + ($currentWeek * 7 * 86400);
$weekEndTs   = $weekStartTs + (6 * 86400);

$weekOptions = [];
for ($w = 0; $w < $totalWeeks; $w++) {
    $ws = $semStartTs + ($w * 7 * 86400);
    $we = $ws + (6 * 86400);
    $weekOptions[$w] = 'Tuần ' . ($w + 1) . ' [từ ngày ' . date('d/m/Y', $ws) . ' đến ngày ' . date('d/m/Y', $we) . ']';
}

$dayDates = [];
for ($d = 2; $d <= 7; $d++) $dayDates[$d] = $weekStartTs + (($d-2)*86400);
$dayDates[8] = $weekStartTs + (6*86400);

// ===== XÂY DỰNG LƯỚI TKB =====
// Helper parse day_sessions "2:sang,4:chieu"
function parseDaySessionsTeacher(string $ds): array {
    $r = [];
    foreach (explode(',', $ds) as $p) {
        $a = explode(':', trim($p));
        if (count($a)==2 && $a[0] && $a[1]) $r[(int)$a[0]] = $a[1];
    }
    return $r;
}

$timetable = [];
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['courses'] as &$c) {
        // Ưu tiên day_sessions mới, fallback schedule_data, fallback schedule_text
        $dayMap = [];
        if (!empty($c['day_sessions'])) {
            $dayMap = parseDaySessionsTeacher($c['day_sessions']);
        } elseif (!empty($c['schedule_data'])) {
            $slots = json_decode($c['schedule_data'], true) ?: [];
            foreach ($slots as $sl) $dayMap[(int)$sl['day']] = $sl['session'];
        } elseif (!empty($c['schedule_text'])) {
            $slots = parseScheduleText($c['schedule_text']);
            foreach ($slots as $sl) $dayMap[(int)$sl['day']] = $sl['session'];
        }
        $c['_day_map'] = $dayMap;

        if (empty($dayMap)) continue;

        // Kiểm tra lớp có học tuần này không
        $subStart = !empty($c['_effective_start']) ? strtotime($c['_effective_start']) : null;
        $subEnd   = !empty($c['_effective_end'])   ? strtotime($c['_effective_end'])   : null;
        if ($subStart && $weekEndTs   < $subStart) continue;
        if ($subEnd   && $weekStartTs > $subEnd)   continue;

        foreach (($c['_date_sessions'] ?? []) as $date => $entries) {
            $ts = strtotime($date);
            if ($ts < $weekStartTs || $ts > $weekEndTs) continue;
            foreach ($entries as $entry) {
                if (!empty($entry['changed'])) {
                    $dayMap[(int)$entry['day']] = (string)$entry['session'];
                }
            }
        }

        foreach ($dayMap as $day => $session) {
            $classDate = isset($dayDates[$day]) ? date('Y-m-d', $dayDates[$day]) : null;
            if (!empty($c['_date_sessions'])) {
                $dateEntries = array_filter($c['_date_sessions'][$classDate] ?? [], fn($entry) => (int)$entry['day'] === (int)$day);
                if (!$dateEntries) continue;
                $entry = reset($dateEntries);
                $session = (string)$entry['session'];
                $c['_display_room'] = $entry['room'] ?? $c['room'];
                $c['_changed_session'] = !empty($entry['changed']);
            } elseif (!empty($c['_class_dates']) && !in_array($classDate, $c['_class_dates'], true)) {
                continue;
            }
            if (!isset($timetable[$day][$session])) $timetable[$day][$session] = [];
            $alreadyIn = false;
            foreach ($timetable[$day][$session] as $existing) {
                if ($existing['section_id'] === $c['section_id']) { $alreadyIn = true; break; }
            }
            if (!$alreadyIn) $timetable[$day][$session][] = $c;
        }
    }
    unset($c);
}

// Màu sắc
$colors = ['#1a3a6b','#f0a500','#28a745','#dc3545','#6f42c1','#17a2b8','#fd7e14','#20c997','#e83e8c','#6c757d'];
$sectionColors = [];
$colorIdx = 0;
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['courses'] as $c) {
        if (!isset($sectionColors[$c['section_code']])) {
            $sectionColors[$c['section_code']] = $colors[$colorIdx % count($colors)];
            $colorIdx++;
        }
    }
}

$qParams = [];
if ($selectedSem) $qParams['semester_id'] = $selectedSem;
$qString = $qParams ? '&' . http_build_query($qParams) : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Thời khóa biểu - Giảng viên</title>
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
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none"
                        onclick="document.querySelector('.student-sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="fw-bold text-navy"><i class="bi bi-calendar3-week me-2"></i>Thời khóa biểu giảng dạy</span>
            </div>
            <div class="d-flex gap-2 no-print">
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i>In TKB
                </button>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="student-content">

            <!-- Chọn học kỳ -->
            <?php if (!empty($bySemester)): ?>
            <div class="d-flex gap-2 mb-4 flex-wrap no-print">
                <?php foreach ($bySemester as $semId => $semData): ?>
                <a href="?semester_id=<?php echo $semId; ?>&week=0"
                   class="btn btn-<?php echo $semId==$selectedSem?'navy':'outline-secondary'; ?> btn-sm">
                    <i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($semData['info']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($bySemester) || !isset($bySemester[$selectedSem])): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                    Chưa được phân công lớp học phần nào.
                    <div class="mt-2 small">Liên hệ phòng đào tạo để được phân công giảng dạy.</div>
                </div>
            </div>
            <?php else: ?>

            <!-- Header + điều hướng tuần -->
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <h6 class="fw-bold text-navy mb-0 mt-1">
                    <i class="bi bi-calendar3 me-2"></i><?php echo htmlspecialchars($bySemester[$selectedSem]['info']); ?>
                    &bull; <?php echo count($bySemester[$selectedSem]['courses']); ?> lớp học phần
                </h6>
                <div class="d-flex align-items-center gap-2 no-print flex-wrap justify-content-end">
                    <select class="form-select form-select-sm"
                            style="min-width:280px;max-width:100%;"
                            aria-label="Chọn tuần"
                            onchange="window.location.href='?semester_id=<?php echo $selectedSem; ?>&week='+this.value">
                        <?php foreach ($weekOptions as $w => $weekLabel): ?>
                        <option value="<?php echo $w; ?>" <?php echo $w === $currentWeek ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($weekLabel); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($currentWeek > 0): ?>
                    <a href="?semester_id=<?php echo $selectedSem; ?>&week=<?php echo $currentWeek-1; ?>" class="btn btn-sm btn-navy">
                        <i class="bi bi-chevron-left"></i> Tuần trước
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i> Tuần trước</button>
                    <?php endif; ?>

                    <div class="text-center" style="min-width:130px">
                        <div class="fw-bold text-navy" style="font-size:0.9rem">
                            <?php echo date('d/m', $weekStartTs); ?> &ndash; <?php echo date('d/m/Y', $weekEndTs); ?>
                        </div>
                        <?php
                        $isCurrentWeek = (time() >= $weekStartTs && time() <= $weekEndTs);
                        if ($isCurrentWeek): ?>
                        <span class="badge bg-success" style="font-size:0.65rem">Tuần hiện tại</span>
                        <?php endif; ?>
                    </div>

                    <a href="?semester_id=<?php echo $selectedSem; ?>&week=<?php echo min(max(0, $totalWeeks-1), $nowWeek); ?>"
                       class="btn btn-sm btn-outline-secondary"
                       title="Về tuần hiện tại">
                        <i class="bi bi-calendar-check"></i>
                    </a>
                    <?php if ($currentWeek < $totalWeeks - 1): ?>
                    <a href="?semester_id=<?php echo $selectedSem; ?>&week=<?php echo $currentWeek+1; ?>" class="btn btn-sm btn-navy">
                        Tuần sau <i class="bi bi-chevron-right"></i>
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-navy" disabled>Tuần sau <i class="bi bi-chevron-right"></i></button>
                    <?php endif; ?>
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
                                        $dayTs   = $dayDates[$dayNum] ?? 0;
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
                                    <td class="session-header"
                                        style="border-left: 4px solid <?php echo $sessionInfo['border']; ?>; background: <?php echo $sessionInfo['color']; ?>">
                                        <div class="fw-bold" style="color:<?php echo $sessionInfo['text']; ?>"><?php echo $sessionInfo['label']; ?></div>
                                        <div class="session-time"><?php echo $sessionInfo['time']; ?></div>
                                        <div class="session-time">5 tiết/buổi</div>
                                    </td>
                                    <?php foreach ($DAYS as $dayNum => $dayName): ?>
                                    <td>
                                        <?php if (!empty($timetable[$dayNum][$sessionKey])): ?>
                                            <?php foreach ($timetable[$dayNum][$sessionKey] as $c):
                                                $color = $sectionColors[$c['section_code']] ?? '#1a3a6b';
                                            ?>
                                            <div class="subject-card"
                                                 style="background:<?php echo $color; ?>18; border-left-color:<?php echo $color; ?>;"
                                                 data-bs-toggle="tooltip"
                                                 title="<?php echo htmlspecialchars($c['subject_name'] . ' | ' . $c['section_code'] . ' | ' . ($c['_display_room'] ?? $c['room']) . ' | ' . $c['current_students'] . '/' . $c['max_students'] . ' SV'); ?>">
                                                <div class="sub-name" style="color:<?php echo $color; ?>">
                                                    <?php echo htmlspecialchars($c['subject_name']); ?>
                                                </div>
                                                <div class="sub-info">
                                                    <i class="bi bi-tag-fill"></i> <?php echo htmlspecialchars($c['section_code']); ?><br>
                                                    <i class="bi bi-door-open-fill"></i> <?php echo htmlspecialchars(($c['_display_room'] ?? $c['room']) ?: '--'); ?><?php echo !empty($c['_changed_session']) ? ' (đổi lịch)' : ''; ?>
                                                    &bull; <i class="bi bi-people-fill"></i>
                                                    <span class="<?php echo $c['current_students'] >= $c['max_students'] ? 'text-danger' : ''; ?>">
                                                        <?php echo $c['current_students']; ?>/<?php echo $c['max_students']; ?>
                                                    </span>
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

            <!-- Bảng chi tiết từng lớp -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list-ul me-2"></i>Chi tiết lịch giảng dạy
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Môn học</th>
                                    <th>Mã lớp HP</th>
                                    <th>Phòng</th>
                                    <th>Sĩ số</th>
                                    <th>Lịch học</th>
                                    <th>Trạng thái</th>
                                    <th class="no-print">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = 1; foreach ($bySemester[$selectedSem]['courses'] as $c):
                                    $color   = $sectionColors[$c['section_code']] ?? '#1a3a6b';
                                    $dayMap  = $c['_day_map'] ?? [];
                                    $SESSION_LABEL = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];
                                    $SESSION_COLOR = ['sang'=>'#f57c00','chieu'=>'#1976d2','toi'=>'#7b1fa2'];
                                    $SESSION_TIME  = ['sang'=>'7:00–11:30','chieu'=>'12:30–17:00','toi'=>'17:30–22:00'];
                                    $subStart = !empty($c['start_date']) ? date('d/m/Y', strtotime($c['start_date'])) : '--';
                                    $subEnd   = !empty($c['end_date'])   ? date('d/m/Y', strtotime($c['end_date']))   : '--';
                                ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $idx++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="legend-dot" style="background:<?php echo $color; ?>"></span>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($c['subject_name']); ?></div>
                                                <span class="badge bg-navy"><?php echo $c['credits']; ?> TC</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small text-muted fw-bold"><?php echo htmlspecialchars($c['section_code']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($c['room'] ?: '--'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $c['current_students'] >= $c['max_students'] ? 'danger' : 'success'; ?>">
                                            <?php echo $c['current_students']; ?>/<?php echo $c['max_students']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($dayMap)): ?>
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <?php foreach ($dayMap as $d => $s): ?>
                                            <span class="badge" style="background:<?php echo $SESSION_COLOR[$s]??'#666'; ?>; font-size:0.75rem;"
                                                  title="<?php echo $SESSION_TIME[$s]??''; ?>">
                                                <?php echo $DAYS[$d] ?? 'N'.$d; ?>
                                                <?php echo $SESSION_LABEL[$s] ?? $s; ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.7rem">
                                            <?php echo count($dayMap); ?> buổi/tuần × 5 tiết
                                        </div>
                                        <?php
                                        $sdStart = !empty($c['start_date']) ? date('d/m/Y', strtotime($c['start_date'])) : null;
                                        $sdEnd   = !empty($c['end_date'])   ? date('d/m/Y', strtotime($c['end_date']))   : null;
                                        if ($sdStart || $sdEnd): ?>
                                        <div class="text-muted" style="font-size:0.7rem; margin-top:2px;">
                                            <i class="bi bi-calendar-range"></i>
                                            <?php echo $sdStart ?? '--'; ?> → <?php echo $sdEnd ?? '--'; ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted small fst-italic">Chưa có lịch</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $c['status']=='open'?'success':($c['status']=='full'?'warning':'secondary'); ?>">
                                            <?php echo $c['status']=='open'?'Mở':($c['status']=='full'?'Đầy':'Đóng'); ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <div class="d-flex gap-1">
                                            <a href="/university/teacher/my_courses.php?view_students=<?php echo $c['section_id']; ?>"
                                               class="btn btn-sm btn-outline-navy" title="Xem danh sách sinh viên">
                                                <i class="bi bi-people-fill"></i>
                                            </a>
                                            <?php if (isGradeInputWindowOpen($c['end_date'] ?? null, $c['grade_submit_deadline'] ?? null)): ?>
                                                <a href="/university/teacher/grades.php?section_id=<?php echo $c['section_id']; ?>"
                                                   class="btn btn-sm btn-gold" title="Nhập điểm">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
