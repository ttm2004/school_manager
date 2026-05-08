<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}

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

$bySemester = [];
foreach ($allSubjects as $sub) {
    $key = $sub['semester_id'];
    $bySemester[$key]['info']      = $sub['semester_name'] . ' - Năm học ' . $sub['school_year'];
    $bySemester[$key]['sem_start'] = $sub['sem_start'];
    $bySemester[$key]['subjects'][] = $sub;
}

$selectedSem = intval($_GET['semester_id'] ?? (array_key_first($bySemester) ?? 0));

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
$SESSION_PERIODS = ['sang'=>[1,5],'chieu'=>[6,10],'toi'=>[11,15]];
$DAYS = [2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'Chủ Nhật'];

// Tính tuần
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

$totalWeeks = 20;
if (isset($bySemester[$selectedSem])) {
    $maxEnd = null;
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (!empty($sub['end_date'])) {
            $ts = strtotime($sub['end_date']);
            if ($maxEnd===null || $ts>$maxEnd) $maxEnd=$ts;
        }
    }
    if ($maxEnd) $totalWeeks = max(1,(int)ceil(($maxEnd-$semStartTs)/(7*86400)));
}

$nowWeek     = max(0,(int)floor((time()-$semStartTs)/(7*86400)));
$currentWeek = max(0,intval($_GET['week'] ?? $nowWeek));
$weekStartTs = $semStartTs + ($currentWeek*7*86400);
$weekEndTs   = $weekStartTs + (6*86400);

$weekOptions = [];
for ($w=0; $w<=$totalWeeks; $w++) {
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

// Class days cho mini calendar
$classDays = [];
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['subjects'] as $sub) {
        if (empty($sub['_dsmap'])) continue;
        $start = !empty($sub['start_date']) ? strtotime($sub['start_date']) : $semStartTs;
        $end   = !empty($sub['end_date'])   ? strtotime($sub['end_date'])   : ($semStartTs + $totalWeeks*7*86400);
        foreach ($sub['_dsmap'] as $day => $sess) {
            $phpDow = $day <= 7 ? $day : 7;
            $cur = $start;
            while ($cur <= $end) {
                if ((int)date('N',$cur) == $phpDow) $classDays[] = date('Y-m-d',$cur);
                $cur += 86400;
            }
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
    <title>Thời khóa biểu — Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        :root{--navy:#0d2d6b;--gold:#f5a623;}
        body{background:#f5f6fa;}
        .pw{max-width:1300px;margin:0 auto;padding:16px;}

        /* Controls bar */
        .ctrl-bar{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-bottom:10px;}
        .ctrl-bar .form-select{font-size:.84rem;height:36px;border-color:#ccc;}
        .sec-title{font-weight:700;font-size:.82rem;color:var(--navy);text-transform:uppercase;letter-spacing:.06em;display:flex;align-items:center;gap:7px;margin-bottom:12px;}
        .sec-title::before{content:'';width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;}

        /* TKB table */
        .tkb{width:100%;border-collapse:collapse;table-layout:fixed;font-size:.78rem;}
        .tkb th,.tkb td{border:1px solid #d0d7e3;padding:0;}

        /* Header / footer row */
        .tkb thead th,.tkb tfoot th{
            background:var(--navy);color:#fff;
            text-align:center;padding:9px 4px;
            font-weight:600;font-size:.79rem;
        }
        .tkb thead th.th-today,.tkb tfoot th.th-today{background:#1565c0;}
        .tkb thead th.th-nav,.tkb tfoot th.th-nav{width:34px;cursor:pointer;}
        .tkb thead th.th-nav:hover,.tkb tfoot th.th-nav:hover{background:#1a4fa0;}
        .nav-btn{background:none;border:none;color:#fff;width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:8px 0;font-size:1rem;cursor:pointer;}

        /* Period label column */
        .td-period{
            width:54px;text-align:center;font-size:.72rem;
            font-weight:600;color:#0d2d6b;padding:3px 2px;
            border-right:2px solid #d0d7e3;
            background:#f8f9fc;
        }
        .td-period.s-sang{border-left:3px solid #1976d2;background:#f0f7ff;}
        .td-period.s-chieu{border-left:3px solid #f57c00;background:#fff8f0;}
        .td-period.s-toi{border-left:3px solid #7b1fa2;background:#fdf4ff;}

        /* Subject cell */
        .td-sub{height:30px;padding:0;vertical-align:top;position:relative;}
        .td-sub.td-today{background:#fffde7;}
        .td-sub.td-empty{background:#fafbff;}

        /* Subject card — spans multiple rows via absolute + height */
        .sub-card{
            position:absolute;left:1px;right:1px;top:1px;
            border-radius:4px;
            padding:5px 7px;
            font-size:.72rem;line-height:1.35;
            overflow:hidden;cursor:pointer;
            border-left:3px solid;
            transition:filter .15s;
            z-index:2;
        }
        .sub-card:hover{filter:brightness(.92);}
        .sub-card .sn{font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .sub-card .si{font-size:.67rem;margin-top:2px;opacity:.82;}

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
    </style>
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="sidebar-brand-text">
                <div>Cổng Sinh viên</div>
                <small><?php echo htmlspecialchars($student['student_code']); ?></small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/student/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a href="/university/student/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Hồ sơ cá nhân</a>
            <a href="/university/student/register_subject.php" class="sidebar-link"><i class="bi bi-journal-plus"></i> Đăng ký học phần</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Học phần của tôi</a>
            <a href="/university/student/timetable.php" class="sidebar-link active"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/student/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
            <a href="/university/student/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Kết quả học tập</a>
            <a href="/university/student/tuition.php" class="sidebar-link"><i class="bi bi-cash-coin"></i> Học phí</a>
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
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>In</button>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
        <div class="student-content">
        <div class="pw">

<!-- Controls -->
<div class="ctrl-bar no-print">
    <div class="sec-title">Thời khóa biểu dạng tuần</div>
    <div class="row g-2">
        <div class="col-md-4">
            <select class="form-select" onchange="window.location.href='?semester_id='+this.value+'&week=0'">
                <?php foreach ($bySemester as $sid => $sd): ?>
                <option value="<?php echo $sid; ?>" <?php echo $sid==$selectedSem?'selected':''; ?>><?php echo htmlspecialchars($sd['info']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" disabled><option>Thời khóa biểu cá nhân</option></select>
        </div>
        <div class="col-md-4">
            <select class="form-select" onchange="window.location.href='?semester_id=<?php echo $selectedSem; ?>&week='+this.value">
                <?php foreach ($weekOptions as $w => $wl): ?>
                <option value="<?php echo $w; ?>" <?php echo $w==$currentWeek?'selected':''; ?>><?php echo htmlspecialchars($wl); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button class="btn btn-outline-secondary btn-sm w-100" onclick="window.print()" style="height:36px;"><i class="bi bi-printer me-1"></i>In</button>
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
<div style="background:#fff;border:1px solid #d0d7e3;border-radius:8px;overflow:hidden;">
<div class="table-responsive">
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
</tr>
</thead>
<tbody>
<?php
$sessBorderColor = ['sang'=>'#1976d2','chieu'=>'#f57c00','toi'=>'#7b1fa2'];
for ($period=1; $period<=16; $period++):
    $sess = $period<=5 ? 'sang' : ($period<=10 ? 'chieu' : 'toi');
    $borderTop = in_array($period,[1,6,11]) ? 'border-top:2px solid '.$sessBorderColor[$sess].';' : '';
?>
<tr>
    <td class="td-period s-<?php echo $sess; ?>" style="<?php echo $borderTop; ?>">
        Tiết <?php echo $period; ?>
    </td>
    <?php foreach ($DAYS as $dn => $dl):
        $dt = $dayDates[$dn] ?? 0;
        $isToday = $dt && date('Y-m-d',$dt)==date('Y-m-d');
        $sub = $timetable[$dn][$period] ?? null;
    ?>
    <td class="td-sub <?php echo $isToday?'td-today':'td-empty'; ?>">
        <?php if ($sub):
            $color = $subjectColors[$sub['section_code']] ?? '#1565c0';
            $dsMap = $sub['_dsmap'];
            $subSess = $dsMap[$dn] ?? null;
            if ($subSess && isset($SESSION_PERIODS[$subSess])):
                [$pS,$pE] = $SESSION_PERIODS[$subSess];
                if ($period === $pS):
                    $cardH = ($pE - $pS + 1) * 30 - 2;
                    // Light bg from color
                    $r = hexdec(substr($color,1,2));
                    $g = hexdec(substr($color,3,2));
                    $b = hexdec(substr($color,5,2));
                    $bgStyle = "background:rgba($r,$g,$b,.12);border-left-color:$color;color:#1a1a2e;height:{$cardH}px;";
        ?>
            <div class="sub-card" style="<?php echo $bgStyle; ?>"
                 data-bs-toggle="tooltip"
                 title="<?php echo htmlspecialchars($sub['subject_name'].' | '.$sub['teacher_name'].' | '.$sub['room']); ?>">
                <div class="sn" style="color:<?php echo $color; ?>"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                <div class="si">
                    <?php echo htmlspecialchars($sub['section_code']); ?><br>
                    Nhóm: <?php echo htmlspecialchars($sub['section_code']); ?><br>
                    Phòng: <?php echo htmlspecialchars($sub['room']); ?><br>
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
</tr>
</tfoot>
</table>
</div>
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
function nextWeek(){ location.href='?semester_id='+SEM_ID+'&week='+(CUR_WEEK+1); }

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
