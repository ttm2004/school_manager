<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager','faculty_staff','dept_head','faculty_lecturer']);

$pageTitle = 'Nguyen vong Giang day';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$deptId    = getDepartmentId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Tai khoan chua duoc gan vao khoa nao.'];
    header('Location: /university/login.php'); exit();
}

function teachingWishSemesterId(mysqli $conn, int $wishId): int
{
    $stmt = $conn->prepare("SELECT semester_id FROM teaching_wishes WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $wishId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['semester_id'] ?? 0);
}

function teachingWishWindowOrRedirect(mysqli $conn, int $semesterId): void
{
    $window = academicPolicyCheckFacultyProposalWindow($conn, $semesterId);
    if (!$window['ok']) {
        $_SESSION['_flash'] = ['type' => 'warning', 'message' => $window['message']];
        header('Location: teaching_wishes.php?semester_id=' . $semesterId);
        exit();
    }
}

// Lay teacher_id cua user hien tai (neu la GV)
$myTeacherId = 0;
$stmtMyT = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? AND faculty_id = ? LIMIT 1");
$stmtMyT->bind_param('ii', $userId, $facultyId);
$stmtMyT->execute();
$myTeacherRow = $stmtMyT->get_result()->fetch_assoc();
$stmtMyT->close();
if ($myTeacherRow) $myTeacherId = (int)$myTeacherRow['id'];

// Lay hoc ky active
$activeSem = getActiveSemester($conn);
$semId = (int)($_GET['semester_id'] ?? ($activeSem['id'] ?? 0));
$teachingWishWindow = $semId > 0
    ? academicPolicyCheckFacultyProposalWindow($conn, $semId)
    : ['ok' => false, 'message' => 'Vui lòng chọn học kỳ để kiểm tra thời gian Khoa/Viện được phép thao tác.'];

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // GV dang ky nguyen vong
    if ($action === 'register') {
        if ($myTeacherId <= 0) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Ban khong phai giang vien.'];
            header('Location: teaching_wishes.php'); exit();
        }
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        $priority  = max(1, min(3, (int)($_POST['priority'] ?? 2)));
        $note      = trim($_POST['note'] ?? '');

        if ($subjectId <= 0 || $semesterId <= 0) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Du lieu khong hop le.'];
            header('Location: teaching_wishes.php?semester_id='.$semesterId); exit();
        }
        teachingWishWindowOrRedirect($conn, $semesterId);

        // Kiem tra mon hoc thuoc CTDT cua khoa
        $stmtChk = $conn->prepare(
            "SELECT c.id FROM curriculum c JOIN majors m ON c.major_id = m.id
             WHERE c.subject_id = ? AND m.faculty_id = ? AND c.deleted_at IS NULL LIMIT 1"
        );
        $stmtChk->bind_param('ii', $subjectId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Mon hoc khong thuoc chuong trinh dao tao cua khoa.'];
            header('Location: teaching_wishes.php?semester_id='.$semesterId); exit();
        }
        $stmtChk->close();

        $stmtIns = $conn->prepare(
            "INSERT INTO teaching_wishes (teacher_id, subject_id, semester_id, faculty_id, department_id, priority, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE priority=VALUES(priority), note=VALUES(note), status='pending', updated_at=NOW()"
        );
        $deptIdParam = $deptId > 0 ? $deptId : null;
        $stmtIns->bind_param('iiiiiis', $myTeacherId, $subjectId, $semesterId, $facultyId, $deptIdParam, $priority, $note);
        if ($stmtIns->execute()) {
            logAudit($conn, $userId, 'create', 'faculty', 'teaching_wishes', $myTeacherId, null,
                json_encode(['subject_id'=>$subjectId,'semester_id'=>$semesterId,'priority'=>$priority]), $ip);
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Da dang ky nguyen vong giang day.'];
        } else {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Loi: '.$conn->error];
        }
        $stmtIns->close();
        header('Location: teaching_wishes.php?semester_id='.$semesterId); exit();
    }

    // Truong BM duyet/tu choi
    if ($action === 'dept_review') {
        if (!isDeptHead()) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chi Truong Bo mon moi co quyen duyet.'];
            header('Location: teaching_wishes.php'); exit();
        }
        $wishId  = (int)($_POST['wish_id'] ?? 0);
        teachingWishWindowOrRedirect($conn, teachingWishSemesterId($conn, $wishId));
        $approve = ($_POST['decision'] ?? '') === 'approve';
        $note    = trim($_POST['review_note'] ?? '');
        $newStatus = $approve ? 'dept_approved' : 'dept_rejected';

        // Kiem tra wish thuoc khoa/bo mon minh
        $stmtChk2 = $conn->prepare(
            "SELECT id FROM teaching_wishes WHERE id = ? AND faculty_id = ? LIMIT 1"
        );
        $stmtChk2->bind_param('ii', $wishId, $facultyId);
        $stmtChk2->execute();
        if ($stmtChk2->get_result()->num_rows === 0) {
            $stmtChk2->close();
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Khong co quyen duyet nguyen vong nay.'];
            header('Location: teaching_wishes.php'); exit();
        }
        $stmtChk2->close();

        $stmtUpd = $conn->prepare(
            "UPDATE teaching_wishes SET status=?, dept_reviewed_by=?, dept_reviewed_at=NOW(), dept_note=?
             WHERE id = ? AND status='pending'"
        );
        $stmtUpd->bind_param('siis', $newStatus, $userId, $note, $wishId);
        $stmtUpd->execute();
        $_SESSION['_flash'] = ['type'=>'success','message'=>$approve ? 'Da duyet nguyen vong.' : 'Da tu choi nguyen vong.'];
        $stmtUpd->close();
        header('Location: teaching_wishes.php?semester_id='.$semId); exit();
    }

    // Truong khoa duyet/tu choi
    if ($action === 'faculty_review') {
        if (!isFacultyManager()) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chi Truong khoa moi co quyen duyet.'];
            header('Location: teaching_wishes.php'); exit();
        }
        $wishId  = (int)($_POST['wish_id'] ?? 0);
        teachingWishWindowOrRedirect($conn, teachingWishSemesterId($conn, $wishId));
        $approve = ($_POST['decision'] ?? '') === 'approve';
        $note    = trim($_POST['review_note'] ?? '');
        $newStatus = $approve ? 'faculty_approved' : 'faculty_rejected';

        $stmtUpd2 = $conn->prepare(
            "UPDATE teaching_wishes SET status=?, faculty_reviewed_by=?, faculty_reviewed_at=NOW(), faculty_note=?
             WHERE id = ? AND faculty_id = ? AND status='dept_approved'"
        );
        $stmtUpd2->bind_param('siiii', $newStatus, $userId, $note, $wishId, $facultyId);
        $stmtUpd2->execute();
        $_SESSION['_flash'] = ['type'=>'success','message'=>$approve ? 'Da duyet nguyen vong len Phong DT.' : 'Da tu choi nguyen vong.'];
        $stmtUpd2->close();
        header('Location: teaching_wishes.php?semester_id='.$semId); exit();
    }

    // GV huy nguyen vong
    if ($action === 'cancel') {
        $wishId = (int)($_POST['wish_id'] ?? 0);
        teachingWishWindowOrRedirect($conn, teachingWishSemesterId($conn, $wishId));
        $stmtCan = $conn->prepare(
            "UPDATE teaching_wishes SET status='cancelled'
             WHERE id = ? AND teacher_id = ? AND status IN ('pending','dept_rejected','faculty_rejected')"
        );
        $stmtCan->bind_param('ii', $wishId, $myTeacherId);
        $stmtCan->execute();
        $_SESSION['_flash'] = ['type'=>'success','message'=>'Da huy nguyen vong.'];
        $stmtCan->close();
        header('Location: teaching_wishes.php?semester_id='.$semId); exit();
    }
}

$flash = getFlash();

// Lay danh sach hoc ky
$semesters = $conn->query("SELECT id, semester_name, status FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Lay danh sach mon hoc thuoc CTDT cua khoa (de GV chon)
$subjects = [];
$chkCurr = $conn->query("SHOW TABLES LIKE 'curriculum'");
if ($chkCurr && $chkCurr->num_rows > 0) {
    $stmtSubj = $conn->prepare(
        "SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.credits
         FROM subjects s
         JOIN curriculum c ON s.id = c.subject_id
         JOIN majors m ON c.major_id = m.id
         WHERE m.faculty_id = ? AND c.deleted_at IS NULL
         ORDER BY s.subject_name ASC"
    );
    $stmtSubj->bind_param('i', $facultyId);
    $stmtSubj->execute();
    $subjects = $stmtSubj->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSubj->close();
}

// Lay danh sach nguyen vong theo hoc ky
$wishes = [];
if ($semId > 0) {
    $chkTW = $conn->query("SHOW TABLES LIKE 'teaching_wishes'");
    if ($chkTW && $chkTW->num_rows > 0) {
        // GV thuong chi xem nguyen vong cua minh
        // Truong BM/Truong khoa xem tat ca trong khoa
        if (isFacultyManager() || isDeptHead()) {
            $stmtW = $conn->prepare(
                "SELECT tw.*, s.subject_code, s.subject_name, s.credits,
                        u.full_name AS teacher_name, t.teacher_code, t.degree,
                        d.department_name,
                        ur.full_name AS dept_reviewer_name,
                        uf.full_name AS faculty_reviewer_name
                 FROM teaching_wishes tw
                 JOIN subjects s ON tw.subject_id = s.id
                 JOIN teachers t ON tw.teacher_id = t.id
                 JOIN users u ON t.user_id = u.id
                 LEFT JOIN departments d ON tw.department_id = d.id
                 LEFT JOIN users ur ON tw.dept_reviewed_by = ur.id
                 LEFT JOIN users uf ON tw.faculty_reviewed_by = uf.id
                 WHERE tw.faculty_id = ? AND tw.semester_id = ?
                 ORDER BY tw.priority ASC, u.full_name ASC"
            );
            $stmtW->bind_param('ii', $facultyId, $semId);
        } else {
            $stmtW = $conn->prepare(
                "SELECT tw.*, s.subject_code, s.subject_name, s.credits,
                        u.full_name AS teacher_name, t.teacher_code, t.degree,
                        d.department_name,
                        ur.full_name AS dept_reviewer_name,
                        uf.full_name AS faculty_reviewer_name
                 FROM teaching_wishes tw
                 JOIN subjects s ON tw.subject_id = s.id
                 JOIN teachers t ON tw.teacher_id = t.id
                 JOIN users u ON t.user_id = u.id
                 LEFT JOIN departments d ON tw.department_id = d.id
                 LEFT JOIN users ur ON tw.dept_reviewed_by = ur.id
                 LEFT JOIN users uf ON tw.faculty_reviewed_by = uf.id
                 WHERE tw.faculty_id = ? AND tw.semester_id = ? AND tw.teacher_id = ?
                 ORDER BY tw.priority ASC"
            );
            $stmtW->bind_param('iii', $facultyId, $semId, $myTeacherId);
        }
        $stmtW->execute();
        $wishes = $stmtW->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtW->close();
    }
}

// Kiem tra bang ton tai
$tableExists = $conn->query("SHOW TABLES LIKE 'teaching_wishes'")->num_rows > 0;

$statusLabels = [
    'pending'          => ['Cho BM duyet',    'warning'],
    'dept_approved'    => ['BM da duyet',      'info'],
    'dept_rejected'    => ['BM tu choi',       'danger'],
    'faculty_approved' => ['Khoa da duyet',    'success'],
    'faculty_rejected' => ['Khoa tu choi',     'danger'],
    'confirmed'        => ['Da xac nhan',      'success'],
    'cancelled'        => ['Da huy',           'secondary'],
];
$priorityLabels = [1=>'Uu tien cao', 2=>'Binh thuong', 3=>'Thap'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-hand-index-fill me-2 text-navy"></i>Nguyen vong Giang day</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
        <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Dang xuat</a>
    </div>
</div>
<div class="admin-content">

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3">
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$teachingWishWindow['ok']): ?>
<div class="alert alert-warning mb-3">
    <i class="bi bi-lock-fill me-2"></i>
    <?php echo htmlspecialchars($teachingWishWindow['message']); ?>
</div>
<?php endif; ?>

<?php if (!$tableExists): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Chua chay migration. Vui long chay file <code>faculty_upgrade_migration.sql</code> truoc.
</div>
<?php else: ?>

<!-- Chon hoc ky + Dang ky nguyen vong (GV) -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Chon hoc ky</div>
            <div class="card-body">
                <form method="get" action="teaching_wishes.php">
                    <select name="semester_id" class="form-select mb-2" onchange="this.form.submit()">
                        <option value="0">-- Chon hoc ky --</option>
                        <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem['id']; ?>" <?php echo $semId==(int)$sem['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($sem['semester_name']); ?>
                            <?php if ($sem['status']==='active'): ?>(Hien tai)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>

    <?php if ($myTeacherId > 0 && $semId > 0 && $teachingWishWindow['ok']): ?>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Dang ky nguyen vong</div>
            <div class="card-body">
                <form method="post" action="teaching_wishes.php?semester_id=<?php echo $semId; ?>" class="row g-2">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="semester_id" value="<?php echo $semId; ?>">
                    <div class="col-md-5">
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- Chon mon hoc --</option>
                            <?php foreach ($subjects as $subj): ?>
                            <option value="<?php echo $subj['id']; ?>">
                                <?php echo htmlspecialchars($subj['subject_code'].' - '.$subj['subject_name'].' ('.$subj['credits'].'TC)'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="priority" class="form-select">
                            <option value="1">Uu tien cao</option>
                            <option value="2" selected>Binh thuong</option>
                            <option value="3">Thap</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="note" class="form-control" placeholder="Ghi chu...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-navy w-100"><i class="bi bi-send me-1"></i>Dang ky</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Bang nguyen vong -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-2"></i>Danh sach Nguyen vong
            <span class="badge bg-light text-dark ms-1"><?php echo count($wishes); ?></span>
        </span>
    </div>
    <?php if (empty($wishes)): ?>
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        <?php echo $semId > 0 ? 'Chua co nguyen vong nao trong hoc ky nay.' : 'Vui long chon hoc ky.'; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Giang vien</th>
                    <th>Mon hoc</th>
                    <th>TC</th>
                    <th>Uu tien</th>
                    <th>Trang thai</th>
                    <th>Ghi chu</th>
                    <th class="text-center">Thao tac</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($wishes as $w):
                [$statusLabel, $statusColor] = $statusLabels[$w['status']] ?? [$w['status'], 'secondary'];
                $canDeptReview    = $teachingWishWindow['ok'] && isDeptHead() && $w['status'] === 'pending';
                $canFacultyReview = $teachingWishWindow['ok'] && isFacultyManager() && $w['status'] === 'dept_approved';
                $canCancel        = $myTeacherId === (int)$w['teacher_id']
                                    && $teachingWishWindow['ok']
                                    && in_array($w['status'], ['pending','dept_rejected','faculty_rejected']);
            ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?php echo htmlspecialchars($w['teacher_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($w['teacher_code']); ?></small>
                    <?php if ($w['department_name']): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($w['department_name']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="small"><?php echo htmlspecialchars($w['subject_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($w['subject_code']); ?></small>
                </td>
                <td class="text-center"><span class="badge bg-light text-dark"><?php echo $w['credits']; ?></span></td>
                <td>
                    <?php $pColor = $w['priority']==1?'danger':($w['priority']==2?'warning':'secondary'); ?>
                    <span class="badge bg-<?php echo $pColor; ?>"><?php echo $priorityLabels[$w['priority']]??''; ?></span>
                </td>
                <td><span class="badge bg-<?php echo $statusColor; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                    <?php if ($w['dept_note'] || $w['faculty_note']): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($w['dept_note'] ?: $w['faculty_note']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="small text-muted"><?php echo htmlspecialchars($w['note']??''); ?></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                    <?php if ($canDeptReview): ?>
                    <form method="post" class="d-inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="dept_review">
                        <input type="hidden" name="wish_id" value="<?php echo $w['id']; ?>">
                        <input type="hidden" name="decision" value="approve">
                        <button class="btn btn-sm btn-outline-success" title="BM Duyet"><i class="bi bi-check-lg"></i></button>
                    </form>
                    <form method="post" class="d-inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="dept_review">
                        <input type="hidden" name="wish_id" value="<?php echo $w['id']; ?>">
                        <input type="hidden" name="decision" value="reject">
                        <button class="btn btn-sm btn-outline-danger" title="BM Tu choi"><i class="bi bi-x-lg"></i></button>
                    </form>
                    <?php endif; ?>
                    <?php if ($canFacultyReview): ?>
                    <form method="post" class="d-inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="faculty_review">
                        <input type="hidden" name="wish_id" value="<?php echo $w['id']; ?>">
                        <input type="hidden" name="decision" value="approve">
                        <button class="btn btn-sm btn-success" title="Khoa Duyet"><i class="bi bi-check-circle"></i></button>
                    </form>
                    <form method="post" class="d-inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="faculty_review">
                        <input type="hidden" name="wish_id" value="<?php echo $w['id']; ?>">
                        <input type="hidden" name="decision" value="reject">
                        <button class="btn btn-sm btn-outline-danger" title="Khoa Tu choi"><i class="bi bi-x-circle"></i></button>
                    </form>
                    <?php endif; ?>
                    <?php if ($canCancel): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Huy nguyen vong?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="wish_id" value="<?php echo $w['id']; ?>">
                        <button class="btn btn-sm btn-outline-secondary" title="Huy"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
