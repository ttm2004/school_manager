<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../app/Services/RoomSchedulingService.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'De xuat mo lop tu Khoa';
$userId    = (int)$_SESSION['user_id'];

// POST: duyet / tu choi de xuat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yeu cau khong hop le.'];
        header('Location: proposals.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chi Truong phong Dao tao moi co quyen duyet.'];
        header('Location: proposals.php'); exit();
    }

    $action   = trim($_POST['action'] ?? '');
    $sectionId = (int)($_POST['section_id'] ?? 0);

    // DUYET de xuat mo lop: proposed -> open
    if ($action === 'approve_open') {
        $maxStudents  = max(1, (int)($_POST['max_students'] ?? 40));
        $room         = trim($_POST['room'] ?? '');
        $teachingMode = trim($_POST['teaching_mode'] ?? 'offline');
        $daySessions  = trim($_POST['day_sessions'] ?? '');
        $note         = trim($_POST['note'] ?? '');
        if (!in_array($teachingMode, ['offline', 'online', 'hybrid'], true)) {
            $teachingMode = 'offline';
        }

        $policyCheck = academicPolicyValidateSectionOpening($conn, $sectionId);
        if (!$policyCheck['ok']) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>$policyCheck['message']];
            header('Location: proposals.php'); exit();
        }
        $section = $policyCheck['section'] ?? [];
        $approvalWindow = academicPolicyCheckApprovalWindow($conn, (int)($section['semester_id'] ?? 0));
        if (!$approvalWindow['ok']) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>$approvalWindow['message']];
            header('Location: proposals.php'); exit();
        }
        $window = $policyCheck['eligible'][0]['semester_window'] ?? null;
        if (!$window) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Hoc ky chua co nam hoc hop le de tinh ngay bat dau/ket thuc.'];
            header('Location: proposals.php'); exit();
        }
        $sectionStartDate = $window['start_date'];
        $sectionEndDate   = $window['end_date'];
        $loadRow = $conn->query(
            "SELECT COALESCE(NULLIF(total_periods,0), theory_periods + practice_periods, credits * 15, 45) AS total_periods
             FROM subjects WHERE id = " . (int)$section['subject_id'] . " LIMIT 1"
        )->fetch_assoc();
        $totalPeriods = max(1, (int)($loadRow['total_periods'] ?? 45));
        $minStudents = max(1, (int)($section['min_students'] ?? 20));
        if ($maxStudents < $minStudents) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>"Sĩ số tối đa phải từ {$minStudents} sinh viên trở lên."];
            header('Location: proposals.php'); exit();
        }
        if ($daySessions === '') {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng nhập lịch học để kiểm tra trùng phòng/giảng viên.'];
            header('Location: proposals.php'); exit();
        }
        $calculatedEndDate = academicScheduleSectionEndDate($sectionStartDate, $daySessions, $totalPeriods, $window['end_date']);
        if ($calculatedEndDate) {
            $sectionEndDate = $calculatedEndDate;
        }
        $scheduleCheck = RoomSchedulingService::validateTeachingSchedule($teachingMode, $daySessions);
        if (!$scheduleCheck['ok']) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>$scheduleCheck['message']];
            header('Location: proposals.php'); exit();
        }
        $teacherIdForConflict = (int)(($section['teacher_id'] ?? 0) ?: ($section['proposed_teacher_id'] ?? 0));
        if ($teacherIdForConflict > 0 && academicPolicyHasScheduleConflict($conn, $sectionId, 'teacher_id', $teacherIdForConflict, (int)$section['semester_id'], $daySessions)) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Giảng viên đang bị trùng lịch trong học kỳ này.'];
            header('Location: proposals.php'); exit();
        }

        $classroomId = null;
        if ($teachingMode === 'online') {
            $room = '';
        } else {
            $availableRooms = RoomSchedulingService::findAvailableClassrooms(
                $conn,
                $sectionId,
                (int)$section['semester_id'],
                (int)$section['subject_id'],
                $maxStudents,
                $teachingMode,
                $daySessions,
                (string)($section['room_requirement'] ?? '')
            );

            if (empty($availableRooms)) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không còn phòng học trống và phù hợp với môn/lịch học đã chọn. Vui lòng đổi lịch hoặc kiểm tra lại danh mục phòng.'];
                header('Location: proposals.php'); exit();
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
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Phòng học đã chọn không còn trống hoặc không phù hợp với môn/lịch học. Vui lòng chọn phòng trong danh sách phòng trống.'];
                    header('Location: proposals.php'); exit();
                }
                $classroomId = (int)$selectedRoom['id'];
            }
        }

        $roomCheck = RoomSchedulingService::validateRoom($conn, $room, $maxStudents, (int)$section['subject_id'], $teachingMode, (string)($section['room_requirement'] ?? ''));
        if (!$roomCheck['ok']) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>$roomCheck['message']];
            header('Location: proposals.php'); exit();
        }
        $classroomId = $teachingMode === 'online' ? null : (int)$classroomId;

        $stmt = $conn->prepare(
            "UPDATE course_sections
             SET status='open',
                 max_students=?,
                 room=?,
                 classroom_id=?,
                 teaching_mode=?,
                 day_sessions=?,
                 start_date=?,
                 end_date=?,
                 open_reviewed_by=?,
                 open_reviewed_at=NOW(),
                 note=?
             WHERE id=? AND status='proposed'"
        );
        $stmt->bind_param('isissssisi', $maxStudents, $room, $classroomId, $teachingMode, $daySessions, $sectionStartDate, $sectionEndDate, $userId, $note, $sectionId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Lay thong tin de gui thong bao
            $info = $conn->query("SELECT cs.open_proposed_by, s.subject_name, cs.section_code,
                                         f.faculty_name, f.id AS faculty_id
                                  FROM course_sections cs
                                  JOIN subjects s ON cs.subject_id = s.id
                                  LEFT JOIN teachers t ON t.user_id = cs.open_proposed_by
                                  LEFT JOIN faculties f ON t.faculty_id = f.id
                                  WHERE cs.id = $sectionId LIMIT 1")->fetch_assoc();
            if ($info) {
                sendAcademicNotification($conn, $userId, 'proposal_approved',
                    'De xuat mo lop duoc duyet',
                    "Lớp HP {$info['section_code']} ({$info['subject_name']}) đã được Phòng Đào tạo duyệt mở chính thức.",
                    $info['faculty_id'] ?? null, null, $sectionId, 'course_section'
                );
                // Gui vao bang notifications cho Truong khoa
                if ($info['open_proposed_by']) {
                    $title = "Lop HP duoc duyet: {$info['section_code']}";
                    $content = "Đề xuất mở lớp {$info['section_code']} ({$info['subject_name']}) đã được Phòng Đào tạo phê duyệt.";
                    $stmtN = $conn->prepare("INSERT INTO system_notifications (user_id, title, content) VALUES (?,?,?)");
                    $stmtN->bind_param('iss', $info['open_proposed_by'], $title, $content);
                    $stmtN->execute(); $stmtN->close();
                }
            }
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Da duyet mo lop HP thanh cong.'];
        } else {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Loi hoac lop HP khong o trang thai cho duyet.'];
        }
        $stmt->close();
        header('Location: proposals.php'); exit();
    }

    // TU CHOI de xuat mo lop: proposed -> cancelled
    if ($action === 'reject_open') {
        $reason = trim($_POST['reject_reason'] ?? '');
        if ($reason === '') {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui long nhap ly do tu choi.'];
            header('Location: proposals.php'); exit();
        }
        $stmt = $conn->prepare(
            "UPDATE course_sections
             SET status='cancelled',
                 open_reject_reason=?,
                 open_reviewed_by=?,
                 open_reviewed_at=NOW()
             WHERE id=? AND status='proposed'"
        );
        $stmt->bind_param('sii', $reason, $userId, $sectionId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $info = $conn->query("SELECT cs.open_proposed_by, s.subject_name, cs.section_code,
                                         f.id AS faculty_id
                                  FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id
                                  LEFT JOIN teachers t ON t.user_id=cs.open_proposed_by
                                  LEFT JOIN faculties f ON t.faculty_id=f.id
                                  WHERE cs.id=$sectionId LIMIT 1")->fetch_assoc();
            if ($info) {
                sendAcademicNotification($conn, $userId, 'proposal_rejected',
                    'De xuat mo lop bi tu choi',
                    "Lop HP {$info['section_code']} bi tu choi. Ly do: $reason",
                    $info['faculty_id'] ?? null, null, $sectionId, 'course_section'
                );
                if ($info['open_proposed_by']) {
                    $title = "De xuat bi tu choi: {$info['section_code']}";
                    $stmtN = $conn->prepare("INSERT INTO system_notifications (user_id, title, content) VALUES (?,?,?)");
                    $stmtN->bind_param('iss', $info['open_proposed_by'], $title, $reason);
                    $stmtN->execute(); $stmtN->close();
                }
            }
            $_SESSION['_flash'] = ['type'=>'warning','message'=>'Da tu choi de xuat.'];
        }
        $stmt->close();
        header('Location: proposals.php'); exit();
    }
}

$flash = getFlash();

// Filters
$filterFaculty  = (int)($_GET['faculty_id'] ?? 0);
$filterSemester = (int)($_GET['semester_id'] ?? 0);
$filterStatus   = trim($_GET['status'] ?? 'proposed');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;

// Build WHERE
$where  = ['1=1'];
$types  = '';
$params = [];

if ($filterFaculty > 0) {
    $where[]  = 'f.id = ?';
    $types   .= 'i';
    $params[] = $filterFaculty;
}
if ($filterSemester > 0) {
    $where[]  = 'cs.semester_id = ?';
    $types   .= 'i';
    $params[] = $filterSemester;
}
$allowedStatuses = ['proposed','open','cancelled','all'];
if ($filterStatus !== 'all' && in_array($filterStatus, $allowedStatuses, true)) {
    $where[]  = 'cs.status = ?';
    $types   .= 's';
    $params[] = $filterStatus;
} elseif ($filterStatus === 'all') {
    $where[] = "cs.status IN ('proposed','open','cancelled')";
}

$whereSQL = implode(' AND ', $where);

// Count
$countSQL = "SELECT COUNT(*) AS c
             FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN semesters sm ON cs.semester_id = sm.id
             LEFT JOIN teachers t ON t.user_id = cs.open_proposed_by
             LEFT JOIN faculties f ON t.faculty_id = f.id
             WHERE $whereSQL";
$stmtCnt = $conn->prepare($countSQL);
if ($types) $stmtCnt->bind_param($types, ...$params);
$stmtCnt->execute();
$total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
$stmtCnt->close();

$pag = paginateAcademic($total, $page, $perPage);

// Fetch
$dataSQL = "SELECT cs.id, cs.section_code, cs.status, cs.expected_students,
                   cs.open_proposed_at, cs.open_reviewed_at, cs.open_reject_reason,
                   cs.open_proposal_note, cs.teaching_mode, cs.room_requirement,
                   s.subject_name, s.subject_code, s.credits,
                   sm.semester_name, sm.school_year,
                   f.faculty_name, tc.cohort_code, tc.cohort_name,
                   up.full_name AS proposed_by_name,
                   ur.full_name AS reviewed_by_name,
                   pt.teacher_code AS proposed_teacher_code,
                   ptu.full_name AS proposed_teacher_name
            FROM course_sections cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN semesters sm ON cs.semester_id = sm.id
            LEFT JOIN training_cohorts tc ON cs.target_cohort_id = tc.id
            LEFT JOIN teachers t ON t.user_id = cs.open_proposed_by
            LEFT JOIN faculties f ON t.faculty_id = f.id
            LEFT JOIN users up ON cs.open_proposed_by = up.id
            LEFT JOIN users ur ON cs.open_reviewed_by = ur.id
            LEFT JOIN teachers pt ON cs.proposed_teacher_id = pt.id
            LEFT JOIN users ptu ON pt.user_id = ptu.id
            WHERE $whereSQL
            ORDER BY cs.open_proposed_at DESC
            LIMIT ? OFFSET ?";
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [$pag['per_page'], $pag['offset']]);
$stmtData  = $conn->prepare($dataSQL);
$stmtData->bind_param($allTypes, ...$allParams);
$stmtData->execute();
$proposals = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtData->close();

// Lay danh sach khoa va hoc ky de filter
$faculties  = $conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$semesters  = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$roomTypeLabels = ['theory'=>'Lý thuyết','lab'=>'Thực hành','computer_lab'=>'Phòng máy','online'=>'Online','other'=>'Khác'];

$statusLabels = [
    'proposed'  => ['warning', 'Cho duyet'],
    'open'      => ['success', 'Da duyet - Mo'],
    'cancelled' => ['danger',  'Tu choi'],
    'draft'     => ['secondary','Nhap'],
];

// Lay chi tiet de xuat dang xem (neu co ?action=review&id=X)
$reviewSection = null;
$reviewRoomOptions = [];
if (isset($_GET['action']) && $_GET['action'] === 'review' && isset($_GET['id'])) {
    $rid = (int)$_GET['id'];
    $stmtRev = $conn->prepare(
        "SELECT cs.*, s.subject_name, s.subject_code, s.credits,
                sm.semester_name, sm.school_year,
                f.faculty_name, tc.cohort_code, tc.cohort_name, up.full_name AS proposed_by_name,
                pt.teacher_code AS proposed_teacher_code, ptu.full_name AS proposed_teacher_name,
                maj.major_name
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN semesters sm ON cs.semester_id = sm.id
         LEFT JOIN training_cohorts tc ON cs.target_cohort_id = tc.id
         LEFT JOIN teachers t ON t.user_id = cs.open_proposed_by
         LEFT JOIN faculties f ON t.faculty_id = f.id
         LEFT JOIN users up ON cs.open_proposed_by = up.id
         LEFT JOIN teachers pt ON cs.proposed_teacher_id = pt.id
         LEFT JOIN users ptu ON pt.user_id = ptu.id
         LEFT JOIN majors maj ON s.major_id = maj.id
         WHERE cs.id = ? AND cs.status = 'proposed'
         LIMIT 1"
    );
    $stmtRev->bind_param('i', $rid);
    $stmtRev->execute();
    $reviewSection = $stmtRev->get_result()->fetch_assoc();
    $stmtRev->close();

    if ($reviewSection) {
        $reviewMode = (string)($reviewSection['teaching_mode'] ?: 'offline');
        $reviewMaxStudents = max(1, (int)($reviewSection['expected_students'] ?: $reviewSection['max_students'] ?: 40));
        $reviewRoomOptions = RoomSchedulingService::findAvailableClassrooms(
            $conn,
            (int)$reviewSection['id'],
            (int)$reviewSection['semester_id'],
            (int)$reviewSection['subject_id'],
            $reviewMaxStudents,
            $reviewMode,
            $reviewSection['day_sessions'] ?? '',
            (string)($reviewSection['room_requirement'] ?? '')
        );
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-send-fill me-2 text-navy"></i>De xuat mo lop tu Khoa</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
</div>
<div class="admin-content">

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3">
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Modal duyet de xuat -->
<?php if ($reviewSection && isAcademicManager()): ?>
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <i class="bi bi-clipboard-check me-2"></i>
        Duyet de xuat: <strong><?php echo htmlspecialchars($reviewSection['section_code']); ?></strong>
        — <?php echo htmlspecialchars($reviewSection['subject_name']); ?>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><small class="text-muted d-block">Mon hoc</small>
                <strong><?php echo htmlspecialchars($reviewSection['subject_name']); ?></strong>
                <span class="text-muted">(<?php echo $reviewSection['credits']; ?> TC)</span>
            </div>
            <div class="col-md-3"><small class="text-muted d-block">Khoa</small>
                <?php echo htmlspecialchars($reviewSection['faculty_name']??'—'); ?>
            </div>
            <div class="col-md-3"><small class="text-muted d-block">Hoc ky</small>
                <?php echo htmlspecialchars($reviewSection['semester_name'].' '.$reviewSection['school_year']); ?>
            </div>
            <div class="col-md-3"><small class="text-muted d-block">Khoa tuyen sinh</small>
                <?php echo htmlspecialchars($reviewSection['cohort_code'] ?? '—'); ?>
            </div>
            <div class="col-md-3"><small class="text-muted d-block">Si so du kien</small>
                <strong><?php echo (int)$reviewSection['expected_students']; ?></strong> SV
            </div>
            <?php if ($reviewSection['room_requirement']): ?>
            <div class="col-md-6"><small class="text-muted d-block">Yeu cau phong</small>
                <?php echo htmlspecialchars($reviewSection['room_requirement']); ?>
            </div>
            <?php endif; ?>
            <div class="col-md-6"><small class="text-muted d-block">Lich hoc de xuat</small>
                <?php echo htmlspecialchars($reviewSection['day_sessions'] ?? '—'); ?>
            </div>
            <div class="col-md-6"><small class="text-muted d-block">GV de xuat tu Khoa</small>
                <?php if (!empty($reviewSection['proposed_teacher_name'])): ?>
                    <strong><?php echo htmlspecialchars($reviewSection['proposed_teacher_name']); ?></strong>
                    <span class="text-muted">(<?php echo htmlspecialchars($reviewSection['proposed_teacher_code'] ?? ''); ?>)</span>
                    <span class="badge bg-warning text-dark ms-1">Cho duyet phan cong</span>
                <?php else: ?>
                    <span class="text-muted">Chua de xuat</span>
                <?php endif; ?>
            </div>
            <?php if ($reviewSection['open_proposal_note']): ?>
            <div class="col-12"><small class="text-muted d-block">Ghi chu tu Khoa</small>
                <em><?php echo htmlspecialchars($reviewSection['open_proposal_note']); ?></em>
            </div>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <!-- Form duyet -->
            <div class="col-md-7">
                <div class="card border-success">
                    <div class="card-header bg-success text-white"><i class="bi bi-check-circle me-2"></i>Duyet mo lop</div>
                    <div class="card-body">
                        <form method="post" action="proposals.php">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="approve_open">
                            <input type="hidden" name="section_id" value="<?php echo $reviewSection['id']; ?>">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Si so toi da <span class="text-danger">*</span></label>
                                    <input type="number" name="max_students" class="form-control form-control-sm"
                                           value="<?php echo (int)($reviewSection['expected_students'] ?: 40); ?>" min="1" max="200" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Hinh thuc hoc</label>
                                    <select name="teaching_mode" class="form-select form-select-sm">
                                        <option value="offline" <?php echo ($reviewSection['teaching_mode']??'offline')==='offline'?'selected':''; ?>>Offline</option>
                                        <option value="online" <?php echo ($reviewSection['teaching_mode']??'')==='online'?'selected':''; ?>>Online</option>
                                        <option value="hybrid" <?php echo ($reviewSection['teaching_mode']??'')==='hybrid'?'selected':''; ?>>Hybrid</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Phòng học (Phòng Đào tạo xếp)</label>
                                    <select name="room" class="form-select form-select-sm">
                                        <option value="">-- Hệ thống tự đề xuất phòng phù hợp --</option>
                                        <?php foreach ($reviewRoomOptions as $idx => $room): ?>
                                        <option value="<?php echo htmlspecialchars($room['room_code']); ?>" <?php echo ($reviewSection['room']??'')===$room['room_code']?'selected':''; ?>>
                                            <?php echo htmlspecialchars(($idx === 0 ? '[Đề xuất] ' : '') . $room['room_code'] . ' - ' . ($room['room_name'] ?: $roomTypeLabels[$room['room_type']] ?? $room['room_type']) . ' (' . $room['capacity'] . ' SV)'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (($reviewSection['teaching_mode'] ?? 'offline') !== 'online'): ?>
                                        <?php if (!empty($reviewRoomOptions)): ?>
                                        <div class="form-text">
                                            Chỉ hiển thị phòng đang trống theo lịch đề xuất và phù hợp sĩ số/loại môn.
                                            Nếu để trống, hệ thống sẽ chọn phòng được đánh dấu đề xuất.
                                        </div>
                                        <?php else: ?>
                                        <div class="form-text text-danger">
                                            Chưa có phòng trống phù hợp với lịch hiện tại. Vui lòng đổi lịch học hoặc kiểm tra lại danh mục phòng.
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                    <div class="form-text">Lớp online không cần xếp phòng học vật lý.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Lich hoc <span class="text-danger">*</span></label>
                                    <input type="text" name="day_sessions" class="form-control form-control-sm"
                                           placeholder="VD: 2:sáng,4:chiều hoặc 2:sang,4:chieu" value="<?php echo htmlspecialchars($reviewSection['day_sessions']??''); ?>" required>
                                    <div class="form-text">Hệ thống hiểu các ca sáng, chiều, tối khi kiểm tra trùng phòng và giảng viên.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Ghi chu</label>
                                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Ghi chu them...">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success w-100"
                                            onclick="return confirm('Xac nhan duyet mo lop HP nay?')">
                                        <i class="bi bi-check-circle me-2"></i>Duyet mo lop chinh thuc
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Form tu choi -->
            <div class="col-md-5">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white"><i class="bi bi-x-circle me-2"></i>Tu choi</div>
                    <div class="card-body">
                        <form method="post" action="proposals.php">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="reject_open">
                            <input type="hidden" name="section_id" value="<?php echo $reviewSection['id']; ?>">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Ly do tu choi <span class="text-danger">*</span></label>
                                <textarea name="reject_reason" class="form-control form-control-sm" rows="4"
                                          placeholder="Nhap ly do tu choi de thong bao cho Khoa..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger w-100"
                                    onclick="return confirm('Xac nhan tu choi de xuat nay?')">
                                <i class="bi bi-x-circle me-2"></i>Tu choi de xuat
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="proposals.php" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small">Khoa</label>
                <select name="faculty_id" class="form-select form-select-sm">
                    <option value="0">-- Tat ca --</option>
                    <?php foreach ($faculties as $f): ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($f['faculty_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Hoc ky</label>
                <select name="semester_id" class="form-select form-select-sm">
                    <option value="0">-- Tat ca --</option>
                    <?php foreach ($semesters as $sm): ?>
                    <option value="<?php echo $sm['id']; ?>" <?php echo $filterSemester==$sm['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Trang thai</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="proposed" <?php echo $filterStatus==='proposed'?'selected':''; ?>>Cho duyet</option>
                    <option value="open" <?php echo $filterStatus==='open'?'selected':''; ?>>Da duyet</option>
                    <option value="cancelled" <?php echo $filterStatus==='cancelled'?'selected':''; ?>>Tu choi</option>
                    <option value="all" <?php echo $filterStatus==='all'?'selected':''; ?>>Tat ca</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
                <a href="proposals.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Bang de xuat -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-2"></i>Danh sach De xuat
            <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span>
        </span>
    </div>
    <?php if (empty($proposals)): ?>
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Khong co de xuat nao.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Mon hoc / Ma lop</th>
                    <th>Khoa</th>
                    <th>Khóa</th>
                    <th>Hoc ky</th>
                    <th>GV de xuat</th>
                    <th class="text-center">Si so DK</th>
                    <th>Hinh thuc</th>
                    <th>Trang thai</th>
                    <th>Ngay gui</th>
                    <th class="text-center">Thao tac</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($proposals as $p):
                [$sColor, $sLabel] = $statusLabels[$p['status']] ?? ['secondary', $p['status']];
            ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?php echo htmlspecialchars($p['subject_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($p['section_code']); ?> · <?php echo $p['credits']; ?> TC</small>
                </td>
                <td class="small"><?php echo htmlspecialchars($p['faculty_name']??'—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($p['cohort_code']??'—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($p['semester_name']); ?></td>
                <td class="small">
                    <?php if (!empty($p['proposed_teacher_name'])): ?>
                    <span class="fw-semibold"><?php echo htmlspecialchars($p['proposed_teacher_name']); ?></span><br>
                    <span class="text-muted"><?php echo htmlspecialchars($p['proposed_teacher_code'] ?? ''); ?></span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge bg-light text-dark"><?php echo (int)$p['expected_students']; ?></span>
                </td>
                <td class="small">
                    <?php $modeLabel=['offline'=>'Offline','online'=>'Online','hybrid'=>'Hybrid'];
                    echo $modeLabel[$p['teaching_mode']] ?? 'Offline'; ?>
                </td>
                <td>
                    <span class="badge bg-<?php echo $sColor; ?>"><?php echo $sLabel; ?></span>
                    <?php if ($p['status']==='cancelled' && $p['open_reject_reason']): ?>
                    <br><small class="text-danger"><?php echo htmlspecialchars(mb_substr($p['open_reject_reason'],0,40)); ?>...</small>
                    <?php endif; ?>
                </td>
                <td class="small text-muted">
                    <?php echo $p['open_proposed_at'] ? date('d/m/Y', strtotime($p['open_proposed_at'])) : '—'; ?>
                    <?php if ($p['proposed_by_name']): ?>
                    <br><span class="text-muted"><?php echo htmlspecialchars($p['proposed_by_name']); ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($p['status']==='proposed' && isAcademicManager()): ?>
                    <a href="proposals.php?action=review&id=<?php echo $p['id']; ?>"
                       class="btn btn-sm btn-warning">
                        <i class="bi bi-clipboard-check"></i> Duyet
                    </a>
                    <?php else: ?>
                    <a href="course_sections.php?id=<?php echo $p['id']; ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                    </a>
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
        <?php $qs2 = http_build_query(['faculty_id'=>$filterFaculty,'semester_id'=>$filterSemester,'status'=>$filterStatus]);
        echo renderAcademicPagination($pag, $qs2); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

</div><!-- /.admin-content -->
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU — Phòng Đào tạo</div>
</div>
<?php include 'includes/footer.php'; ?>
