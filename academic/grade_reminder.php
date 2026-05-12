<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Nhac nhap Diem';
$userId    = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yeu cau khong hop le.'];
        header('Location: grade_reminder.php'); exit();
    }
    $action    = trim($_POST['action'] ?? '');
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $sectionId = (int)($_POST['section_id'] ?? 0);

    // Nhac 1 GV
    if ($action === 'remind_one' && $teacherId > 0) {
        $info = $conn->query("SELECT u.id AS user_id, u.full_name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.id=$teacherId LIMIT 1")->fetch_assoc();
        if ($info) {
            $title = 'Nhac nho: Vui long nhap diem day du';
            $content = 'Phong Dao tao nhac ban kiem tra va nhap day du diem cho cac lop HP chua co diem. Han nop diem da qua.';
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, content) VALUES (?,?,?)");
            $stmtN->bind_param('iss', $info['user_id'], $title, $content);
            $stmtN->execute(); $stmtN->close();
            sendAcademicNotification($conn, $userId, 'grade_reminder', $title, $content, null, $teacherId);
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Da gui nhac nho den '.htmlspecialchars($info['full_name']).'.'];
        }
        header('Location: grade_reminder.php'); exit();
    }

    // Nhac tat ca GV chua nhap du diem
    if ($action === 'remind_all') {
        $filterSemId = (int)($_POST['semester_id'] ?? 0);
        $stmtGV = $conn->prepare(
            "SELECT DISTINCT t.id AS teacher_id, t.user_id, u.full_name
             FROM course_sections cs
             JOIN teachers t ON cs.teacher_id = t.id
             JOIN users u ON t.user_id = u.id
             JOIN student_subjects ss ON ss.course_section_id = cs.id AND ss.status='registered'
             LEFT JOIN grades g ON g.student_subject_id = ss.id
             WHERE cs.semester_id = ? AND cs.status IN ('open','closed')
               AND (g.id IS NULL OR g.final_score IS NULL)"
        );
        $stmtGV->bind_param('i', $filterSemId);
        $stmtGV->execute();
        $gvList = $stmtGV->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtGV->close();

        $count = 0;
        $title = 'Nhac nho: Vui long nhap diem day du';
        $content = 'Phong Dao tao nhac ban kiem tra va nhap day du diem cho cac lop HP chua co diem.';
        $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, content) VALUES (?,?,?)");
        foreach ($gvList as $gv) {
            $stmtN->bind_param('iss', $gv['user_id'], $title, $content);
            $stmtN->execute();
            $count++;
        }
        $stmtN->close();
        $_SESSION['_flash'] = ['type'=>'success','message'=>"Da gui nhac nho den $count giang vien."];
        header('Location: grade_reminder.php'); exit();
    }
}

$flash = getFlash();
$filterSem = (int)($_GET['semester_id'] ?? 0);
if ($filterSem === 0) {
    $activeSem = getActiveSemesterAcademic($conn);
    if ($activeSem) $filterSem = (int)$activeSem['id'];
}

// Lay danh sach GV chua nhap du diem
$missingGrades = [];
if ($filterSem > 0) {
    $stmtMiss = $conn->prepare(
        "SELECT t.id AS teacher_id, t.teacher_code, t.degree,
                u.full_name AS teacher_name, u.email,
                f.faculty_name,
                COUNT(DISTINCT cs.id) AS section_count,
                SUM(CASE WHEN g.final_score IS NULL THEN 1 ELSE 0 END) AS missing_count,
                COUNT(ss.id) AS total_students
         FROM course_sections cs
         JOIN teachers t ON cs.teacher_id = t.id
         JOIN users u ON t.user_id = u.id
         LEFT JOIN faculties f ON t.faculty_id = f.id
         JOIN student_subjects ss ON ss.course_section_id = cs.id AND ss.status='registered'
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE cs.semester_id = ? AND cs.status IN ('open','closed')
         GROUP BY t.id, t.teacher_code, t.degree, u.full_name, u.email, f.faculty_name
         HAVING missing_count > 0
         ORDER BY missing_count DESC, u.full_name ASC"
    );
    $stmtMiss->bind_param('i', $filterSem);
    $stmtMiss->execute();
    $missingGrades = $stmtMiss->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMiss->close();
}

$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-bell-fill me-2 text-navy"></i>Nhac nhap Diem</span>
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

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small">Hoc ky</label>
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($semesters as $sm): ?>
                    <option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($missingGrades) && isAcademicManager()): ?>
            <form method="post" action="grade_reminder.php" class="d-inline">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="remind_all">
                <input type="hidden" name="semester_id" value="<?php echo $filterSem; ?>">
                <button type="submit" class="btn btn-sm btn-warning"
                        onclick="return confirm('Gui nhac nho den TAT CA <?php echo count($missingGrades); ?> giang vien?')">
                    <i class="bi bi-bell me-1"></i>Nhac tat ca (<?php echo count($missingGrades); ?> GV)
                </button>
            </form>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-exclamation-circle me-2"></i>Giang vien chua nhap du diem
        <span class="badge bg-<?php echo empty($missingGrades)?'success':'danger'; ?> ms-1"><?php echo count($missingGrades); ?></span>
    </div>
    <?php if (empty($missingGrades)): ?>
    <div class="card-body text-center text-muted py-4">
        <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
        Tat ca giang vien da nhap du diem.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Giang vien</th><th>Khoa</th>
                    <th class="text-center">So lop</th>
                    <th class="text-center">SV chua co diem</th>
                    <th class="text-center">Thao tac</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($missingGrades as $gv): ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?php echo htmlspecialchars($gv['teacher_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($gv['teacher_code']); ?> · <?php echo htmlspecialchars($gv['degree']??''); ?></small>
                    <?php if ($gv['email']): ?>
                    <br><small class="text-muted"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($gv['email']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo htmlspecialchars($gv['faculty_name']??'—'); ?></td>
                <td class="text-center"><span class="badge bg-light text-dark"><?php echo $gv['section_count']; ?></span></td>
                <td class="text-center"><span class="badge bg-danger"><?php echo $gv['missing_count']; ?></span></td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <form method="post" action="grade_reminder.php" class="d-inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="remind_one">
                        <input type="hidden" name="teacher_id" value="<?php echo $gv['teacher_id']; ?>">
                        <button class="btn btn-sm btn-outline-warning" title="Gui nhac nho">
                            <i class="bi bi-bell"></i> Nhac
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="grades.php?semester_id=<?php echo $filterSem; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
