<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../includes/teacher_assignment_rules.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Đề xuất Mở lớp & Phân công GV';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

function facultyProposalWindowOrRedirect(mysqli $conn, int $semesterId, string $redirect = 'proposals.php'): void
{
    $window = academicPolicyCheckFacultyProposalWindow($conn, $semesterId);
    if (!$window['ok']) {
        $_SESSION['_flash'] = ['type' => 'warning', 'message' => $window['message']];
        header('Location: ' . $redirect);
        exit();
    }
}

function facultyProposalSectionSemester(mysqli $conn, int $sectionId): int
{
    $stmt = $conn->prepare("SELECT semester_id FROM course_sections WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['semester_id'] ?? 0);
}

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isFacultyManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
        header('Location: proposals.php');
        exit();
    }

    $action = trim($_POST['action'] ?? '');

    // CREATE DRAFT (mo lop)
    if ($action === 'create_draft') {
        $subjectId   = (int)($_POST['subject_id'] ?? 0);
        $semesterId  = (int)($_POST['semester_id'] ?? 0);
        $cohortId    = (int)($_POST['cohort_id'] ?? 0);
        $expStudents = (int)($_POST['expected_students'] ?? 0);
        $sectionName = trim($_POST['section_name'] ?? '');
        $daySessions = trim($_POST['day_sessions'] ?? '');
        $note        = trim($_POST['open_proposal_note'] ?? '');

        if ($subjectId <= 0 || $semesterId <= 0 || $cohortId <= 0 || $expStudents <= 0 || $sectionName === '' || $daySessions === '') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng điền đầy đủ thông tin.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, $semesterId, 'proposals.php?tab=open');

        // Kiem tra semester hop le
        $stmtSemChk = $conn->prepare("SELECT id FROM semesters WHERE id = ? AND status IN ('active','upcoming','open') LIMIT 1");
        $stmtSemChk->bind_param('i', $semesterId);
        $stmtSemChk->execute();
        if ($stmtSemChk->get_result()->num_rows === 0) {
            $stmtSemChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Học kỳ không hợp lệ.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtSemChk->close();

        $proposalWindow = academicPolicyCheckProposalWindow($conn, $semesterId);
        if (!$proposalWindow['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $proposalWindow['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }

        $policyCheck = academicPolicyValidateSubjectOpening($conn, $facultyId, $subjectId, $semesterId, $cohortId);
        if (!$policyCheck['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $policyCheck['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }

        // Kiem tra duplicate
        $stmtDup = $conn->prepare(
            "SELECT id FROM course_sections WHERE subject_id = ? AND semester_id = ? AND status IN ('proposed','open','draft') LIMIT 1"
        );
        $stmtDup->bind_param('ii', $subjectId, $semesterId);
        $stmtDup->execute();
        if ($stmtDup->get_result()->num_rows > 0) {
            $stmtDup->close();
            $_SESSION['_flash'] = ['type' => 'warning', 'message' => 'Đã có đề xuất hoặc lớp học phần cho môn này trong học kỳ được chọn.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtDup->close();

        $stmtIns = $conn->prepare(
            "INSERT INTO course_sections (subject_id, semester_id, target_cohort_id, section_code, status, expected_students, max_students, day_sessions, open_proposal_note, open_proposed_by)
             VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?)"
        );
        $stmtIns->bind_param('iiisiissi', $subjectId, $semesterId, $cohortId, $sectionName, $expStudents, $expStudents, $daySessions, $note, $userId);
        $stmtIns->execute();
        $newId = (int)$conn->insert_id;
        $stmtIns->close();

        logAudit($conn, $userId, 'create', 'faculty', 'course_sections', $newId, null,
            json_encode(['subject_id' => $subjectId, 'semester_id' => $semesterId, 'status' => 'draft']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã tạo đề xuất nháp. Vui lòng gửi đề xuất khi sẵn sàng.'];
        header('Location: proposals.php?tab=open');
        exit();
    }

    // SUBMIT (draft -> pending)
    if ($action === 'submit') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=open');

        // Kiem tra ownership va status
        $stmtChk = $conn->prepare(
            "SELECT cs.id, cs.status FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? AND cs.status = 'draft' LIMIT 1"
        );
        $stmtChk->bind_param('ii', $sectionId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Đề xuất không hợp lệ hoặc đã được gửi.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtChk->close();

        $stmtSec = $conn->prepare("SELECT semester_id FROM course_sections WHERE id = ? LIMIT 1");
        $stmtSec->bind_param('i', $sectionId);
        $stmtSec->execute();
        $secSem = $stmtSec->get_result()->fetch_assoc();
        $stmtSec->close();
        $proposalWindow = academicPolicyCheckProposalWindow($conn, (int)($secSem['semester_id'] ?? 0));
        if (!$proposalWindow['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $proposalWindow['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections SET status = 'proposed', open_proposed_at = NOW(), open_proposed_by = ? WHERE id = ?"
        );
        $stmtUpd->bind_param('ii', $userId, $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'submit', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['status' => 'proposed']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã gửi đề xuất mở lớp. Chờ Phòng Đào tạo duyệt.'];
        header('Location: proposals.php?tab=open');
        exit();
    }

    // CANCEL (pending -> cancelled)
    if ($action === 'cancel') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=open');

        $stmtChk = $conn->prepare(
            "SELECT cs.id FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? AND cs.status = 'proposed' LIMIT 1"
        );
        $stmtChk->bind_param('ii', $sectionId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ có thể hủy đề xuất đang chờ duyệt.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtChk->close();

        $stmtUpd = $conn->prepare("UPDATE course_sections SET status = 'cancelled' WHERE id = ?");
        $stmtUpd->bind_param('i', $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'update', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['status' => 'cancelled']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã hủy đề xuất.'];
        header('Location: proposals.php?tab=open');
        exit();
    }

    // PROPOSE TEACHER
    if ($action === 'propose_teacher') {
        $sectionId  = (int)($_POST['section_id'] ?? 0);
        $teacherId  = (int)($_POST['proposed_teacher_id'] ?? 0);
        $propNote   = trim($_POST['proposal_note'] ?? '');

        if ($sectionId <= 0 || $teacherId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=assign');

        // Kiem tra section chua duoc duyet
        $stmtSChk = $conn->prepare(
            "SELECT cs.id, cs.proposal_status FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? LIMIT 1"
        );
        $stmtSChk->bind_param('ii', $sectionId, $facultyId);
        $stmtSChk->execute();
        $secRow = $stmtSChk->get_result()->fetch_assoc();
        $stmtSChk->close();

        if (!$secRow) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lớp học phần không hợp lệ.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        if ($secRow['proposal_status'] === 'approved') {
            $_SESSION['_flash'] = ['type' => 'warning', 'message' => 'Phân công đã được Phòng Đào tạo phê duyệt.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }

        $assignmentCheck = validateTeacherAssignmentForSection($conn, $teacherId, $sectionId);
        if (!$assignmentCheck['ok']) {
            // Giữ thông điệp cũ cho luồng khoa: chỉ được đề xuất giảng viên thuộc khoa mình.
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $assignmentCheck['message']];
            header('Location: proposals.php?tab=assign');
            exit();
        }

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections SET proposed_teacher_id = ?, proposal_status = 'pending',
             proposal_note = ?, proposed_by = ?, proposed_at = NOW() WHERE id = ?"
        );
        $stmtUpd->bind_param('isii', $teacherId, $propNote, $userId, $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'submit', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['proposed_teacher_id' => $teacherId, 'proposal_status' => 'pending']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã gửi đề xuất phân công giảng viên.'];
        header('Location: proposals.php?tab=assign');
        exit();
    }

    // CANCEL TEACHER PROPOSAL
    if ($action === 'cancel_teacher_proposal') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=assign');

        $stmtChk = $conn->prepare(
            "SELECT cs.id FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? AND cs.proposal_status = 'pending' LIMIT 1"
        );
        $stmtChk->bind_param('ii', $sectionId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ có thể hủy đề xuất đang chờ duyệt.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        $stmtChk->close();

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections SET proposed_teacher_id = NULL, proposal_status = NULL, proposal_note = NULL WHERE id = ?"
        );
        $stmtUpd->bind_param('i', $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'update', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['proposal_status' => null]), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã hủy đề xuất phân công giảng viên.'];
        header('Location: proposals.php?tab=assign');
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hành động không hợp lệ.'];
    header('Location: proposals.php');
    exit();
}

// GET Handler
$flash      = getFlash();
$tab        = trim($_GET['tab'] ?? 'open'); // open | assign
$statusFilter = trim($_GET['status'] ?? '');

$semesters = [];
$stmtSem = $conn->prepare("SELECT id, semester_name, school_year, status FROM semesters ORDER BY id DESC");
$stmtSem->execute();
$semesters = $stmtSem->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSem->close();

$activeSemester = getActiveSemester($conn);
$selectedSemId  = (int)($_GET['semester_id'] ?? ($activeSemester['id'] ?? 0));
if ($selectedSemId <= 0 && !empty($semesters)) {
    foreach ($semesters as $sem) {
        if (in_array($sem['status'], ['active', 'upcoming', 'open'], true)) {
            $selectedSemId = (int)$sem['id'];
            break;
        }
    }
    if ($selectedSemId <= 0) {
        $selectedSemId = (int)$semesters[0]['id'];
    }
}
$facultyProposalWindow = $selectedSemId > 0
    ? academicPolicyCheckFacultyProposalWindow($conn, $selectedSemId)
    : ['ok' => false, 'message' => 'Vui lòng chọn học kỳ để kiểm tra thời gian Khoa/Viện được phép thao tác.'];

// Lay de xuat mo lop
$openProposals = [];
$stmtOpen = $conn->prepare(
    "SELECT cs.id, cs.section_code, cs.status, cs.max_students,
            cs.open_proposal_note, cs.open_proposed_at, cs.day_sessions,
            s.subject_name, s.subject_code,
            sem.semester_name,
            tc.cohort_code, tc.cohort_name,
            u.full_name AS proposed_by_name
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id = s.id
     JOIN semesters sem ON cs.semester_id = sem.id
     LEFT JOIN training_cohorts tc ON cs.target_cohort_id = tc.id
     LEFT JOIN users u ON cs.open_proposed_by = u.id
     WHERE cs.open_proposed_by = ? AND cs.status IN ('draft','proposed','open','cancelled')
     ORDER BY cs.open_proposed_at DESC, cs.id DESC"
);
$stmtOpen->bind_param('i', $userId);
$stmtOpen->execute();
$openProposals = $stmtOpen->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtOpen->close();

// Lay de xuat phan cong GV
$assignProposals = [];
$stmtAssign = $conn->prepare(
    "SELECT cs.id, cs.section_code, cs.status, cs.proposal_status,
            cs.proposal_note, cs.proposed_at, cs.proposal_reject_reason,
            s.subject_name, s.subject_code,
            sem.semester_name,
            pt.teacher_code AS proposed_teacher_code,
            pu.full_name AS proposed_teacher_name,
            at2.teacher_code AS assigned_teacher_code,
            au.full_name AS assigned_teacher_name,
            pb.full_name AS proposed_by_name
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id = s.id
     JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
     JOIN majors m ON cur.major_id = m.id
     JOIN semesters sem ON cs.semester_id = sem.id
     LEFT JOIN teachers pt ON cs.proposed_teacher_id = pt.id
     LEFT JOIN users pu ON pt.user_id = pu.id
     LEFT JOIN teachers at2 ON cs.teacher_id = at2.id
     LEFT JOIN users au ON at2.user_id = au.id
     LEFT JOIN users pb ON cs.proposed_by = pb.id
     WHERE m.faculty_id = ? AND cs.semester_id = ?
     ORDER BY cs.proposed_at DESC, cs.id DESC"
);
$stmtAssign->bind_param('ii', $facultyId, $selectedSemId);
$stmtAssign->execute();
$assignProposals = $stmtAssign->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAssign->close();

// Lay danh sach mon hoc dung CTDT/hoc ky/khoa tuyen sinh cua khoa
$facultySubjects = [];
$facultySubjects = $selectedSemId > 0
    ? academicPolicyFindEligibleOpenings($conn, $facultyId, $selectedSemId)
    : [];

$facultyCohorts = [];
$stmtCohorts = $conn->prepare(
    "SELECT tc.id, tc.cohort_code, tc.cohort_name, tc.enrollment_year, m.major_name
     FROM training_cohorts tc
     JOIN majors m ON tc.major_id = m.id
     WHERE m.faculty_id = ? AND tc.status IN ('planned','active')
     ORDER BY tc.enrollment_year DESC, m.major_name ASC"
);
$stmtCohorts->bind_param('i', $facultyId);
$stmtCohorts->execute();
$facultyCohorts = $stmtCohorts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCohorts->close();

// Lay danh sach GV trong khoa
$facultyTeachers = [];
$stmtFT = $conn->prepare(
    "SELECT t.id, t.teacher_code, u.full_name,
            COALESCE(SUM(s2.credits), 0) AS current_load
     FROM teachers t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN course_sections cs2 ON cs2.teacher_id = t.id AND cs2.semester_id = ? AND cs2.status IN ('open','closed')
     LEFT JOIN subjects s2 ON cs2.subject_id = s2.id
     WHERE t.faculty_id = ?
     GROUP BY t.id, t.teacher_code, u.full_name
     ORDER BY u.full_name ASC"
);
$stmtFT->bind_param('ii', $selectedSemId, $facultyId);
$stmtFT->execute();
$facultyTeachers = $stmtFT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtFT->close();

$statusBadgeMap = [
    'draft'     => ['secondary', 'Nháp'],
    'proposed'  => ['warning', 'Chờ duyệt'],
    'open'      => ['success', 'Đã duyệt'],
    'cancelled' => ['dark', 'Đã hủy'],
    'closed'    => ['info', 'Đã đóng'],
];
$propStatusBadgeMap = [
    'pending'  => ['warning', 'Chờ duyệt'],
    'approved' => ['success', 'Đã duyệt'],
    'rejected' => ['danger', 'Từ chối'],
];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mo/dong menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-send-fill me-2 text-navy" aria-hidden="true"></i>Đề xuất Mở lớp & Phân công GV
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if (isFacultyManager() && $facultyProposalWindow['ok']): ?>
            <?php if ($tab === 'open'): ?>
            <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#createDraftModal"
                    aria-label="Tạo đề xuất mở lớp mới">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tạo đề xuất
            </button>
            <?php endif; ?>
            <?php endif; ?>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Dang xuat
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php endif; ?>

        <?php if (!$facultyProposalWindow['ok']): ?>
        <div class="alert alert-warning mb-4" role="alert">
            <i class="bi bi-lock-fill me-2" aria-hidden="true"></i>
            <?php echo htmlspecialchars($facultyProposalWindow['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'open' ? 'active' : ''; ?>"
                   href="proposals.php?tab=open" role="tab" aria-selected="<?php echo $tab === 'open' ? 'true' : 'false'; ?>">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Đề xuất Mở lớp
                    <span class="badge bg-warning ms-1"><?php echo count(array_filter($openProposals, fn($p) => $p['status'] === 'proposed')); ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'assign' ? 'active' : ''; ?>"
                   href="proposals.php?tab=assign&semester_id=<?php echo $selectedSemId; ?>" role="tab"
                   aria-selected="<?php echo $tab === 'assign' ? 'true' : 'false'; ?>">
                    <i class="bi bi-person-check me-1" aria-hidden="true"></i>Đề xuất Phân công GV
                    <span class="badge bg-warning ms-1"><?php echo count(array_filter($assignProposals, fn($p) => $p['proposal_status'] === 'pending')); ?></span>
                </a>
            </li>
        </ul>

        <?php if ($tab === 'open'): ?>
        <!-- Open proposals tab -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-check me-2" aria-hidden="true"></i>Danh sách Đề xuất Mở lớp
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã lớp</th>
                            <th>Môn học</th>
                            <th>Học kỳ</th>
                            <th>Khoa</th>
                            <th class="text-center">Dự kiến SV</th>
                            <th>Lịch học</th>
                            <th>Trạng thái</th>
                            <th>Ngày gửi</th>
                            <th>Ghi chú</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($openProposals)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Chưa có đề xuất nào.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($openProposals as $p): ?>
                        <?php $sb = $statusBadgeMap[$p['status']] ?? ['secondary', $p['status']]; ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($p['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['semester_name']); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['cohort_code'] ?? '—'); ?></td>
                            <td class="text-center"><?php echo (int)$p['max_students']; ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['day_sessions'] ?? '—'); ?></td>
                            <td><span class="badge bg-<?php echo $sb[0]; ?>"><?php echo $sb[1]; ?></span></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['open_proposed_at'] ?? '—'); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars(mb_substr($p['open_proposal_note'] ?? '', 0, 50)); ?></td>
                            <td>
                                <?php if (isFacultyManager() && $facultyProposalWindow['ok']): ?>
                                <?php if ($p['status'] === 'draft'): ?>
                                <form method="post" action="proposals.php" class="d-inline">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="submit">
                                    <input type="hidden" name="section_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning"
                                            aria-label="Gửi đề xuất <?php echo htmlspecialchars($p['section_code']); ?>">
                                        <i class="bi bi-send" aria-hidden="true"></i> Gửi
                                    </button>
                                </form>
                                <?php elseif ($p['status'] === 'proposed'): ?>
                                <form method="post" action="proposals.php" class="d-inline"
                                      onsubmit="return confirm('Hủy đề xuất này?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="section_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            aria-label="Hủy đề xuất <?php echo htmlspecialchars($p['section_code']); ?>">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i> Hủy
                                    </button>
                                </form>
                                <?php elseif ($p['status'] === 'open'): ?>
                                <span class="badge bg-success">Đã được duyệt</span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- Assign proposals tab -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="proposals.php" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="assign">
                    <div class="col-12 col-md-4">
                        <label for="semester_id" class="form-label">Học kỳ</label>
                        <select id="semester_id" name="semester_id" class="form-select">
                            <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo (int)$sem['id']; ?>"
                                <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name']); ?>
                                <?php if ($sem['status'] === 'active'): ?>(Hiện tại)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Xem đề xuất phân công">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-check me-2" aria-hidden="true"></i>Đề xuất Phân công Giảng viên
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã lớp</th>
                            <th>Môn học</th>
                            <th>GV đề xuất</th>
                            <th>GV chính thức</th>
                            <th>Trạng thái</th>
                            <th>Lý do từ chối</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignProposals)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Không có lớp học phần nào trong học kỳ này.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($assignProposals as $p): ?>
                        <?php $psb = $propStatusBadgeMap[$p['proposal_status']] ?? ['light', 'Chưa đề xuất']; ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($p['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['subject_name']); ?></td>
                            <td>
                                <?php if ($p['proposed_teacher_name']): ?>
                                <?php echo htmlspecialchars($p['proposed_teacher_name']); ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($p['proposed_teacher_code']); ?>)</small>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['assigned_teacher_name']): ?>
                                <?php echo htmlspecialchars($p['assigned_teacher_name']); ?>
                                <?php else: ?>
                                <span class="text-muted">Chưa phân công</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $psb[0]; ?>"><?php echo $psb[1]; ?></span>
                            </td>
                            <td class="text-muted small">
                                <?php echo htmlspecialchars($p['proposal_reject_reason'] ?? '—'); ?>
                            </td>
                            <td>
                                <?php if (isFacultyManager() && $facultyProposalWindow['ok']): ?>
                                <?php if ($p['proposal_status'] === 'approved'): ?>
                                <span class="text-muted small">Đã duyệt</span>
                                <?php elseif ($p['proposal_status'] === 'pending'): ?>
                                <form method="post" action="proposals.php" class="d-inline"
                                      onsubmit="return confirm('Hủy đề xuất phân công này?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="cancel_teacher_proposal">
                                    <input type="hidden" name="section_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            aria-label="Hủy đề xuất phân công lớp <?php echo htmlspecialchars($p['section_code']); ?>">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-navy"
                                        data-bs-toggle="modal" data-bs-target="#propTeacherModal"
                                        data-section-id="<?php echo (int)$p['id']; ?>"
                                        data-section-code="<?php echo htmlspecialchars($p['section_code']); ?>"
                                        aria-label="Đề xuất giảng viên cho lớp <?php echo htmlspecialchars($p['section_code']); ?>">
                                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div>

<?php if (isFacultyManager()): ?>
<!-- Create Draft Modal -->
<div class="modal fade" id="createDraftModal" tabindex="-1" aria-labelledby="createDraftModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="proposals.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create_draft">
                <div class="modal-header">
                    <h5 class="modal-title" id="createDraftModalLabel">Tạo Đề xuất Mở lớp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cd_subject" class="form-label">Môn học <span class="text-danger">*</span></label>
                        <select id="cd_subject" name="subject_id" class="form-select" required>
                            <option value="">-- Chọn môn học --</option>
                            <?php foreach ($facultySubjects as $subj): ?>
                            <option value="<?php echo (int)$subj['subject_id']; ?>">
                                <?php echo htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name'] . ' (' . $subj['credits'] . ' TC, ' . $subj['major_code'] . ', khoa ' . $subj['enrollment_year'] . '-' . $subj['graduation_year'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($facultySubjects)): ?>
                        <div class="form-text text-danger">Học kỳ đang chọn chưa có môn phù hợp với CTĐT và khóa tuyển sinh.</div>
                        <?php else: ?>
                        <div class="form-text">Danh sách môn được lọc theo CTĐT, học kỳ và khóa tuyển sinh đang chọn.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="cd_semester" class="form-label">Học kỳ <span class="text-danger">*</span></label>
                        <select id="cd_semester" name="semester_id" class="form-select" required>
                            <?php foreach ($semesters as $sem): ?>
                            <?php if (!in_array($sem['status'], ['active', 'upcoming', 'open'], true)) continue; ?>
                            <option value="<?php echo (int)$sem['id']; ?>" <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' ' . ($sem['school_year'] ?? '')); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Đổi học kỳ tại đây rồi mở lại form để xem đúng danh sách môn gợi ý.</div>
                    </div>
                    <div class="mb-3">
                        <label for="cd_cohort" class="form-label">Khóa tuyển sinh <span class="text-danger">*</span></label>
                        <select id="cd_cohort" name="cohort_id" class="form-select" required>
                            <option value="">-- Chọn khóa/ngành --</option>
                            <?php foreach ($facultyCohorts as $cohort): ?>
                            <option value="<?php echo (int)$cohort['id']; ?>">
                                <?php echo htmlspecialchars($cohort['cohort_code'] . ' - ' . $cohort['major_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="cd_section_name" class="form-label">Mã lớp học phần <span class="text-danger">*</span></label>
                            <input type="text" id="cd_section_name" name="section_name" class="form-control" required
                                   placeholder="VD: CS101.01">
                        </div>
                        <div class="col-6">
                            <label for="cd_exp_students" class="form-label">Dự kiến SV <span class="text-danger">*</span></label>
                            <input type="number" id="cd_exp_students" name="expected_students" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="cd_day_sessions" class="form-label">Lịch học <span class="text-danger">*</span></label>
                        <input type="text" id="cd_day_sessions" name="day_sessions" class="form-control" required
                               placeholder="VD: 2:sáng,4:chiều hoặc 2:sang,4:chieu">
                        <div class="form-text">Khoa/Viện chỉ đề xuất lịch học; Phòng Đào tạo sẽ kiểm tra và xếp phòng trống, phù hợp với môn.</div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="cd_note" class="form-label">Ghi chú</label>
                        <textarea id="cd_note" name="open_proposal_note" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tạo nháp
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Propose Teacher Modal -->
<div class="modal fade" id="propTeacherModal" tabindex="-1" aria-labelledby="propTeacherModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="proposals.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="propose_teacher">
                <input type="hidden" name="section_id" id="pt_section_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="propTeacherModalLabel">Đề xuất Phân công Giảng viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted" id="pt_section_code"></p>
                    <div class="mb-3">
                        <label for="pt_teacher" class="form-label">Giảng viên <span class="text-danger">*</span></label>
                        <select id="pt_teacher" name="proposed_teacher_id" class="form-select" required>
                            <option value="">-- Chọn giảng viên --</option>
                            <?php foreach ($facultyTeachers as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>">
                                <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ') — ' . $t['current_load'] . ' TC hiện tại'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="pt_note" class="form-label">Ghi chú</label>
                        <textarea id="pt_note" name="proposal_note" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Gửi đề xuất
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('propTeacherModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('pt_section_id').value = btn.dataset.sectionId;
    document.getElementById('pt_section_code').textContent = 'Lớp: ' + btn.dataset.sectionCode;
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
