<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Khoa Diem';
$userId    = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yeu cau khong hop le.'];
        header('Location: grade_locks.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chi Truong phong moi co quyen khoa diem.'];
        header('Location: grade_locks.php'); exit();
    }
    $action    = trim($_POST['action'] ?? '');
    $sectionId = (int)($_POST['section_id'] ?? 0);

    if ($action === 'lock' && $sectionId > 0) {
        $note = trim($_POST['note'] ?? '');
        $chk = $conn->query("SHOW TABLES LIKE 'grade_locks'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO grade_locks (course_section_id, locked_by, note) VALUES (?,?,?)");
            $stmt->bind_param('iis', $sectionId, $userId, $note);
            if ($stmt->execute()) {
                // Thong bao cho GV
                $info = $conn->query("SELECT cs.section_code, s.subject_name, t.user_id AS tuid
                                      FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id
                                      LEFT JOIN teachers t ON cs.teacher_id=t.id
                                      WHERE cs.id=$sectionId LIMIT 1")->fetch_assoc();
                if ($info && $info['tuid']) {
                    $title = "Diem lop {$info['section_code']} da bi khoa";
                    $content = "Phong Dao tao da khoa diem lop {$info['section_code']} ({$info['subject_name']}). Khong the chinh sua them.";
                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, content) VALUES (?,?,?)");
                    $stmtN->bind_param('iss', $info['tuid'], $title, $content);
                    $stmtN->execute(); $stmtN->close();
                }
                $_SESSION['_flash'] = ['type'=>'success','message'=>'Da khoa diem lop HP.'];
            }
            $stmt->close();
        }
        header('Location: grade_locks.php'); exit();
    }

    if ($action === 'unlock' && $sectionId > 0) {
        $chk = $conn->query("SHOW TABLES LIKE 'grade_locks'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM grade_locks WHERE course_section_id=?");
            $stmt->bind_param('i', $sectionId);
            $stmt->execute(); $stmt->close();
        }
        $_SESSION['_flash'] = ['type'=>'warning','message'=>'Da mo khoa diem.'];
        header('Location: grade_locks.php'); exit();
    }
}

$flash = getFlash();
$filterSem = (int)($_GET['semester_id'] ?? 0);
if ($filterSem === 0) {
    $activeSem = getActiveSemesterAcademic($conn);
    if ($activeSem) $filterSem = (int)$activeSem['id'];
}

$tableExists = $conn->query("SHOW TABLES LIKE 'grade_locks'")->num_rows > 0;

$sections = [];
if ($filterSem > 0) {
    $stmtSec = $conn->prepare(
        "SELECT cs.id, cs.section_code, cs.status,
                s.subject_name, f.faculty_name,
                ut.full_name AS teacher_name,
                COUNT(ss.id) AS total_students,
                SUM(CASE WHEN g.final_score IS NOT NULL THEN 1 ELSE 0 END) AS graded_count,
                gl.id AS lock_id, gl.locked_at, ul.full_name AS locked_by_name
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN majors m ON s.major_id = m.id
         LEFT JOIN faculties f ON m.faculty_id = f.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users ut ON t.user_id = ut.id
         LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id AND ss.status='registered'
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         LEFT JOIN grade_locks gl ON gl.course_section_id = cs.id
         LEFT JOIN users ul ON gl.locked_by = ul.id
         WHERE cs.semester_id = ? AND cs.status IN ('open','closed')
         GROUP BY cs.id, cs.section_code, cs.status, s.subject_name, f.faculty_name,
                  ut.full_name, gl.id, gl.locked_at, ul.full_name
         ORDER BY f.faculty_name, s.subject_name"
    );
    $stmtSec->bind_param('i', $filterSem);
    $stmtSec->execute();
    $sections = $stmtSec->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSec->close();
}

$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-lock-fill me-2 text-navy"></i>Khoa Diem</span>
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

<?php if (!$tableExists): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Chua chay migration. Vui long chay <code>academic_module_migration.sql</code>.</div>
<?php else: ?>

<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle me-2"></i>
    Sau khi <strong>khoa diem</strong>, giang vien va admin khong the chinh sua diem nua.
    Chi Truong phong Dao tao moi co the mo khoa lai.
</div>

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
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-lock me-2"></i>Danh sach Lop HP
            <span class="badge bg-light text-dark ms-1"><?php echo count($sections); ?></span>
        </span>
        <?php if (isAcademicManager() && !empty($sections)): ?>
        <form method="post" action="grade_locks.php" onsubmit="return confirm('Khoa diem TAT CA lop HP trong hoc ky nay?')">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="lock_all">
            <input type="hidden" name="semester_id" value="<?php echo $filterSem; ?>">
        </form>
        <?php endif; ?>
    </div>
    <?php if (empty($sections)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Khong co du lieu.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Lop HP</th><th>Khoa</th><th>Giang vien</th>
                    <th class="text-center">Tien do diem</th>
                    <th>Trang thai khoa</th>
                    <th class="text-center">Thao tac</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sections as $sec):
                $pct = $sec['total_students'] > 0 ? round($sec['graded_count']/$sec['total_students']*100) : 0;
                $pctColor = $pct >= 100 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
                $isLocked = !empty($sec['lock_id']);
            ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?php echo htmlspecialchars($sec['section_code']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars(mb_substr($sec['subject_name'],0,35)); ?></small>
                </td>
                <td class="small"><?php echo htmlspecialchars($sec['faculty_name']??'—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($sec['teacher_name']??'Chua co'); ?></td>
                <td class="text-center">
                    <span class="badge bg-<?php echo $pctColor; ?>"><?php echo $sec['graded_count']; ?>/<?php echo $sec['total_students']; ?></span>
                    <div class="progress mt-1" style="height:3px;width:80px;margin:0 auto">
                        <div class="progress-bar bg-<?php echo $pctColor; ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </td>
                <td>
                    <?php if ($isLocked): ?>
                    <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>Da khoa</span>
                    <br><small class="text-muted">Boi <?php echo htmlspecialchars($sec['locked_by_name']??''); ?>
                    · <?php echo $sec['locked_at'] ? date('d/m/Y', strtotime($sec['locked_at'])) : ''; ?></small>
                    <?php else: ?>
                    <span class="badge bg-success"><i class="bi bi-unlock-fill me-1"></i>Dang mo</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <?php if (!$isLocked): ?>
                    <button class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#lockModal"
                            data-section-id="<?php echo $sec['id']; ?>"
                            data-section-code="<?php echo htmlspecialchars($sec['section_code']); ?>"
                            data-pct="<?php echo $pct; ?>"
                            title="Khoa diem">
                        <i class="bi bi-lock"></i> Khoa
                    </button>
                    <?php else: ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Mo khoa diem lop nay?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="unlock">
                        <input type="hidden" name="section_id" value="<?php echo $sec['id']; ?>">
                        <button class="btn btn-sm btn-outline-warning"><i class="bi bi-unlock"></i> Mo khoa</button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
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

<div class="modal fade" id="lockModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-lock me-2"></i>Khoa Diem</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post" action="grade_locks.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="lock">
            <input type="hidden" name="section_id" id="lockSectionId">
            <div class="modal-body">
                <p>Khoa diem lop: <strong id="lockSectionCode"></strong></p>
                <div id="lockWarning" class="alert alert-warning d-none">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Lop HP nay chua nhap du diem (<span id="lockPct"></span>%). Ban co chac muon khoa?
                </div>
                <label class="form-label small">Ghi chu</label>
                <input type="text" name="note" class="form-control form-control-sm" placeholder="VD: Ket thuc hoc ky HK1 2025-2026">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
                <button type="submit" class="btn btn-dark"><i class="bi bi-lock me-1"></i>Xac nhan Khoa diem</button>
            </div>
        </form>
    </div></div>
</div>
<script>
document.getElementById('lockModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('lockSectionId').value = b.dataset.sectionId;
    document.getElementById('lockSectionCode').textContent = b.dataset.sectionCode;
    const pct = parseInt(b.dataset.pct);
    const warn = document.getElementById('lockWarning');
    if (pct < 100) {
        warn.classList.remove('d-none');
        document.getElementById('lockPct').textContent = pct;
    } else { warn.classList.add('d-none'); }
});
</script>
<?php include 'includes/footer.php'; ?>
