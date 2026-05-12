<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../includes/teacher_assignment_rules.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);
$pageTitle = 'Quản lý Lớp học phần';
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'CSRF invalid.']; header('Location: course_sections.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chỉ Trưởng phòng mới có quyền.']; header('Location: course_sections.php'); exit();
    }
    $action = trim($_POST['action'] ?? '');
    if ($action === 'add') {
        $subjectId   = (int)($_POST['subject_id'] ?? 0);
        $teacherId   = (int)($_POST['teacher_id'] ?? 0) ?: null;
        $semesterId  = (int)($_POST['semester_id'] ?? 0);
        $code        = trim($_POST['section_code'] ?? '');
        $room        = trim($_POST['room'] ?? '');
        $maxStudents = max(1,(int)($_POST['max_students'] ?? 40));
        $status      = trim($_POST['status'] ?? 'open');
        $daySession  = trim($_POST['day_sessions'] ?? '');
        $startDate   = trim($_POST['start_date'] ?? '') ?: null;
        $endDate     = trim($_POST['end_date'] ?? '') ?: null;
        $mode        = trim($_POST['teaching_mode'] ?? 'offline');
        if ($subjectId && $semesterId && $code) {
            if ($teacherId) {
                $assignmentCheck = validateTeacherAssignment($conn, $teacherId, $subjectId, $semesterId);
                if (!$assignmentCheck['ok']) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>$assignmentCheck['message']];
                    header('Location: course_sections.php'); exit();
                }
            }
            $scheduleCheck = academicPolicyValidateTeachingSchedule($mode, $daySession);
            if (!$scheduleCheck['ok']) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>$scheduleCheck['message']];
                header('Location: course_sections.php'); exit();
            }
            $classroomId = null;
            if ($mode === 'online') {
                $room = '';
            } else {
                $availableRooms = academicPolicyFindAvailableClassrooms($conn, 0, $semesterId, $subjectId, $maxStudents, $mode, $daySession);
                if (empty($availableRooms)) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không còn phòng học trống và phù hợp với lịch/sĩ số/môn học đã chọn.'];
                    header('Location: course_sections.php'); exit();
                }
                if ($room === '') {
                    $room = (string)$availableRooms[0]['room_code'];
                    $classroomId = (int)$availableRooms[0]['id'];
                } else {
                    $selectedRoom = null;
                    foreach ($availableRooms as $availableRoom) {
                        if ((string)$availableRoom['room_code'] === $room) {
                            $selectedRoom = $availableRoom;
                            break;
                        }
                    }
                    if (!$selectedRoom) {
                        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Phòng đã chọn không trống hoặc không phù hợp. Vui lòng chọn phòng từ danh mục phòng trống.'];
                        header('Location: course_sections.php'); exit();
                    }
                    $classroomId = (int)$selectedRoom['id'];
                }
            }
            $stmt = $conn->prepare("INSERT INTO course_sections (subject_id,teacher_id,semester_id,section_code,room,classroom_id,max_students,current_students,status,day_sessions,start_date,end_date,teaching_mode) VALUES (?,?,?,?,?,?,?,0,?,?,?,?,?)");
            $stmt->bind_param('iiissiisssss', $subjectId,$teacherId,$semesterId,$code,$room,$classroomId,$maxStudents,$status,$daySession,$startDate,$endDate,$mode);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Thêm lớp học phần thành công.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        } else { $_SESSION['_flash']=['type'=>'danger','message'=>'Vui lòng điền đầy đủ thông tin.']; }
        header('Location: course_sections.php'); exit();
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $teacherId   = (int)($_POST['teacher_id'] ?? 0) ?: null;
        $room        = trim($_POST['room'] ?? '');
        $maxStudents = max(1,(int)($_POST['max_students'] ?? 40));
        $status      = trim($_POST['status'] ?? 'open');
        $daySession  = trim($_POST['day_sessions'] ?? '');
        $startDate   = trim($_POST['start_date'] ?? '') ?: null;
        $endDate     = trim($_POST['end_date'] ?? '') ?: null;
        $mode        = trim($_POST['teaching_mode'] ?? 'offline');
        if ($id) {
            $stmtSection = $conn->prepare("SELECT subject_id, semester_id, room_requirement FROM course_sections WHERE id = ? LIMIT 1");
            $stmtSection->bind_param('i', $id);
            $stmtSection->execute();
            $sectionForRoom = $stmtSection->get_result()->fetch_assoc();
            $stmtSection->close();
            if (!$sectionForRoom) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không tìm thấy lớp học phần.'];
                header('Location: course_sections.php'); exit();
            }
            if ($teacherId) {
                $assignmentCheck = validateTeacherAssignmentForSection($conn, $teacherId, $id);
                if (!$assignmentCheck['ok']) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>$assignmentCheck['message']];
                    header('Location: course_sections.php'); exit();
                }
            }
            $scheduleCheck = academicPolicyValidateTeachingSchedule($mode, $daySession);
            if (!$scheduleCheck['ok']) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>$scheduleCheck['message']];
                header('Location: course_sections.php'); exit();
            }
            $classroomId = null;
            if ($mode === 'online') {
                $room = '';
            } else {
                $availableRooms = academicPolicyFindAvailableClassrooms(
                    $conn,
                    $id,
                    (int)$sectionForRoom['semester_id'],
                    (int)$sectionForRoom['subject_id'],
                    $maxStudents,
                    $mode,
                    $daySession,
                    (string)($sectionForRoom['room_requirement'] ?? '')
                );
                if (empty($availableRooms)) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không còn phòng học trống và phù hợp với lịch/sĩ số/môn học đã chọn.'];
                    header('Location: course_sections.php'); exit();
                }
                if ($room === '') {
                    $room = (string)$availableRooms[0]['room_code'];
                    $classroomId = (int)$availableRooms[0]['id'];
                } else {
                    $selectedRoom = null;
                    foreach ($availableRooms as $availableRoom) {
                        if ((string)$availableRoom['room_code'] === $room) {
                            $selectedRoom = $availableRoom;
                            break;
                        }
                    }
                    if (!$selectedRoom) {
                        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Phòng đã chọn không trống hoặc không phù hợp. Vui lòng chọn phòng từ danh mục phòng trống.'];
                        header('Location: course_sections.php'); exit();
                    }
                    $classroomId = (int)$selectedRoom['id'];
                }
            }
            $stmt = $conn->prepare("UPDATE course_sections SET teacher_id=?,room=?,classroom_id=?,max_students=?,status=?,day_sessions=?,start_date=?,end_date=?,teaching_mode=? WHERE id=?");
            $stmt->bind_param('isiisssssi', $teacherId,$room,$classroomId,$maxStudents,$status,$daySession,$startDate,$endDate,$mode,$id);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Cap nhat thanh cong.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        }
        header('Location: course_sections.php'); exit();
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $chk = $conn->query("SELECT COUNT(*) AS c FROM student_subjects WHERE course_section_id=$id")->fetch_assoc()['c'];
            if ($chk > 0) { $_SESSION['_flash']=['type'=>'danger','message'=>'Không thể xóa: có sinh viên đã đăng ký.']; }
            else {
                $stmt = $conn->prepare("DELETE FROM course_sections WHERE id=?");
                $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();
                $_SESSION['_flash']=['type'=>'success','message'=>'Đã xóa lớp học phần.'];
            }
        }
        header('Location: course_sections.php'); exit();
    }
}

$flash = getFlash();
$filterSem     = (int)($_GET['semester_id'] ?? 0);
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? '');
$search        = trim($_GET['q'] ?? '');
$page          = max(1,(int)($_GET['page'] ?? 1));
$perPage       = 20;

if ($filterSem === 0) { $a = getActiveSemesterAcademic($conn); if ($a) $filterSem = (int)$a['id']; }

$where = ['1=1']; $types = ''; $params = [];
if ($filterSem > 0)    { $where[] = 'cs.semester_id=?'; $types .= 'i'; $params[] = $filterSem; }
if ($filterFaculty > 0){ $where[] = 'f.id=?'; $types .= 'i'; $params[] = $filterFaculty; }
if ($filterStatus !== ''){ $where[] = 'cs.status=?'; $types .= 's'; $params[] = $filterStatus; }
if ($search !== '')    { $where[] = '(s.subject_name LIKE ? OR cs.section_code LIKE ?)'; $like="%$search%"; $types .= 'ss'; $params[] = $like; $params[] = $like; }
$whereSQL = implode(' AND ', $where);

$stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id LEFT JOIN majors m ON s.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL");
if ($types) $stmtCnt->bind_param($types,...$params); $stmtCnt->execute();
$total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0); $stmtCnt->close();
$pag = paginateAcademic($total, $page, $perPage);

$stmtData = $conn->prepare("SELECT cs.id, cs.section_code, cs.status, cs.max_students, cs.current_students, cs.room, cs.day_sessions, cs.start_date, cs.end_date, cs.teaching_mode, cs.proposal_status,
       s.subject_name, s.credits, f.faculty_name,
       ut.full_name AS teacher_name, t.teacher_code, sm.semester_name
FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id
JOIN semesters sm ON cs.semester_id=sm.id
LEFT JOIN majors m ON s.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id
LEFT JOIN teachers t ON cs.teacher_id=t.id LEFT JOIN users ut ON t.user_id=ut.id
WHERE $whereSQL ORDER BY f.faculty_name, s.subject_name LIMIT ? OFFSET ?");
$allTypes = $types.'ii'; $allParams = array_merge($params,[$pag['per_page'],$pag['offset']]);
$stmtData->bind_param($allTypes,...$allParams); $stmtData->execute();
$sections = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC); $stmtData->close();

$subjects  = $conn->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_name")->fetch_all(MYSQLI_ASSOC);
$teachers  = $conn->query("SELECT t.id, t.teacher_code, u.full_name, f.faculty_name FROM teachers t JOIN users u ON t.user_id=u.id LEFT JOIN faculties f ON t.faculty_id=f.id ORDER BY f.faculty_name, u.full_name")->fetch_all(MYSQLI_ASSOC);
$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$faculties = $conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);

$statusLabels = ['open'=>['success','Mở'],'proposed'=>['warning','Đề xuất'],'draft'=>['secondary','Nháp'],'full'=>['info','Đầy'],'closed'=>['dark','Đóng'],'cancelled'=>['danger','Hủy']];
$modeLabels   = ['offline'=>'Offline','online'=>'Online','hybrid'=>'Hybrid'];

include 'includes/header.php'; include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-grid-3x3-gap-fill me-2 text-navy"></i>Quản lý Lớp học phần</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (isAcademicManager()): ?>
        <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Thêm lớp học phần</button>
        <?php endif; ?>
        <span class="text-muted small align-self-center"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
    </div>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small">Học kỳ</label>
        <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($semesters as $sm): ?><option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>><?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Khoa</label>
        <select name="faculty_id" class="form-select form-select-sm">
            <option value="0">-- Tất cả --</option>
            <?php foreach ($faculties as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Trạng thái</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">-- Tất cả --</option>
            <?php foreach ($statusLabels as $k=>[$c,$l]): ?><option value="<?php echo $k; ?>" <?php echo $filterStatus===$k?'selected':''; ?>><?php echo $l; ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small">Tim kiem</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Tên môn, mã lớp..." value="<?php echo htmlspecialchars($search); ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
        <a href="course_sections.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
</form></div></div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-grid-3x3-gap-fill me-2"></i>Lớp học phần <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span></span>
    </div>
    <?php if (empty($sections)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có dữ liệu.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Mã lớp / Môn học</th><th>Khoa</th><th>Giảng viên</th><th class="text-center">Sĩ số</th><th>Lịch học</th><th>Hình thức</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($sections as $cs):
                [$sColor,$sLabel] = $statusLabels[$cs['status']] ?? ['secondary',$cs['status']];
                $pct = $cs['max_students'] > 0 ? round($cs['current_students']/$cs['max_students']*100) : 0;
            ?>
            <tr>
                <td><div class="fw-semibold small"><?php echo htmlspecialchars($cs['section_code']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($cs['subject_name']); ?> · <?php echo $cs['credits']; ?> TC</small></td>
                <td class="small"><?php echo htmlspecialchars($cs['faculty_name']??'—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($cs['teacher_name']??'<span class="text-warning">Chưa có</span>'); ?></td>
                <td class="text-center">
                    <span class="badge bg-<?php echo $pct>=100?'danger':($pct>=80?'warning':'success'); ?>"><?php echo $cs['current_students']; ?>/<?php echo $cs['max_students']; ?></span></td>
                <td class="small text-muted"><?php echo htmlspecialchars($cs['day_sessions']??'—'); ?></td>
                <td class="small"><?php echo $modeLabels[$cs['teaching_mode']] ?? 'Offline'; ?></td>
                <td><span class="badge bg-<?php echo $sColor; ?>"><?php echo $sLabel; ?></span>
                    <?php if ($cs['proposal_status']==='pending'): ?><br><span class="badge bg-info" style="font-size:.65rem">Đề xuất GV</span><?php endif; ?></td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:2px 6px"
                            data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="<?php echo $cs['id']; ?>"
                            data-room="<?php echo htmlspecialchars($cs['room']??''); ?>"
                            data-max="<?php echo $cs['max_students']; ?>"
                            data-status="<?php echo $cs['status']; ?>"
                            data-day="<?php echo htmlspecialchars($cs['day_sessions']??''); ?>"
                            data-start="<?php echo $cs['start_date']??''; ?>"
                            data-end="<?php echo $cs['end_date']??''; ?>"
                            data-mode="<?php echo $cs['teaching_mode']??'offline'; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Xóa lớp học phần này?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $cs['id']; ?>">
                        <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:2px 6px"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?php echo $pag['offset']+1; ?>–<?php echo min($pag['offset']+$pag['per_page'],$pag['total']); ?> / <?php echo number_format($pag['total']); ?></small>
        <?php $qs2=http_build_query(['semester_id'=>$filterSem,'faculty_id'=>$filterFaculty,'status'=>$filterStatus,'q'=>$search]); echo renderAcademicPagination($pag,$qs2); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal Them -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Thêm Lớp học phần</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="course_sections.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="add">
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Mã lớp học phần <span class="text-danger">*</span></label><input type="text" name="section_code" class="form-control" required placeholder="VD: CNTT101_01"></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Môn học <span class="text-danger">*</span></label>
            <select name="subject_id" class="form-select" required><option value="">-- Chọn môn --</option>
            <?php foreach ($subjects as $sub): ?><option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subject_code'].' - '.$sub['subject_name']); ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Học kỳ <span class="text-danger">*</span></label>
            <select name="semester_id" class="form-select" required><option value="">-- Chọn học kỳ --</option>
            <?php foreach ($semesters as $sm): ?><option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>><?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Giảng viên</label>
            <select name="teacher_id" class="form-select"><option value="">-- Chưa phân công --</option>
            <?php $lastF=''; foreach ($teachers as $t): if ($t['faculty_name']!==$lastF) { if ($lastF!=='') echo '</optgroup>'; echo '<optgroup label="'.htmlspecialchars($t['faculty_name']??'Khac').'">'; $lastF=$t['faculty_name']; } ?>
            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name'].' ('.$t['teacher_code'].')'); ?></option>
            <?php endforeach; if ($lastF!=='') echo '</optgroup>'; ?></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Phong hoc</label><input type="text" name="room" class="form-control" placeholder="VD: A101"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Si so toi da</label><input type="number" name="max_students" class="form-control" value="40" min="1"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Hinh thuc</label>
            <select name="teaching_mode" class="form-select"><option value="offline">Offline</option><option value="online">Online</option><option value="hybrid">Hybrid</option></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Lịch học (day_sessions)</label><input type="text" name="day_sessions" class="form-control" placeholder="VD: 2:sang,4:chieu"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày bắt đầu</label><input type="date" name="start_date" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày kết thúc</label><input type="date" name="end_date" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Trạng thái</label>
            <select name="status" class="form-select"><option value="open">Mở</option><option value="draft">Nháp</option><option value="closed">Đóng</option></select></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Lưu</button></div>
    </form>
</div></div></div>

<!-- Modal Sua -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chỉnh sửa Lớp học phần</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="course_sections.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Giảng viên</label>
            <select name="teacher_id" class="form-select"><option value="">-- Chưa phân công --</option>
            <?php $lastF=''; foreach ($teachers as $t): if ($t['faculty_name']!==$lastF) { if ($lastF!=='') echo '</optgroup>'; echo '<optgroup label="'.htmlspecialchars($t['faculty_name']??'Khac').'">'; $lastF=$t['faculty_name']; } ?>
            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name'].' ('.$t['teacher_code'].')'); ?></option>
            <?php endforeach; if ($lastF!=='') echo '</optgroup>'; ?></select></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Phong hoc</label><input type="text" name="room" id="editRoom" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Si so toi da</label><input type="number" name="max_students" id="editMax" class="form-control" min="1"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Hinh thuc</label>
            <select name="teaching_mode" id="editMode" class="form-select"><option value="offline">Offline</option><option value="online">Online</option><option value="hybrid">Hybrid</option></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Trạng thái</label>
            <select name="status" id="editStatus" class="form-select">
                <?php foreach ($statusLabels as $k=>[$c,$l]): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Lịch học</label><input type="text" name="day_sessions" id="editDay" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày bắt đầu</label><input type="date" name="start_date" id="editStart" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày kết thúc</label><input type="date" name="end_date" id="editEnd" class="form-control"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Lưu</button></div>
    </form>
</div></div></div>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value = b.dataset.id;
    document.getElementById('editRoom').value = b.dataset.room;
    document.getElementById('editMax').value = b.dataset.max;
    document.getElementById('editStatus').value = b.dataset.status;
    document.getElementById('editDay').value = b.dataset.day;
    document.getElementById('editStart').value = b.dataset.start;
    document.getElementById('editEnd').value = b.dataset.end;
    document.getElementById('editMode').value = b.dataset.mode;
});
</script>
<?php include 'includes/footer.php'; ?>
