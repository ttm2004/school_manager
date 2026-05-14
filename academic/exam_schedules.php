<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../app/Services/ExamScheduleService.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);
$pageTitle = 'Lich thi Cuoi ky';
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) { $_SESSION['_flash']=['type'=>'danger','message'=>'CSRF invalid.']; header('Location: exam_schedules.php'); exit(); }
    if (!isAcademicManager()) { $_SESSION['_flash']=['type'=>'danger','message'=>'Chi Truong phong moi co quyen.']; header('Location: exam_schedules.php'); exit(); }
    $action = trim($_POST['action'] ?? '');

    if ($action === 'add') {
        $sectionId = (int)($_POST['course_section_id'] ?? 0);
        $examDate  = trim($_POST['exam_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime   = trim($_POST['end_time'] ?? '');
        $room      = trim($_POST['room'] ?? '');
        $examForm  = trim($_POST['exam_form'] ?? 'Tu luan');
        $note      = trim($_POST['note'] ?? '');
        $status    = trim($_POST['status'] ?? 'scheduled');
        if ($sectionId && $examDate && $startTime && $endTime) {
            $examCheck = ExamScheduleService::validate($conn, $sectionId, $examDate, $startTime, $endTime, $room);
            if (!$examCheck['ok']) {
                $_SESSION['_flash']=['type'=>'danger','message'=>$examCheck['message']];
            } else {
                $demoContext = academicPolicySectionDemoContext($conn, $sectionId);
                $stmt = $conn->prepare("INSERT INTO final_exam_schedules (course_section_id,exam_date,start_time,end_time,room,exam_form,note,status,data_mode,demo_batch_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('isssssssss',$sectionId,$examDate,$startTime,$endTime,$room,$examForm,$note,$status,$demoContext['data_mode'],$demoContext['demo_batch_id']);
                $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Them lich thi thanh cong.']
                                 : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
                $stmt->close();
                // Thong bao SV
                if ($_SESSION['_flash']['type']==='success') {
                    $svList = $conn->query("SELECT u.id FROM student_subjects ss JOIN students st ON ss.student_id=st.id JOIN users u ON st.user_id=u.id WHERE ss.course_section_id=$sectionId AND ss.status IN ('registered','auto_enrolled')")->fetch_all(MYSQLI_ASSOC);
                    $info = $conn->query("SELECT s.subject_name, cs.section_code FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id WHERE cs.id=$sectionId LIMIT 1")->fetch_assoc();
                    if ($info && !empty($svList)) {
                        $title = "Lich thi: {$info['subject_name']}";
                        $content = "Lop {$info['section_code']} thi ngay ".date('d/m/Y',strtotime($examDate))." luc $startTime tai phong $room.";
                        $stmtN = $conn->prepare("INSERT INTO system_notifications (user_id,title,content) VALUES (?,?,?)");
                        foreach ($svList as $sv) { $stmtN->bind_param('iss',$sv['id'],$title,$content); $stmtN->execute(); }
                        $stmtN->close();
                    }
                }
            }
        } else { $_SESSION['_flash']=['type'=>'danger','message'=>'Vui long dien day du thong tin.']; }
        header('Location: exam_schedules.php'); exit();
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $examDate = trim($_POST['exam_date'] ?? ''); $startTime = trim($_POST['start_time'] ?? '');
        $endTime  = trim($_POST['end_time'] ?? '');  $room = trim($_POST['room'] ?? '');
        $examForm = trim($_POST['exam_form'] ?? ''); $note = trim($_POST['note'] ?? '');
        $status   = trim($_POST['status'] ?? 'scheduled');
        if ($id) {
            $stmtCurrent = $conn->prepare("SELECT course_section_id FROM final_exam_schedules WHERE id=? LIMIT 1");
            $stmtCurrent->bind_param('i', $id);
            $stmtCurrent->execute();
            $currentExam = $stmtCurrent->get_result()->fetch_assoc();
            $stmtCurrent->close();
            if (!$currentExam) {
                $_SESSION['_flash']=['type'=>'danger','message'=>'Không tìm th?y l?ch thi.'];
                header('Location: exam_schedules.php'); exit();
            }
            $examCheck = ExamScheduleService::validate($conn, (int)$currentExam['course_section_id'], $examDate, $startTime, $endTime, $room, $id);
            if (!$examCheck['ok']) {
                $_SESSION['_flash']=['type'=>'danger','message'=>$examCheck['message']];
                header('Location: exam_schedules.php'); exit();
            }
            $stmt = $conn->prepare("UPDATE final_exam_schedules SET exam_date=?,start_time=?,end_time=?,room=?,exam_form=?,note=?,status=? WHERE id=?");
            $stmt->bind_param('sssssssi',$examDate,$startTime,$endTime,$room,$examForm,$note,$status,$id);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Cap nhat thanh cong.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        }
        header('Location: exam_schedules.php'); exit();
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $stmt=$conn->prepare("DELETE FROM final_exam_schedules WHERE id=?"); $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close(); $_SESSION['_flash']=['type'=>'success','message'=>'Da xoa.']; }
        header('Location: exam_schedules.php'); exit();
    }
}

$flash = getFlash();
$filterSem  = (int)($_GET['semester_id'] ?? 0);
$filterDate = trim($_GET['exam_date'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1)); $perPage = 25;
if ($filterSem===0) { $a=getActiveSemesterAcademic($conn); if ($a) $filterSem=(int)$a['id']; }

$where=['1=1']; $types=''; $params=[];
if ($filterSem>0)   { $where[]='cs.semester_id=?'; $types.='i'; $params[]=$filterSem; }
if ($filterDate!='') { $where[]='fes.exam_date=?'; $types.='s'; $params[]=$filterDate; }
$whereSQL=implode(' AND ',$where);

$stmtCnt=$conn->prepare("SELECT COUNT(*) AS c FROM final_exam_schedules fes JOIN course_sections cs ON fes.course_section_id=cs.id WHERE $whereSQL");
if ($types) $stmtCnt->bind_param($types,...$params); $stmtCnt->execute();
$total=(int)($stmtCnt->get_result()->fetch_assoc()['c']??0); $stmtCnt->close();
$pag=paginateAcademic($total,$page,$perPage);

$stmtData=$conn->prepare("SELECT fes.id, fes.exam_date, fes.start_time, fes.end_time, fes.room, fes.exam_form, fes.status, fes.note, fes.course_section_id,
       cs.section_code, s.subject_name, s.credits, sm.semester_name, f.faculty_name,
       ut.full_name AS teacher_name,
       COUNT(DISTINCT ss.student_id) AS student_count
FROM final_exam_schedules fes
JOIN course_sections cs ON fes.course_section_id=cs.id
JOIN subjects s ON cs.subject_id=s.id JOIN semesters sm ON cs.semester_id=sm.id
LEFT JOIN majors m ON s.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id
LEFT JOIN teachers t ON cs.teacher_id=t.id LEFT JOIN users ut ON t.user_id=ut.id
LEFT JOIN student_subjects ss ON ss.course_section_id=cs.id AND ss.status IN ('registered','auto_enrolled')
WHERE $whereSQL GROUP BY fes.id ORDER BY fes.exam_date ASC, fes.start_time ASC LIMIT ? OFFSET ?");
$allTypes=$types.'ii'; $allParams=array_merge($params,[$pag['per_page'],$pag['offset']]);
$stmtData->bind_param($allTypes,...$allParams); $stmtData->execute();
$exams=$stmtData->get_result()->fetch_all(MYSQLI_ASSOC); $stmtData->close();

$allSections=$conn->query("SELECT cs.id, cs.section_code, s.subject_name, sm.semester_name, sm.school_year, sm.end_date FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id JOIN semesters sm ON cs.semester_id=sm.id WHERE cs.status='open' ORDER BY sm.school_year DESC, s.subject_name")->fetch_all(MYSQLI_ASSOC);
$semesters=$conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$statusLabels=['scheduled'=>['primary','Da len lich'],'completed'=>['success','Da thi xong'],'cancelled'=>['danger','Da huy'],'postponed'=>['warning','Hoan']];

include 'includes/header.php'; include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-calendar-event-fill me-2 text-navy"></i>Lich thi Cuoi ky</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (isAcademicManager()): ?>
        <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Them lich thi</button>
        <?php endif; ?>
        <span class="text-muted small align-self-center"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
    </div>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small">Hoc ky</label>
        <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($semesters as $sm): ?><option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>><?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?></option><?php endforeach; ?>
        </select></div>
    <div><label class="form-label small">Ngay thi</label><input type="date" name="exam_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDate); ?>"></div>
    <button type="submit" class="btn btn-sm btn-navy align-self-end"><i class="bi bi-search"></i></button>
    <a href="exam_schedules.php" class="btn btn-sm btn-outline-secondary align-self-end"><i class="bi bi-x-lg"></i></a>
</form></div></div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-event-fill me-2"></i>Lich thi <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span></span>
    </div>
    <?php if (empty($exams)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-calendar-x fs-2 d-block mb-2"></i>Chua co lich thi.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Mon hoc / Lop</th><th>Khoa</th><th>GV</th><th>Ngay thi</th><th>Gio</th><th>Phong</th><th>Hinh thuc</th><th class="text-center">SV</th><th>TT</th><th class="text-center">Thao tac</th></tr></thead>
            <tbody>
            <?php foreach ($exams as $e):
                [$sColor,$sLabel]=$statusLabels[$e['status']]??['secondary',$e['status']];
                $isToday = date('Y-m-d')===$e['exam_date'];
            ?>
            <tr class="<?php echo $isToday?'table-warning':''; ?>">
                <td><div class="fw-semibold small"><?php echo htmlspecialchars($e['subject_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($e['section_code']); ?></small></td>
                <td class="small"><?php echo htmlspecialchars($e['faculty_name']??'—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($e['teacher_name']??'—'); ?></td>
                <td class="fw-semibold small <?php echo $isToday?'text-warning':''; ?>"><?php echo date('d/m/Y',strtotime($e['exam_date'])); ?><?php if ($isToday): ?><br><span class="badge bg-warning text-dark" style="font-size:.65rem">Hom nay</span><?php endif; ?></td>
                <td class="small fw-semibold"><?php echo substr($e['start_time'],0,5); ?>–<?php echo substr($e['end_time'],0,5); ?></td>
                <td class="small fw-bold text-navy"><?php echo htmlspecialchars($e['room']??'—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($e['exam_form']); ?></td>
                <td class="text-center"><span class="badge bg-navy"><?php echo $e['student_count']; ?></span></td>
                <td><span class="badge bg-<?php echo $sColor; ?>"><?php echo $sLabel; ?></span></td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:2px 6px"
                            data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="<?php echo $e['id']; ?>"
                            data-date="<?php echo $e['exam_date']; ?>"
                            data-start="<?php echo $e['start_time']; ?>"
                            data-end="<?php echo $e['end_time']; ?>"
                            data-room="<?php echo htmlspecialchars($e['room']??''); ?>"
                            data-form="<?php echo htmlspecialchars($e['exam_form']); ?>"
                            data-note="<?php echo htmlspecialchars($e['note']??''); ?>"
                            data-status="<?php echo $e['status']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Xoa lich thi?')">
                        <?php echo csrfField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                        <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:2px 6px"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages']>1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?php echo $pag['offset']+1; ?>–<?php echo min($pag['offset']+$pag['per_page'],$pag['total']); ?> / <?php echo number_format($pag['total']); ?></small>
        <?php echo renderAcademicPagination($pag,http_build_query(['semester_id'=>$filterSem,'exam_date'=>$filterDate])); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal Them -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Them Lich thi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="exam_schedules.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="add">
    <div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label fw-semibold">Lop HP <span class="text-danger">*</span></label>
            <select name="course_section_id" class="form-select" required><option value="">-- Chon lop HP --</option>
            <?php foreach ($allSections as $sec): ?><option value="<?php echo $sec['id']; ?>"><?php echo htmlspecialchars($sec['section_code'].' — '.$sec['subject_name'].' ('.$sec['semester_name'].' '.$sec['school_year'].')'); ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngay thi <span class="text-danger">*</span></label><input type="date" name="exam_date" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Gio bat dau <span class="text-danger">*</span></label><input type="time" name="start_time" class="form-control" required value="07:00"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Gio ket thuc <span class="text-danger">*</span></label><input type="time" name="end_time" class="form-control" required value="09:00"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Phong thi</label><input type="text" name="room" class="form-control" placeholder="VD: A101"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Hinh thuc</label>
            <select name="exam_form" class="form-select"><option>Tu luan</option><option>Trac nghiem</option><option>Tieu luan</option></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Trang thai</label>
            <select name="status" class="form-select"><option value="scheduled">Da len lich</option><option value="completed">Da thi xong</option><option value="cancelled">Da huy</option></select></div>
        <div class="col-12"><label class="form-label fw-semibold">Ghi chu</label><input type="text" name="note" class="form-control"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Luu</button></div>
    </form>
</div></div></div>

<!-- Modal Sua -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chinh sua Lich thi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="exam_schedules.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Ngay thi</label><input type="date" name="exam_date" id="editDate" class="form-control"></div>
        <div class="col-md-3"><label class="form-label fw-semibold">Gio bat dau</label><input type="time" name="start_time" id="editStart" class="form-control"></div>
        <div class="col-md-3"><label class="form-label fw-semibold">Gio ket thuc</label><input type="time" name="end_time" id="editEnd" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Phong thi</label><input type="text" name="room" id="editRoom" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Hinh thuc</label>
            <select name="exam_form" id="editForm" class="form-select"><option>Tu luan</option><option>Trac nghiem</option><option>Tieu luan</option></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Trang thai</label>
            <select name="status" id="editStatus" class="form-select"><option value="scheduled">Da len lich</option><option value="completed">Da thi xong</option><option value="cancelled">Da huy</option><option value="postponed">Hoan</option></select></div>
        <div class="col-12"><label class="form-label fw-semibold">Ghi chu</label><input type="text" name="note" id="editNote" class="form-control"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Luu</button></div>
    </form>
</div></div></div>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value = b.dataset.id;
    document.getElementById('editDate').value = b.dataset.date;
    document.getElementById('editStart').value = b.dataset.start;
    document.getElementById('editEnd').value = b.dataset.end;
    document.getElementById('editRoom').value = b.dataset.room;
    document.getElementById('editForm').value = b.dataset.form;
    document.getElementById('editNote').value = b.dataset.note;
    document.getElementById('editStatus').value = b.dataset.status;
});
</script>
<?php include 'includes/footer.php'; ?>

