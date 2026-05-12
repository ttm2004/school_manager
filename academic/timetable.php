<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);
$pageTitle = 'Thời khóa biểu';
$flash = getFlash();
$filterSem = (int)($_GET['semester_id'] ?? 0);
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
if ($filterSem === 0) { $a = getActiveSemesterAcademic($conn); if ($a) $filterSem = (int)$a['id']; }

$DAY_LABEL = [2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'CN'];
$SESSION_LABEL = ['sang'=>'Sáng (7:00-11:30)','chieu'=>'Chiều (12:30-17:00)','toi'=>'Tối (17:30-22:00)'];
$SESSION_COLOR = ['sang'=>'#f0a500','chieu'=>'#1976d2','toi'=>'#6f42c1'];

$where=['1=1']; $types=''; $params=[];
if ($filterSem) { $where[]='cs.semester_id=?'; $types.='i'; $params[]=$filterSem; }
if ($filterFaculty) { $where[]='f.id=?'; $types.='i'; $params[]=$filterFaculty; }
$whereSQL=implode(' AND ',$where);

$stmtData=$conn->prepare(
    "SELECT cs.id, cs.section_code, cs.day_sessions, cs.room, cs.max_students, cs.current_students, cs.teaching_mode,
            s.subject_name, s.credits, f.faculty_name, m.major_name,
            ut.full_name AS teacher_name, t.teacher_code
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id=s.id
     LEFT JOIN majors m ON s.major_id=m.id
     LEFT JOIN faculties f ON m.faculty_id=f.id
     LEFT JOIN teachers t ON cs.teacher_id=t.id
     LEFT JOIN users ut ON t.user_id=ut.id
     WHERE $whereSQL AND cs.status='open' AND cs.day_sessions IS NOT NULL AND cs.day_sessions != ''
     ORDER BY f.faculty_name, cs.day_sessions"
);
if ($types) $stmtData->bind_param($types,...$params);
$stmtData->execute();
$sections=$stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtData->close();

// Group by day+session
$timetable = [];
foreach ($sections as $cs) {
    $ds = $cs['day_sessions'] ?? '';
    foreach (academicPolicyScheduleTokens($ds) as $part) {
        [$day, $sess] = array_pad(explode(':', trim($part), 2), 2, '');
        if ($day && $sess) {
            $timetable[(int)$day][$sess][] = $cs;
        }
    }
}
ksort($timetable);

$semesters=$conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$faculties=$conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php'; include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-table me-2 text-navy"></i>Thoi khoa bieu</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??'')?></span>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small">Hoc ky</label>
        <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($semesters as $sm): ?><option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>><?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?></option><?php endforeach; ?>
        </select></div>
    <div><label class="form-label small">Khoa</label>
        <select name="faculty_id" class="form-select form-select-sm">
            <option value="0">-- Tat ca --</option>
            <?php foreach ($faculties as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
        </select></div>
    <button type="submit" class="btn btn-sm btn-navy align-self-end"><i class="bi bi-search"></i></button>
    <a href="timetable.php" class="btn btn-sm btn-outline-secondary align-self-end"><i class="bi bi-x-lg"></i></a>
</form></div></div>

<?php if (empty($timetable)): ?>
<div class="card"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>Chua co lich hoc nao duoc xep.
</div></div>
<?php else: ?>
<?php foreach ($timetable as $day => $sessions): ?>
<div class="card mb-3">
    <div class="card-header fw-bold">
        <i class="bi bi-calendar3 me-2"></i><?php echo $DAY_LABEL[$day] ?? "Thu $day"; ?>
    </div>
    <div class="card-body p-0">
    <?php foreach (['sang','chieu','toi'] as $sess):
        if (empty($sessions[$sess])) continue;
        $color = $SESSION_COLOR[$sess] ?? '#666';
    ?>
    <div class="border-bottom p-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge" style="background:<?php echo $color; ?>"><?php echo $SESSION_LABEL[$sess] ?? $sess; ?></span>
            <small class="text-muted"><?php echo count($sessions[$sess]); ?> lop</small>
        </div>
        <div class="row g-2">
        <?php foreach ($sessions[$sess] as $cs): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="border rounded p-2 bg-light h-100">
                <div class="fw-semibold small"><?php echo htmlspecialchars($cs['section_code']); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars(mb_substr($cs['subject_name'],0,35)); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($cs['faculty_name']??''); ?></div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <small class="text-muted"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($cs['teacher_name']??'Chua co GV'); ?></small>
                    <?php if ($cs['room']): ?><small class="badge bg-light text-dark border"><?php echo htmlspecialchars($cs['room']); ?></small><?php endif; ?>
                </div>
                <div class="small text-muted mt-1">
                    <i class="bi bi-people me-1"></i><?php echo $cs['current_students']; ?>/<?php echo $cs['max_students']; ?> SV
                    <?php if ($cs['teaching_mode'] !== 'offline'): ?>
                    <span class="badge bg-info ms-1" style="font-size:.65rem"><?php echo ucfirst($cs['teaching_mode']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
