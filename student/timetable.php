<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Seed schedule data
$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}

// Lay tat ca mon da dang ky
$stmt = $conn->prepare("
    SELECT ss.id as ss_id, cs.section_code, cs.schedule_text, cs.schedule_data,
           cs.room, cs.day_sessions, cs.start_date, cs.end_date,
           s.subject_name, s.credits, s.subject_code,
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

// Nhom theo hoc ky
$bySemester = [];
foreach ($allSubjects as $sub) {
    $key = $sub['semester_id'];
    $bySemester[$key]['info']      = $sub['semester_name'] . ' - Nam hoc ' . $sub['school_year'];
    $bySemester[$key]['sem_start'] = $sub['sem_start'];
    $bySemester[$key]['subjects'][] = $sub;
}

$selectedSem = intval($_GET['semester_id'] ?? (array_key_first($bySemester) ?? 0));

// Parse day_sessions
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

// Session -> period range
$SESSION_PERIODS = ['sang'=>[1,5],'chieu'=>[6,10],'toi'=>[11,15]];
$SESSION_LABEL   = ['sang'=>'Sang','chieu'=>'Chieu','toi'=>'Toi'];
$DAYS = [2=>'Thu 2',3=>'Thu 3',4=>'Thu 4',5=>'Thu 5',6=>'Thu 6',7=>'Thu 7',8=>'Chu Nhat'];
$DAYS_SHORT = [2=>'T2',3=>'T3',4=>'T4',5=>'T5',6=>'T6',7=>'T7',8=>'CN'];

// Tinh tuan
$semStartTs = null;
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (!empty($sub['start_date'])) {
            $ts = strtotime($sub['start_date']);
            if ($semStartTs===null || $ts<$semStartTs) $semStartTs=$ts;
        }
    }
}
if (!$semStartTs) {
    $semStartTs = isset($bySemester[$selectedSem]['sem_start']) && $bySemester[$selectedSem]['sem_start']
        ? strtotime($bySemester[$selectedSem]['sem_start'])
        : strtotime('monday this week');
}
$dow = (int)date('N', $semStartTs);
if ($dow!=1) $semStartTs = strtotime('last monday', $semStartTs);

$nowWeek     = max(0,(int)floor((time()-$semStartTs)/(7*86400)));
$currentWeek = max(0,intval($_GET['week'] ?? $nowWeek));
$weekStartTs = $semStartTs + ($currentWeek*7*86400);
$weekEndTs   = $weekStartTs + (6*86400);

// Tinh so tuan tong
$totalWeeks = 0;
if (isset($bySemester[$selectedSem])) {
    $maxEnd = null;
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (!empty($sub['end_date'])) {
            $ts = strtotime($sub['end_date']);
            if ($maxEnd===null || $ts>$maxEnd) $maxEnd=$ts;
        }
    }
    if ($maxEnd) $totalWeeks = max(1,(int)ceil(($maxEnd-$semStartTs)/(7*86400)));
    else $totalWeeks = 20;
}

// Danh sach tuan cho dropdown
$weekOptions = [];
for ($w=0; $w<=$totalWeeks; $w++) {
    $ws = $semStartTs + ($w*7*86400);
    $we = $ws + (6*86400);
    $weekOptions[$w] = 'Tuan '.($w+1).' [tu ngay '.date('d/m/Y',$ws).' den ngay '.date('d/m/Y',$we).']';
}

// Day dates
$dayDates = [];
for ($d=2;$d<=7;$d++) $dayDates[$d] = $weekStartTs+(($d-2)*86400);
$dayDates[8] = $weekStartTs+(6*86400);

// Build timetable grid [day][period] = subject
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

        $subStart = !empty($sub['start_date']) ? strtotime($sub['start_date']) : null;
        $subEnd   = !empty($sub['end_date'])   ? strtotime($sub['end_date'])   : null;
        $active = true;
        if ($subStart && $weekEndTs < $subStart) $active = false;
        if ($subEnd   && $weekStartTs > $subEnd) $active = false;
        $sub['_active'] = $active;
        if (!$active) continue;

        foreach ($dsMap as $day => $sess) {
            if (!isset($SESSION_PERIODS[$sess])) continue;
            [$pStart, $pEnd] = $SESSION_PERIODS[$sess];
            for ($p=$pStart; $p<=$pEnd; $p++) {
                $timetable[$day][$p] = $sub;
            }
        }
    }
    unset($sub);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thoi khoa bieu - Sinh vien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        :root{--navy:#0d2d6b;--gold:#f5a623;}
        body{background:#f5f6fa;}
        .page-wrap{max-width:1300px;margin:0 auto;padding:16px;}

        /* Header controls */
        .tkb-controls{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 18px;margin-bottom:12px;}
        .tkb-controls .form-select{border-radius:6px;border:1px solid #ccc;font-size:.85rem;height:36px;}
        .tkb-title{font-weight:700;font-size:1rem;color:var(--navy);display:flex;align-items:center;gap:8px;margin-bottom:12px;}
        .tkb-title i{color:var(--gold);}

        /* Grid */
        .tkb-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:.78rem;}
        .tkb-table th,.tkb-table td{border:1px solid #d0d7e3;padding:0;}
        .tkb-table thead th{background:var(--navy);color:#fff;text-align:center;padding:8px 4px;font-weight:600;font-size:.8rem;}
        .tkb-table thead th.today-col{background:#1565c0;}
        .tkb-table thead th.nav-col{background:var(--navy);width:36px;cursor:pointer;}
        .tkb-table thead th.nav-col:hover{background:#1a4fa0;}

        /* Period column */
        .period-cell{background:#f0f4ff;text-align:center;font-size:.72rem;color:#555;padding:4px 2px;width:52px;border-right:2px solid #c5cfe8;}
        .period-cell.period-group-start{border-top:2px solid #b0bcd8;}

        /* Subject cell */
        .sub-cell{padding:0;vertical-align:top;height:28px;position:relative;}
        .sub-card{
            position:absolute;inset:1px;
            border-radius:4px;
            padding:4px 6px;
            font-size:.72rem;
            line-height:1.3;
            overflow:hidden;
            cursor:pointer;
            border-left:3px solid;
            transition:opacity .15s;
        }
        .sub-card:hover{opacity:.85;}
        .sub-card .sn{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .sub-card .si{color:#444;font-size:.68rem;margin-top:1px;}
        .sub-card.span-top{border-radius:4px 4px 0 0;}
        .sub-card.span-mid{border-radius:0;border-top:none;}
        .sub-card.span-bot{border-radius:0 0 4px 4px;border-top:none;}

        /* Empty cell */
        .empty-cell{background:#fafbff;}
        .today-cell{background:#fffde7 !important;}

        /* Nav arrows in table */
        .nav-arrow{background:none;border:none;color:#fff;font-size:1rem;width:100%;height:100%;display:flex;align-items:center;justify-content:center;cursor:pointer;}

        /* Bottom nav */
        .week-nav{display:flex;align-items:center;gap:8px;margin-top:8px;}
        .week-nav .btn{font-size:.8rem;padding:4px 12px;}

        /* Timeline */
        .timeline-wrap{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 18px;margin-top:16px;}
        .timeline-title{font-weight:700;font-size:.85rem;color:var(--navy);display:flex;align-items:center;gap:6px;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid var(--navy);}
        .timeline-title i{color:var(--gold);}
        .tl-track{position:relative;height:32px;margin:0 8px 20px;}
        .tl-line{position:absolute;top:50%;left:0;right:0;height:3px;background:#1565c0;transform:translateY(-50%);}
        .tl-dots{position:absolute;top:0;left:0;right:0;height:100%;display:flex;align-items:center;}
        .tl-dot{position:absolute;transform:translateX(-50%);}
        .tl-dot .dot{width:14px;height:14px;border-radius:50%;background:#1565c0;border:2px solid #fff;box-shadow:0 0 0 2px #1565c0;cursor:pointer;transition:all .2s;}
        .tl-dot.active .dot{width:18px;height:18px;background:#fff;border:3px solid #1565c0;box-shadow:0 0 0 3px #1565c0;}
        .tl-dot.future .dot{background:#ccc;border-color:#ccc;box-shadow:0 0 0 2px #ccc;}
        .tl-dot .lbl{position:absolute;top:18px;left:50%;transform:translateX(-50%);font-size:.62rem;white-space:nowrap;color:#555;}
        .tl-dot.active .lbl{color:var(--navy);font-weight:700;}

        /* Mini calendar */
        .mini-cal{margin-top:8px;}
        .mini-cal-header{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:10px;font-weight:600;font-size:.9rem;}
        .mini-cal-header button{background:none;border:none;color:var(--navy);font-size:1rem;cursor:pointer;padding:2px 6px;}
        .mini-cal table{width:100%;border-collapse:collapse;}
        .mini-cal th{text-align:center;font-size:.72rem;color:#888;padding:4px 0;font-weight:600;}
        .mini-cal th.sun{color:#e53935;}
        .mini-cal td{text-align:center;font-size:.8rem;padding:5px 2px;cursor:pointer;border-radius:50%;width:32px;height:32px;position:relative;}
        .mini-cal td:hover{background:#e3f2fd;}
        .mini-cal td.today{background:#1565c0;color:#fff;border-radius:50%;}
        .mini-cal td.has-class::after{content:'';position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:#e53935;}
        .mini-cal td.today.has-class::after{background:#fff;}
        .mini-cal td.sun-col{color:#e53935;}
        .mini-cal td.other-month{color:#ccc;}
        .mini-cal td.in-week{background:#e8f5e9;border-radius:0;}
        .mini-cal td.in-week:first-child{border-radius:50% 0 0 50%;}
        .mini-cal td.in-week:last-child{border-radius:0 50% 50% 0;}

        @media print{
            .no-print{display:none!important;}
            body{background:#fff;}
            .page-wrap{padding:0;}
        }
    </style>
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="sidebar-brand-text"><div>Cong Sinh vien</div><small><?php echo htmlspecialchars($student['student_code']); ?></small></div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/student/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tong quan</a>
            <a href="/university/student/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Ho so ca nhan</a>
            <a href="/university/student/register_subject.php" class="sidebar-link"><i class="bi bi-journal-plus"></i> Dang ky hoc phan</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Hoc phan cua toi</a>
            <a href="/university/student/timetable.php" class="sidebar-link active"><i class="bi bi-calendar3-week"></i> Thoi khoa bieu</a>
            <a href="/university/student/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lich thi cuoi ky</a>
            <a href="/university/student/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Ket qua hoc tap</a>
            <a href="/university/student/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Danh gia giang vien</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chu</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Dang xuat</a>
        </nav>
    </div>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy"><i class="bi bi-calendar3-week me-2"></i>Thoi khoa bieu</span>
            </div>
            <div class="d-flex gap-2 no-print">
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>In</button>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
        <div class="student-content">
        <div class="page-wrap">

<!-- Controls -->
<div class="tkb-controls no-print">
    <div class="tkb-title"><i class="bi bi-circle-fill" style="font-size:.5rem;"></i> THOI KHOA BIEU DANG TUAN</div>
    <div class="row g-2">
        <div class="col-md-4">
            <select class="form-select" id="semSelect" onchange="changeSem(this.value)">
                <?php foreach ($bySemester as $sid => $sd): ?>
                <option value="<?php echo $sid; ?>" <?php echo $sid==$selectedSem?'selected':''; ?>><?php echo htmlspecialchars($sd['info']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" disabled>
                <option>Thoi khoa bieu ca nhan</option>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select" id="weekSelect" onchange="changeWeek(this.value)">
                <?php foreach ($weekOptions as $w => $wLabel): ?>
                <option value="<?php echo $w; ?>" <?php echo $w==$currentWeek?'selected':''; ?>><?php echo htmlspecialchars($wLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="window.print()" style="height:36px;">
                <i class="bi bi-printer me-1"></i>In
            </button>
        </div>
    </div>
</div>

<?php if (empty($bySemester) || !isset($bySemester[$selectedSem])): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
    Chua dang ky mon hoc nao. <a href="/university/student/register_subject.php">Dang ky ngay</a>
</div></div>
<?php else: ?>

<!-- TKB Grid -->
<div style="background:#fff;border:1px solid #d0d7e3;border-radius:8px;overflow:hidden;">
<div class="table-responsive">
<table class="tkb-table">
<thead>
<tr>
    <th class="nav-col" onclick="prevWeek()"><button class="nav-arrow"><i class="bi bi-chevron-left"></i></button></th>
    <?php foreach ($DAYS as $dayNum => $dayName):
        $dayTs = $dayDates[$dayNum] ?? 0;
        $isToday = $dayTs && date('Y-m-d',$dayTs)==date('Y-m-d');
    ?>
    <th class="<?php echo $isToday?'today-col':''; ?>">
        <?php echo $dayName; ?> (<?php echo $dayTs?date('d/m',$dayTs):''; ?>)
    </th>
    <?php endforeach; ?>
    <th class="nav-col" onclick="nextWeek()"><button class="nav-arrow"><i class="bi bi-chevron-right"></i></button></th>
</tr>
</thead>
<tbody>
<?php for ($period=1; $period<=16; $period++):
    // Xac dinh session cua tiet nay
    $sess = 'sang';
    if ($period>=6 && $period<=10) $sess='chieu';
    elseif ($period>=11) $sess='toi';
    $sessColors = ['sang'=>'#e3f2fd','chieu'=>'#fff3e0','toi'=>'#f3e5f5'];
    $sessBorder = ['sang'=>'#1976d2','chieu'=>'#f57c00','toi'=>'#7b1fa2'];
?>
<tr>
    <td class="period-cell <?php echo in_array($period,[1,6,11])?'period-group-start':''; ?>"
        style="<?php echo in_array($period,[1,6,11])?'border-top:2px solid '.$sessBorder[$sess].';':''; ?>">
        <div style="font-weight:600;color:<?php echo $sessBorder[$sess]; ?>">Tiet <?php echo $period; ?></div>
    </td>
    <?php foreach ($DAYS as $dayNum => $dayName):
        $dayTs = $dayDates[$dayNum] ?? 0;
        $isToday = $dayTs && date('Y-m-d',$dayTs)==date('Y-m-d');
        $sub = $timetable[$dayNum][$period] ?? null;
        $cellClass = $isToday ? 'today-cell' : 'empty-cell';
    ?>
    <td class="sub-cell <?php echo $cellClass; ?>">
        <?php if ($sub):
            $color = $subjectColors[$sub['section_code']] ?? '#1565c0';
            $dsMap = $sub['_dsmap'];
            $subSess = $dsMap[$dayNum] ?? null;
            if ($subSess && isset($SESSION_PERIODS[$subSess])):
                [$pS,$pE] = $SESSION_PERIODS[$subSess];
                $isFirst = ($period==$pS);
                $isLast  = ($period==$pE);
                $spanClass = $isFirst ? 'span-top' : ($isLast ? 'span-bot' : 'span-mid');
                if ($isFirst):
        ?>
            <div class="sub-card <?php echo $spanClass; ?>"
                 style="background:<?php echo $color; ?>18;border-left-color:<?php echo $color; ?>;height:<?php echo ($pE-$pS+1)*28-2; ?>px;z-index:2;"
                 data-bs-toggle="tooltip"
                 title="<?php echo htmlspecialchars($sub['subject_name'].' | '.$sub['teacher_name'].' | '.$sub['room']); ?>">
                <div class="sn" style="color:<?php echo $color; ?>"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                <div class="si">
                    <?php echo htmlspecialchars($sub['section_code']); ?><br>
                    Nhom: <?php echo htmlspecialchars($sub['section_code']); ?><br>
                    Phong: <?php echo htmlspecialchars($sub['room']); ?><br>
                    GV: <?php echo htmlspecialchars($sub['teacher_name']); ?>
                </div>
            </div>
        <?php endif; endif; endif; ?>
    </td>
    <?php endforeach; ?>
    <td class="period-cell" style="<?php echo in_array($period,[1,6,11])?'border-top:2px solid '.$sessBorder[$sess].';':''; ?>">
        <div style="font-weight:600;color:<?php echo $sessBorder[$sess]; ?>">Tiet <?php echo $period; ?></div>
    </td>
</tr>
<?php endfor; ?>
<tfoot>
<tr>
    <th class="nav-col" onclick="prevWeek()"><button class="nav-arrow"><i class="bi bi-chevron-left"></i></button></th>
    <?php foreach ($DAYS as $dayNum => $dayName):
        $dayTs = $dayDates[$dayNum] ?? 0;
        $isToday = $dayTs && date('Y-m-d',$dayTs)==date('Y-m-d');
    ?>
    <th class="<?php echo $isToday?'today-col':''; ?>"><?php echo $dayName; ?> (<?php echo $dayTs?date('d/m',$dayTs):''; ?>)</th>
    <?php endforeach; ?>
    <th class="nav-col" onclick="nextWeek()"><button class="nav-arrow"><i class="bi bi-chevron-right"></i></button></th>
</tr>
</tfoot>
</table>
</div>
</div>

<?php endif; ?>

<!-- Timeline hoc ky -->
<div class="timeline-wrap no-print">
    <div class="timeline-title"><i class="bi bi-circle-fill" style="font-size:.5rem;"></i> TIEN TRINH HOC TAP</div>

    <?php if (!empty($bySemester)): ?>
    <div class="tl-track" id="tlTrack">
        <div class="tl-line"></div>
        <div class="tl-dots" id="tlDots"></div>
    </div>
    <?php endif; ?>

    <!-- Mini calendar -->
    <div class="mini-cal" id="miniCal"></div>
</div>

</div><!-- /.page-wrap -->
        </div><!-- /.student-content -->
        <div class="student-footer">&copy; <?php echo date('Y'); ?> Truong Dai hoc Thu Dau Mot</div>
    </div><!-- /.student-main -->
</div><!-- /.student-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
const SEMESTER_ID = <?php echo $selectedSem; ?>;
const CURRENT_WEEK = <?php echo $currentWeek; ?>;
const WEEK_START_TS = <?php echo $weekStartTs; ?>;
const WEEK_END_TS   = <?php echo $weekEndTs; ?>;
const SEM_START_TS  = <?php echo $semStartTs; ?>;
const TOTAL_WEEKS   = <?php echo $totalWeeks; ?>;

// Semesters data for timeline
const SEMESTERS = <?php
$semArr = [];
foreach ($bySemester as $sid => $sd) {
    $semArr[] = ['id'=>$sid,'label'=>$sd['info'],'start'=>$sd['sem_start']??''];
}
echo json_encode($semArr, JSON_UNESCAPED_UNICODE);
?>;

// Class dates (days that have subjects)
const CLASS_DAYS = <?php
$classDays = [];
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (empty($sub['_dsmap'])) continue;
        $start = !empty($sub['start_date']) ? strtotime($sub['start_date']) : $semStartTs;
        $end   = !empty($sub['end_date'])   ? strtotime($sub['end_date'])   : ($semStartTs + $totalWeeks*7*86400);
        foreach ($sub['_dsmap'] as $day => $sess) {
            // day: 2=Mon...8=Sun, PHP dow: 1=Mon...7=Sun
            $phpDow = $day <= 7 ? $day - 1 : 0; // 0=Sun
            $cur = $start;
            while ($cur <= $end) {
                $curDow = (int)date('N', $cur); // 1=Mon...7=Sun
                $targetDow = $day <= 7 ? $day - 1 : 7; // 1-7
                if ($day == 8) $targetDow = 7; // Sun
                else $targetDow = $day - 1;
                if ($curDow == ($day <= 7 ? $day : 7)) {
                    $classDays[] = date('Y-m-d', $cur);
                }
                $cur += 86400;
            }
        }
    }
}
echo json_encode(array_unique($classDays));
?>;

// Navigation
function prevWeek() {
    if (CURRENT_WEEK > 0) {
        window.location.href = '?semester_id='+SEMESTER_ID+'&week='+(CURRENT_WEEK-1);
    }
}
function nextWeek() {
    window.location.href = '?semester_id='+SEMESTER_ID+'&week='+(CURRENT_WEEK+1);
}
function changeSem(val) {
    window.location.href = '?semester_id='+val+'&week=0';
}
function changeWeek(val) {
    window.location.href = '?semester_id='+SEMESTER_ID+'&week='+val;
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft') prevWeek();
    if (e.key === 'ArrowRight') nextWeek();
});

// Tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

// ── Timeline ──────────────────────────────────────────────────────────────────
(function buildTimeline() {
    const track = document.getElementById('tlDots');
    if (!track || !SEMESTERS.length) return;
    const total = SEMESTERS.length;
    SEMESTERS.forEach((sem, i) => {
        const pct = total === 1 ? 50 : (i / (total - 1)) * 100;
        const dot = document.createElement('div');
        dot.className = 'tl-dot' + (sem.id == SEMESTER_ID ? ' active' : (i > SEMESTERS.findIndex(s=>s.id==SEMESTER_ID) ? ' future' : ''));
        dot.style.left = pct + '%';
        dot.innerHTML = '<div class="dot"></div><div class="lbl">' + sem.label.replace(/ - Nam hoc .*/,'').replace('Hoc ky ','HK ') + '</div>';
        dot.addEventListener('click', () => { window.location.href = '?semester_id='+sem.id+'&week=0'; });
        track.appendChild(dot);
    });
})();

// ── Mini Calendar ─────────────────────────────────────────────────────────────
(function buildCalendar() {
    const cal = document.getElementById('miniCal');
    if (!cal) return;

    let viewYear  = new Date(WEEK_START_TS * 1000).getFullYear();
    let viewMonth = new Date(WEEK_START_TS * 1000).getMonth(); // 0-based

    function render() {
        const today = new Date();
        const weekStart = new Date(WEEK_START_TS * 1000);
        const weekEnd   = new Date(WEEK_END_TS   * 1000);
        weekStart.setHours(0,0,0,0); weekEnd.setHours(23,59,59,999);

        const monthName = new Intl.DateTimeFormat('vi-VN',{month:'long'}).format(new Date(viewYear,viewMonth,1));
        const firstDay  = new Date(viewYear, viewMonth, 1);
        const lastDay   = new Date(viewYear, viewMonth+1, 0);
        // Start from Monday
        let startDow = firstDay.getDay(); // 0=Sun
        if (startDow === 0) startDow = 7;
        const startOffset = startDow - 1; // days before first of month

        let html = '<div class="mini-cal-header">';
        html += '<button onclick="calPrev()"><i class="bi bi-chevron-left"></i></button>';
        html += '<span>' + monthName.charAt(0).toUpperCase()+monthName.slice(1) + ' &nbsp; ' + viewYear + '</span>';
        html += '<button onclick="calNext()"><i class="bi bi-chevron-right"></i></button>';
        html += '</div>';
        html += '<table><thead><tr>';
        ['T2','T3','T4','T5','T6','T7','<span class="sun">CN</span>'].forEach(d => { html += '<th'+(d.includes('CN')?' class="sun"':'')+'>'+d+'</th>'; });
        html += '</tr></thead><tbody>';

        let day = 1 - startOffset;
        for (let row=0; row<6; row++) {
            html += '<tr>';
            for (let col=0; col<7; col++, day++) {
                const d = new Date(viewYear, viewMonth, day);
                const isOther = day < 1 || day > lastDay.getDate();
                const isToday = !isOther && d.toDateString()===today.toDateString();
                const inWeek  = !isOther && d >= weekStart && d <= weekEnd;
                const dateStr = d.toISOString().slice(0,10);
                const hasClass = CLASS_DAYS.includes(dateStr);
                let cls = [];
                if (isOther) cls.push('other-month');
                if (isToday) cls.push('today');
                if (inWeek && !isOther) cls.push('in-week');
                if (hasClass && !isOther) cls.push('has-class');
                if (col===6) cls.push('sun-col');
                html += '<td class="'+cls.join(' ')+'">'+(isOther ? (day<1?new Date(viewYear,viewMonth,day).getDate():day-lastDay.getDate()) : day)+'</td>';
            }
            html += '</tr>';
            if (day > lastDay.getDate() && row >= 4) break;
        }
        html += '</tbody></table>';
        cal.innerHTML = html;
    }

    window.calPrev = function() { viewMonth--; if(viewMonth<0){viewMonth=11;viewYear--;} render(); };
    window.calNext = function() { viewMonth++; if(viewMonth>11){viewMonth=0;viewYear++;} render(); };
    render();
})();
</script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
