<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../academic/includes/academic_helpers.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
requireNoTuitionLock((int)($student['id'] ?? 0));

$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}

$stmt = $conn->prepare("
    SELECT ss.id as ss_id, cs.id as section_id, cs.section_code, cs.schedule_text, cs.schedule_data,
           cs.room, cs.day_sessions, cs.start_date, cs.end_date, cs.data_mode as section_data_mode,
           s.subject_name, s.credits,
           COALESCE(NULLIF(s.total_periods,0), s.theory_periods + s.practice_periods, s.credits * 15, 45) AS total_periods,
           COALESCE(u.full_name, 'Chưa phân công') as teacher_name,
           sm.semester_name, sm.school_year, sm.data_mode as semester_data_mode,
           sm.id as semester_id, sm.start_date as sem_start, sm.end_date as sem_end
    FROM student_subjects ss
    JOIN course_sections cs ON ss.course_section_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    LEFT JOIN teachers t ON cs.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE ss.student_id = ? AND ss.status IN ('registered','auto_enrolled')
    ORDER BY sm.school_year DESC, sm.semester_name
");
$stmt->bind_param('i', $student['id']);
$stmt->execute();
$allSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$bySemester = [];
foreach ($allSubjects as $sub) {
    $key = $sub['semester_id'];
    $bySemester[$key]['info']      = $sub['semester_name'] . ' - Năm học ' . $sub['school_year'];
    $bySemester[$key]['sem_start'] = $sub['sem_start'];
    $bySemester[$key]['sem_end']   = $sub['sem_end'];
    $bySemester[$key]['data_mode'] = (($sub['semester_data_mode'] ?? 'system') === 'test' || ($sub['section_data_mode'] ?? 'system') === 'test')
        ? 'test'
        : ($bySemester[$key]['data_mode'] ?? 'system');
    $bySemester[$key]['subjects'][] = $sub;
}

$selectedSem = intval($_GET['semester_id'] ?? (array_key_first($bySemester) ?? 0));
$scheduleChanges = [];
if (isset($bySemester[$selectedSem])) {
    $scheduleChanges = academicScheduleChangesBySection(
        $conn,
        array_column($bySemester[$selectedSem]['subjects'], 'section_id')
    );
}

function parseDaySessions(string $ds): array {
    $r = [];
    foreach (explode(',', $ds) as $p) {
        $a = explode(':', trim($p));
        if (count($a)==2 && $a[0] && $a[1]) $r[(int)$a[0]] = $a[1];
    }
    return $r;
}
function parseScheduleData(string $json): array {
    $slots = json_decode($json, true) ?: [];
    $r = [];
    foreach ($slots as $sl) {
        if (isset($sl['day'], $sl['session'])) $r[(int)$sl['day']] = $sl['session'];
    }
    return $r;
}

// Tiết theo buổi
$SESSION_PERIODS = ['sang'=>[1,5],'chieu'=>[6,10]];
$SESSION_TIMES = ['sang'=>'7h00 - 11h20','chieu'=>'12h30 - 16h50'];
$DAYS = [2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'Chủ Nhật'];

if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as &$sub) {
        $limitEnd = $sub['end_date'] ?: ($bySemester[$selectedSem]['sem_end'] ?? null);
        $dates = academicScheduleSectionDates(
            $sub['start_date'] ?: ($sub['sem_start'] ?? null),
            $sub['day_sessions'] ?? '',
            (int)($sub['total_periods'] ?? 45),
            5,
            $limitEnd
        );
        if ($dates) {
            $dateSessions = [];
            foreach ($dates as $date) {
                $dow = (int)date('N', strtotime($date));
                $dayKey = $dow === 7 ? 8 : $dow + 1;
                $dsMapForDate = !empty($sub['day_sessions']) ? parseDaySessions($sub['day_sessions']) : [];
                if (isset($dsMapForDate[$dayKey])) {
                    $dateSessions[$date][] = ['day' => $dayKey, 'session' => $dsMapForDate[$dayKey], 'room' => $sub['room']];
                }
            }
            foreach ($scheduleChanges[(int)$sub['section_id']] ?? [] as $change) {
                unset($dateSessions[$change['original_date']]);
                $newDay = (int)date('N', strtotime($change['new_date']));
                $newDay = $newDay === 7 ? 8 : $newDay + 1;
                $newSession = academicScheduleNormalizeSession((string)$change['new_day_session']);
                $dateSessions[$change['new_date']][] = [
                    'day' => $newDay,
                    'session' => $newSession,
                    'room' => $change['room'] ?: $sub['room'],
                    'changed' => true,
                ];
            }
            ksort($dateSessions);
            $sub['_class_dates'] = $dates;
            $sub['_date_sessions'] = $dateSessions;
            $sub['_effective_start'] = $dates[0];
            $effectiveDates = array_keys($dateSessions);
            $sub['_effective_end'] = $effectiveDates ? end($effectiveDates) : end($dates);
        } else {
            $sub['_class_dates'] = [];
            $sub['_date_sessions'] = [];
            $sub['_effective_start'] = $sub['start_date'] ?: ($sub['sem_start'] ?? null);
            $sub['_effective_end'] = $sub['end_date'] ?: ($sub['sem_end'] ?? null);
        }
    }
    unset($sub);
}

// Tính tuần theo thời gian bắt đầu/kết thúc học kỳ.
$semStartTs = isset($bySemester[$selectedSem]['sem_start']) && $bySemester[$selectedSem]['sem_start']
    ? strtotime($bySemester[$selectedSem]['sem_start'])
    : strtotime('monday this week');
$semEndTs = isset($bySemester[$selectedSem]['sem_end']) && $bySemester[$selectedSem]['sem_end']
    ? strtotime($bySemester[$selectedSem]['sem_end'])
    : null;
$dow = (int)date('N', $semStartTs);
if ($dow!=1) $semStartTs = strtotime('next monday', $semStartTs);

$timetableEndTs = isset($bySemester[$selectedSem])
    ? academicTimetableResolveEndTs($bySemester[$selectedSem], $bySemester[$selectedSem]['subjects'] ?? [])
    : $semEndTs;

$totalWeeks = 20;
if ($timetableEndTs) {
    $totalWeeks = max(1,(int)ceil(($timetableEndTs-$semStartTs + 86400)/(7*86400)));
}

$nowWeek     = max(0,(int)floor((time()-$semStartTs)/(7*86400)));
$currentWeek = max(0, min(max(0, $totalWeeks - 1), intval($_GET['week'] ?? $nowWeek)));
$weekStartTs = $semStartTs + ($currentWeek*7*86400);
$weekEndTs   = $weekStartTs + (6*86400);

$weekOptions = [];
for ($w=0; $w<$totalWeeks; $w++) {
    $ws = $semStartTs + ($w*7*86400);
    $we = $ws + (6*86400);
    $weekOptions[$w] = 'Tuần '.($w+1).' [từ ngày '.date('d/m/Y',$ws).' đến ngày '.date('d/m/Y',$we).']';
}

$dayDates = [];
for ($d=2;$d<=7;$d++) $dayDates[$d] = $weekStartTs+(($d-2)*86400);
$dayDates[8] = $weekStartTs+(6*86400);

// Build grid [day][period] = subject
$timetable = [];
$subjectColors = [];
$palette = ['#1565c0','#2e7d32','#6a1b9a','#c62828','#e65100','#00695c','#4527a0','#283593','#558b2f','#ad1457'];
$ci = 0;

if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as &$sub) {
        $dsMap = [];
        if (!empty($sub['day_sessions'])) $dsMap = parseDaySessions($sub['day_sessions']);
        elseif (!empty($sub['schedule_data'])) $dsMap = parseScheduleData($sub['schedule_data']);
        $sub['_dsmap'] = $dsMap;

        if (!isset($subjectColors[$sub['section_code']])) {
            $subjectColors[$sub['section_code']] = $palette[$ci % count($palette)];
            $ci++;
        }

        $subStart = !empty($sub['_effective_start']) ? strtotime($sub['_effective_start']) : null;
        $subEnd   = !empty($sub['_effective_end'])   ? strtotime($sub['_effective_end'])   : null;
        $active = true;
        if ($subStart && $weekEndTs < $subStart) $active = false;
        if ($subEnd   && $weekStartTs > $subEnd) $active = false;
        $sub['_active'] = $active;
        if (!$active) continue;

        foreach (($sub['_date_sessions'] ?? []) as $date => $entries) {
            $ts = strtotime($date);
            if ($ts < $weekStartTs || $ts > $weekEndTs) continue;
            foreach ($entries as $entry) {
                if (!empty($entry['changed'])) {
                    $dsMap[(int)$entry['day']] = (string)$entry['session'];
                }
            }
        }

        foreach ($dsMap as $day => $sess) {
            if (!isset($SESSION_PERIODS[$sess])) continue;
            $classDate = isset($dayDates[$day]) ? date('Y-m-d', $dayDates[$day]) : null;
            if (!empty($sub['_date_sessions'])) {
                $dateEntries = array_filter($sub['_date_sessions'][$classDate] ?? [], fn($entry) => (int)$entry['day'] === (int)$day);
                if (!$dateEntries) continue;
                $sess = (string)reset($dateEntries)['session'];
                $sub['_display_room'] = reset($dateEntries)['room'] ?? $sub['room'];
                $sub['_changed_session'] = !empty(reset($dateEntries)['changed']);
            } elseif (!empty($sub['_class_dates']) && !in_array($classDate, $sub['_class_dates'], true)) {
                continue;
            }
            if (!isset($SESSION_PERIODS[$sess])) continue;
            [$pStart, $pEnd] = $SESSION_PERIODS[$sess];
            for ($p=$pStart; $p<=$pEnd; $p++) {
                $timetable[$day][$p] = $sub;
            }
        }
    }
    unset($sub);
}

// Class days cho mini calendar
$classDays = [];
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (empty($sub['_dsmap'])) continue;
        if (!empty($sub['_class_dates'])) {
            foreach (array_keys($sub['_date_sessions'] ?? []) ?: $sub['_class_dates'] as $classDate) $classDays[] = $classDate;
            continue;
        }
    }
}
$classDays = array_unique($classDays);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Thời khóa biểu — Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        :root{--navy:#0d2d6b;--gold:#f5a623;}
        body{background:#eef3f8;}
        .pw{max-width:1300px;margin:0 auto;padding:16px;}

        /* Controls bar */
        .ctrl-bar{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-bottom:12px;box-shadow:0 6px 18px rgba(13,45,107,.06);}
        .ctrl-bar .form-select{font-size:.84rem;height:36px;border-color:#ccc;}
        .sec-title{font-weight:700;font-size:.82rem;color:var(--navy);text-transform:uppercase;letter-spacing:.06em;display:flex;align-items:center;gap:7px;margin-bottom:12px;}
        .sec-title::before{content:'';width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;}

        /* TKB table */
        .tkb{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed;font-size:.78rem;background:#fff;}
        .tkb th,.tkb td{border-right:1px solid #d8e0ec;border-bottom:1px solid #d8e0ec;padding:0;}

        /* Header / footer row */
        .tkb thead th,.tkb tfoot th{
            background:var(--navy);color:#fff;
            text-align:center;padding:10px 4px;
            font-weight:600;font-size:.8rem;
            position:sticky;top:0;z-index:4;
        }
        .tkb thead th.th-today,.tkb tfoot th.th-today{background:#1565c0;}
        .tkb thead th.th-nav,.tkb tfoot th.th-nav{width:42px;cursor:pointer;}
        .tkb thead th.th-nav:hover,.tkb tfoot th.th-nav:hover{background:#1a4fa0;}
        .nav-btn{background:none;border:none;color:#fff;width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:10px 0;font-size:1.1rem;cursor:pointer;}

        /* Period label column */
        .td-period{
            width:62px;min-width:62px;text-align:center;font-size:.73rem;
            font-weight:600;color:#0d2d6b;padding:4px 3px;
            border-right:2px solid #d0d7e3;
            background:#f8f9fc;white-space:nowrap;position:sticky;left:0;z-index:3;
        }
        .td-period.s-sang{border-left:3px solid #1976d2;background:#f0f7ff;}
        .td-period.s-chieu{border-left:3px solid #f57c00;background:#fff8f0;}
        .td-period .session-time{display:block;font-size:.62rem;font-weight:500;color:#667085;margin-top:2px;}

        /* Subject cell */
        .td-sub{height:38px;padding:0;vertical-align:top;position:relative;background:#fff;}
        .td-sub.td-today{background:#fffde7 !important;}

        /* Subject card — spans multiple rows via absolute + height */
        .sub-card{
            position:absolute;left:2px;right:2px;top:2px;
            border-radius:7px;
            padding:6px 8px;
            font-size:.73rem;line-height:1.4;
            overflow:hidden;cursor:pointer;
            border-left:4px solid;
            transition:filter .15s, box-shadow .15s;
            z-index:2;
            box-shadow:0 4px 12px rgba(13,45,107,.12);
        }
        .sub-card:hover{filter:brightness(.94);box-shadow:0 3px 10px rgba(0,0,0,.15);}
        .sub-card .sn{font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.75rem;}
        .sub-card .si{font-size:.68rem;margin-top:3px;line-height:1.5;}
        .tkb-shell{background:#fff;border:1px solid #d0d7e3;border-radius:8px;overflow:auto;box-shadow:0 8px 22px rgba(13,45,107,.07);}
        .week-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px;}
        .week-summary .item{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:12px 14px;box-shadow:0 4px 12px rgba(13,45,107,.05);}
        .week-summary .label{font-size:.72rem;color:#667085;text-transform:uppercase;font-weight:700;}
        .week-summary .value{font-size:1.1rem;font-weight:800;color:var(--navy);}

        /* Timeline */
        .tl-wrap{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:16px 18px;margin-top:14px;}
        .tl-track{position:relative;height:36px;margin:0 20px 30px;}
        .tl-line{position:absolute;top:50%;left:0;right:0;height:3px;background:#1565c0;transform:translateY(-50%);}
        .tl-dot{position:absolute;transform:translateX(-50%);top:50%;margin-top:-7px;}
        .tl-dot .d{width:14px;height:14px;border-radius:50%;background:#1565c0;border:2px solid #fff;box-shadow:0 0 0 2px #1565c0;cursor:pointer;transition:all .2s;}
        .tl-dot.active .d{width:20px;height:20px;background:#fff;border:3px solid #1565c0;box-shadow:0 0 0 3px #1565c0;margin-top:-3px;}
        .tl-dot.future .d{background:#ccc;border-color:#ccc;box-shadow:0 0 0 2px #ccc;}
        .tl-dot .lb{position:absolute;top:20px;left:50%;transform:translateX(-50%);font-size:.63rem;white-space:nowrap;color:#666;margin-top:2px;}
        .tl-dot.active .lb{color:var(--navy);font-weight:700;}

        /* Mini calendar */
        .mc{max-width:400px;margin-top:8px;}
        .mc-hd{display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:10px;font-weight:600;font-size:.9rem;color:var(--navy);}
        .mc-hd button{background:none;border:none;color:var(--navy);font-size:1rem;cursor:pointer;padding:2px 8px;border-radius:4px;}
        .mc-hd button:hover{background:#e3f2fd;}
        .mc table{width:100%;border-collapse:collapse;}
        .mc th{text-align:center;font-size:.72rem;color:#888;padding:5px 0;font-weight:600;}
        .mc th.sun{color:#e53935;}
        .mc td{text-align:center;font-size:.82rem;padding:5px 3px;cursor:pointer;position:relative;border-radius:4px;}
        .mc td:hover:not(.om){background:#e3f2fd;border-radius:50%;}
        .mc td.td-today-cal{background:#1565c0;color:#fff;border-radius:50%;font-weight:700;}
        .mc td.hc::after{content:'';position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:#e53935;}
        .mc td.td-today-cal.hc::after{background:#fff;}
        .mc td.sun{color:#e53935;}
        .mc td.om{color:#ccc;cursor:default;}
        .mc td.iw{background:#e8f5e9;}
        .mc td.iw.td-today-cal{background:#1565c0;}

        @media print{.no-print{display:none!important;}body{background:#fff;}.pw{padding:0;}}
        @media (max-width: 768px){.week-summary{grid-template-columns:1fr}.pw{padding:10px}.tkb{min-width:920px}}
    </style>
</head>
<body>
<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy"><i class="bi bi-calendar3-week me-2"></i>Thời khóa biểu</span>
            </div>
            <div class="d-flex gap-2 no-print">
                <a href="/university/student/semester_timetable.php<?php echo $selectedSem ? '?semester_id=' . (int)$selectedSem : ''; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-range me-1"></i>Dạng học kỳ</a>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>In</button>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
        <div class="student-content">
        <div class="pw">

<!-- Controls -->
<div class="ctrl-bar no-print">
    <div class="sec-title">Thời khóa biểu dạng tuần</div>
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <select class="form-select" onchange="window.location.href='?semester_id='+this.value+'&week=0'">
                <?php foreach ($bySemester as $sid => $sd): ?>
                <option value="<?php echo $sid; ?>" <?php echo $sid==$selectedSem?'selected':''; ?>><?php echo htmlspecialchars($sd['info']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" disabled><option>TKB cá nhân</option></select>
        </div>
        <div class="col-md-4">
            <select class="form-select" onchange="window.location.href='?semester_id=<?php echo $selectedSem; ?>&week='+this.value">
                <?php foreach ($weekOptions as $w => $wl): ?>
                <option value="<?php echo $w; ?>" <?php echo $w==$currentWeek?'selected':''; ?>><?php echo htmlspecialchars($wl); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="prevWeek()" <?php echo $currentWeek<=0?'disabled':''; ?>>
                <i class="bi bi-chevron-left me-1"></i>Tuần trước
            </button>
            <button class="btn btn-navy btn-sm" onclick="goToday()" title="Về tuần hiện tại">
                <i class="bi bi-calendar-check"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="nextWeek()" <?php echo $currentWeek >= $totalWeeks-1 ? 'disabled' : ''; ?>>
                Tuần sau<i class="bi bi-chevron-right ms-1"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="In TKB">
                <i class="bi bi-printer"></i>
            </button>
        </div>
    </div>
</div>

<?php if (empty($bySemester) || !isset($bySemester[$selectedSem])): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
    Chưa đăng ký môn học nào. <a href="/university/student/register_subject.php">Đăng ký ngay</a>
</div></div>
<?php else: ?>

<!-- TKB Grid -->
<?php
$weekSubjectCodes = [];
foreach ($timetable as $dayRows) {
    foreach ($dayRows as $item) {
        $weekSubjectCodes[$item['section_code']] = true;
    }
}
$selectedSemesterSubjectCount = isset($bySemester[$selectedSem]) ? count($bySemester[$selectedSem]['subjects']) : 0;
?>
<div class="week-summary no-print">
    <div class="item"><div class="label">Học kỳ</div><div class="value"><?php echo htmlspecialchars($bySemester[$selectedSem]['info']); ?></div></div>
    <div class="item"><div class="label">Môn đã đăng ký</div><div class="value"><?php echo (int)$selectedSemesterSubjectCount; ?> môn</div></div>
    <div class="item"><div class="label">Có lịch trong tuần</div><div class="value"><?php echo count($weekSubjectCodes); ?> môn</div></div>
</div>
<div class="tkb-shell">
<table class="tkb">
<thead>
<tr>
    <th class="th-nav" onclick="prevWeek()"><button class="nav-btn"><i class="bi bi-chevron-left"></i></button></th>
    <?php foreach ($DAYS as $dn => $dl):
        $dt = $dayDates[$dn] ?? 0;
        $isToday = $dt && date('Y-m-d',$dt)==date('Y-m-d');
    ?>
    <th class="<?php echo $isToday?'th-today':''; ?>">
        <?php echo $dl; ?> (<?php echo $dt?date('d/m',$dt):''; ?>)
    </th>
    <?php endforeach; ?>
    <th class="th-nav" onclick="nextWeek()"><button class="nav-btn"><i class="bi bi-chevron-right"></i></button></th>
</tr>
</thead>
<tbody>
<?php
$sessBorderColor = ['sang'=>'#1976d2','chieu'=>'#f57c00'];
for ($period=1; $period<=10; $period++):
    $sess = $period<=5 ? 'sang' : 'chieu';
    $borderTop = in_array($period,[1,6,11]) ? 'border-top:2px solid '.$sessBorderColor[$sess].';' : '';
    $rowBg = in_array($period,[1,6,11]) ? 'background:#f5f8ff;' : '';
?>
<tr style="<?php echo $rowBg; ?>">
    <td class="td-period s-<?php echo $sess; ?>" style="<?php echo $borderTop; ?>">
        Tiết <?php echo $period; ?>
        <?php if (in_array($period, [1,6], true)): ?>
        <span class="session-time"><?php echo $SESSION_TIMES[$sess]; ?></span>
        <?php endif; ?>
    </td>
    <?php foreach ($DAYS as $dn => $dl):
        $dt = $dayDates[$dn] ?? 0;
        $isToday = $dt && date('Y-m-d',$dt)==date('Y-m-d');
        $sub = $timetable[$dn][$period] ?? null;
    ?>
    <td class="td-sub<?php echo $isToday?' td-today':''; ?>">
        <?php if ($sub):
            $color = $subjectColors[$sub['section_code']] ?? '#1565c0';
            $dsMap = $sub['_dsmap'];
            $subSess = $dsMap[$dn] ?? null;
            if ($subSess && isset($SESSION_PERIODS[$subSess])):
                [$pS,$pE] = $SESSION_PERIODS[$subSess];
                if ($period === $pS):
                    $cardH = ($pE - $pS + 1) * 38 - 4;
                    // Light bg from color
                    $r = hexdec(substr($color,1,2));
                    $g = hexdec(substr($color,3,2));
                    $b = hexdec(substr($color,5,2));
                    $bgStyle = "background:rgba($r,$g,$b,.1);border-left-color:$color;color:#1a1a2e;height:{$cardH}px;";
        ?>
            <div class="sub-card" style="<?php echo $bgStyle; ?>"
                 data-bs-toggle="tooltip"
                 title="<?php echo htmlspecialchars($sub['subject_name'].' | '.$sub['teacher_name'].' | '.($sub['_display_room'] ?? $sub['room'])); ?>">
                <div class="sn" style="color:<?php echo $color; ?>"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                <div class="si">
                    <?php echo htmlspecialchars($sub['section_code']); ?><br>
                    Nhóm: <?php echo htmlspecialchars($sub['section_code']); ?><br>
                    Phòng: <?php echo htmlspecialchars($sub['_display_room'] ?? $sub['room']); ?><?php echo !empty($sub['_changed_session']) ? ' (đổi lịch)' : ''; ?><br>
                    GV: <?php echo htmlspecialchars($sub['teacher_name']); ?>
                </div>
            </div>
        <?php endif; endif; endif; ?>
    </td>
    <?php endforeach; ?>
</tr>
<?php endfor; ?>
</tbody>
<tfoot>
<tr>
    <th class="th-nav" onclick="prevWeek()"><button class="nav-btn"><i class="bi bi-chevron-left"></i></button></th>
    <?php foreach ($DAYS as $dn => $dl):
        $dt = $dayDates[$dn] ?? 0;
        $isToday = $dt && date('Y-m-d',$dt)==date('Y-m-d');
    ?>
    <th class="<?php echo $isToday?'th-today':''; ?>"><?php echo $dl; ?> (<?php echo $dt?date('d/m',$dt):''; ?>)</th>
    <?php endforeach; ?>
    <th class="th-nav" onclick="nextWeek()"><button class="nav-btn"><i class="bi bi-chevron-right"></i></button></th>
</tr>
</tfoot>
</table>
</div>

<?php endif; ?>

<!-- Timeline + Mini Calendar -->
<div class="tl-wrap no-print">
    <div class="sec-title">Tiến trình học tập</div>
    <div class="tl-track" id="tlTrack">
        <div class="tl-line"></div>
        <div id="tlDots" style="position:absolute;top:0;left:0;right:0;height:100%;"></div>
    </div>
    <div class="mc" id="miniCal"></div>
</div>

</div><!-- /.pw -->
        </div><!-- /.student-content -->
        <div class="student-footer">&copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
const SEM_ID      = <?php echo $selectedSem; ?>;
const CUR_WEEK    = <?php echo $currentWeek; ?>;
const WEEK_S_TS   = <?php echo $weekStartTs; ?>;
const WEEK_E_TS   = <?php echo $weekEndTs; ?>;
const SEM_S_TS    = <?php echo $semStartTs; ?>;
const TOTAL_WEEKS = <?php echo $totalWeeks; ?>;
const SEMESTERS   = <?php
    $arr=[];
    foreach($bySemester as $sid=>$sd) $arr[]=['id'=>$sid,'label'=>$sd['info'],'start'=>$sd['sem_start']??''];
    echo json_encode($arr,JSON_UNESCAPED_UNICODE);
?>;
const CLASS_DAYS  = <?php echo json_encode(array_values($classDays)); ?>;

function prevWeek(){ if(CUR_WEEK>0) location.href='?semester_id='+SEM_ID+'&week='+(CUR_WEEK-1); }
function nextWeek(){ if(CUR_WEEK<TOTAL_WEEKS-1) location.href='?semester_id='+SEM_ID+'&week='+(CUR_WEEK+1); }
function goToday(){
    const nowWeek = Math.min(TOTAL_WEEKS-1, Math.max(0, Math.floor((Date.now()/1000 - SEM_S_TS) / (7*86400))));
    location.href='?semester_id='+SEM_ID+'&week='+nowWeek;
}

document.addEventListener('keydown',e=>{
    if(e.key==='ArrowLeft') prevWeek();
    if(e.key==='ArrowRight') nextWeek();
});

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));

// Timeline
(function(){
    const wrap = document.getElementById('tlDots');
    if(!wrap||!SEMESTERS.length) return;
    const n = SEMESTERS.length;
    const curIdx = SEMESTERS.findIndex(s=>s.id==SEM_ID);
    SEMESTERS.forEach((sem,i)=>{
        const pct = n===1 ? 50 : (i/(n-1))*100;
        const div = document.createElement('div');
        div.className = 'tl-dot'+(i===curIdx?' active':(i>curIdx?' future':''));
        div.style.left = pct+'%';
        const shortLabel = sem.label.replace(/ - Năm học .*/,'').replace('Học kỳ ','HK ');
        div.innerHTML = '<div class="d"></div><div class="lb">'+shortLabel+'</div>';
        div.addEventListener('click',()=>location.href='?semester_id='+sem.id+'&week=0');
        wrap.appendChild(div);
    });
})();

// Mini Calendar
(function(){
    const cal = document.getElementById('miniCal');
    if(!cal) return;
    const now = new Date(WEEK_S_TS*1000);
    let vy = now.getFullYear(), vm = now.getMonth();

    function render(){
        const today = new Date();
        const ws = new Date(WEEK_S_TS*1000); ws.setHours(0,0,0,0);
        const we = new Date(WEEK_E_TS*1000); we.setHours(23,59,59,999);
        const first = new Date(vy,vm,1);
        const last  = new Date(vy,vm+1,0);
        let dow = first.getDay(); if(dow===0) dow=7;
        const offset = dow-1;
        const mName = new Intl.DateTimeFormat('vi-VN',{month:'long'}).format(first);
        let h = '<div class="mc-hd">';
        h += '<button onclick="mcPrev()"><i class="bi bi-chevron-left"></i></button>';
        h += '<span>'+mName.charAt(0).toUpperCase()+mName.slice(1)+' &nbsp; '+vy+'</span>';
        h += '<button onclick="mcNext()"><i class="bi bi-chevron-right"></i></button>';
        h += '</div><table><thead><tr>';
        ['T2','T3','T4','T5','T6','T7','<span class="sun">CN</span>'].forEach((d,i)=>h+='<th'+(i===6?' class="sun"':'')+'>'+d+'</th>');
        h += '</tr></thead><tbody>';
        let day = 1-offset;
        for(let row=0;row<6;row++){
            h+='<tr>';
            for(let col=0;col<7;col++,day++){
                const d = new Date(vy,vm,day);
                const isOther = day<1||day>last.getDate();
                const isToday = !isOther && d.toDateString()===today.toDateString();
                const inWeek  = !isOther && d>=ws && d<=we;
                const ds = d.toISOString().slice(0,10);
                const hasCls = CLASS_DAYS.includes(ds);
                let cls=[];
                if(isOther) cls.push('om');
                if(isToday) cls.push('td-today-cal');
                if(inWeek&&!isOther) cls.push('iw');
                if(hasCls&&!isOther) cls.push('hc');
                if(col===6) cls.push('sun');
                const dispDay = isOther ? (day<1?new Date(vy,vm,day).getDate():day-last.getDate()) : day;
                h+='<td class="'+cls.join(' ')+'">'+dispDay+'</td>';
            }
            h+='</tr>';
            if(day>last.getDate()&&row>=4) break;
        }
        h+='</tbody></table>';
        cal.innerHTML=h;
    }
    window.mcPrev=()=>{vm--;if(vm<0){vm=11;vy--;}render();};
    window.mcNext=()=>{vm++;if(vm>11){vm=0;vy++;}render();};
    render();
})();
</script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>

