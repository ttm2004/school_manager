<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Quan ly Diem so';
$userId    = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yeu cau khong hop le.'];
        header('Location: grades.php'); exit();
    }
    $action = trim($_POST['action'] ?? '');

    // Admin/Phong DT nhap/sua diem (override)
    if ($action === 'save_grade' && isAcademicManager()) {
        $ssId    = (int)($_POST['student_subject_id'] ?? 0);
        $process = $_POST['process_score'] !== '' ? (float)$_POST['process_score'] : null;
        $midterm = $_POST['midterm_score'] !== '' ? (float)$_POST['midterm_score'] : null;
        $final   = $_POST['final_score'] !== '' ? (float)$_POST['final_score'] : null;
        $note    = trim($_POST['note'] ?? '');

        // Kiem tra grade_lock
        $sectionId = (int)$conn->query("SELECT course_section_id FROM student_subjects WHERE id=$ssId LIMIT 1")->fetch_assoc()['course_section_id'];
        if (isGradeLocked($conn, $sectionId)) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Diem da bi khoa. Khong the chinh sua.'];
            header('Location: grades.php?section_id='.$sectionId); exit();
        }

        $total  = calcTotalScore($process, $midterm, $final);
        $letter = calcLetterGrade($total);

        $chk = $conn->prepare("SELECT id FROM grades WHERE student_subject_id=?");
        $chk->bind_param('i', $ssId); $chk->execute();
        $exists = $chk->get_result()->fetch_assoc(); $chk->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE grades SET process_score=?,midterm_score=?,final_score=?,total_score=?,letter_grade=?,note=? WHERE student_subject_id=?");
            $stmt->bind_param('ddddssi', $process,$midterm,$final,$total,$letter,$note,$ssId);
        } else {
            $stmt = $conn->prepare("INSERT INTO grades (student_subject_id,process_score,midterm_score,final_score,total_score,letter_grade,note) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('iddddss', $ssId,$process,$midterm,$final,$total,$letter,$note);
        }
        $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Luu diem thanh cong.']
                         : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
        $stmt->close();
        header('Location: grades.php?section_id='.$sectionId); exit();
    }
}

$flash = getFlash();

$filterSem     = (int)($_GET['semester_id'] ?? 0);
$filterSection = (int)($_GET['section_id'] ?? 0);
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);

if ($filterSem === 0) {
    $activeSem = getActiveSemesterAcademic($conn);
    if ($activeSem) $filterSem = (int)$activeSem['id'];
}

// Lay danh sach lop HP
$sectionWhere = 'cs.semester_id = ?';
$sectionParams = [$filterSem];
$sectionTypes  = 'i';
if ($filterFaculty > 0) {
    $sectionWhere .= ' AND f.id = ?';
    $sectionParams[] = $filterFaculty;
    $sectionTypes   .= 'i';
}
$stmtSec = $conn->prepare(
    "SELECT cs.id, cs.section_code, cs.status,
            s.subject_name, f.faculty_name,
            ut.full_name AS teacher_name,
            COUNT(ss.id) AS total_students,
            SUM(CASE WHEN g.final_score IS NOT NULL THEN 1 ELSE 0 END) AS graded_count,
            (SELECT COUNT(*) FROM grade_locks gl WHERE gl.course_section_id = cs.id) AS is_locked
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id = s.id
     LEFT JOIN majors m ON s.major_id = m.id
     LEFT JOIN faculties f ON m.faculty_id = f.id
     LEFT JOIN teachers t ON cs.teacher_id = t.id
     LEFT JOIN users ut ON t.user_id = ut.id
     LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id AND ss.status='registered'
     LEFT JOIN grades g ON g.student_subject_id = ss.id
     WHERE $sectionWhere
     GROUP BY cs.id, cs.section_code, cs.status, s.subject_name, f.faculty_name, ut.full_name
     ORDER BY f.faculty_name, s.subject_name"
);
$stmtSec->bind_param($sectionTypes, ...$sectionParams);
$stmtSec->execute();
$sections = $stmtSec->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSec->close();

// Lay diem cua lop duoc chon
$gradeRows = [];
$selectedSection = null;
if ($filterSection > 0) {
    $stmtSelSec = $conn->prepare(
        "SELECT cs.id, cs.section_code, s.subject_name, ut.full_name AS teacher_name,
                sm.semester_name,
                (SELECT COUNT(*) FROM grade_locks gl WHERE gl.course_section_id = cs.id) AS is_locked
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN semesters sm ON cs.semester_id = sm.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users ut ON t.user_id = ut.id
         WHERE cs.id = ? LIMIT 1"
    );
    $stmtSelSec->bind_param('i', $filterSection);
    $stmtSelSec->execute();
    $selectedSection = $stmtSelSec->get_result()->fetch_assoc();
    $stmtSelSec->close();

    $stmtGr = $conn->prepare(
        "SELECT ss.id AS ss_id, st.student_code, u.full_name,
                g.id AS grade_id, g.process_score, g.midterm_score, g.final_score,
                g.total_score, g.letter_grade, g.note
         FROM student_subjects ss
         JOIN students st ON ss.student_id = st.id
         JOIN users u ON st.user_id = u.id
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE ss.course_section_id = ? AND ss.status = 'registered'
         ORDER BY u.full_name ASC"
    );
    $stmtGr->bind_param('i', $filterSection);
    $stmtGr->execute();
    $gradeRows = $stmtGr->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtGr->close();
}

$faculties = $conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-graph-up me-2 text-navy"></i>Quan ly Diem so</span>
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

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small">Hoc ky</label>
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($semesters as $sm): ?>
                    <option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
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
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <!-- Danh sach lop HP -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-list me-2"></i>Lop HP
                <span class="badge bg-light text-dark ms-1"><?php echo count($sections); ?></span>
            </div>
            <div class="list-group list-group-flush" style="max-height:600px;overflow-y:auto">
            <?php if (empty($sections)): ?>
            <div class="list-group-item text-center text-muted py-3">Khong co du lieu.</div>
            <?php else: ?>
            <?php foreach ($sections as $sec):
                $pct = $sec['total_students'] > 0 ? round($sec['graded_count']/$sec['total_students']*100) : 0;
                $pctColor = $pct >= 100 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
                $isActive = $filterSection === (int)$sec['id'];
            ?>
            <a href="grades.php?semester_id=<?php echo $filterSem; ?>&faculty_id=<?php echo $filterFaculty; ?>&section_id=<?php echo $sec['id']; ?>"
               class="list-group-item list-group-item-action <?php echo $isActive?'active':''; ?> py-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold"><?php echo htmlspecialchars($sec['section_code']); ?></div>
                        <small class="<?php echo $isActive?'text-white-50':'text-muted'; ?>"><?php echo htmlspecialchars(mb_substr($sec['subject_name'],0,30)); ?></small>
                        <?php if ($sec['is_locked']): ?>
                        <span class="badge bg-secondary ms-1" style="font-size:.65rem"><i class="bi bi-lock-fill"></i></span>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-<?php echo $pctColor; ?> flex-shrink-0">
                        <?php echo $sec['graded_count']; ?>/<?php echo $sec['total_students']; ?>
                    </span>
                </div>
                <div class="progress mt-1" style="height:3px">
                    <div class="progress-bar bg-<?php echo $pctColor; ?>" style="width:<?php echo $pct; ?>%"></div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bang diem -->
    <div class="col-lg-8">
        <?php if (!$selectedSection): ?>
        <div class="card h-100">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-graph-up fs-1 d-block mb-3 opacity-25"></i>
                Chon mot lop HP de xem va quan ly diem.
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-table me-2"></i>
                    <?php echo htmlspecialchars($selectedSection['section_code']); ?>
                    — <?php echo htmlspecialchars($selectedSection['subject_name']); ?>
                    <?php if ($selectedSection['is_locked']): ?>
                    <span class="badge bg-secondary ms-2"><i class="bi bi-lock-fill me-1"></i>Da khoa diem</span>
                    <?php endif; ?>
                </span>
                <small class="text-muted">GV: <?php echo htmlspecialchars($selectedSection['teacher_name']??'Chua co'); ?></small>
            </div>
            <?php if (empty($gradeRows)): ?>
            <div class="card-body text-center text-muted py-4">Chua co sinh vien dang ky.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ma SV</th><th>Ho ten</th>
                            <th class="text-center">QT (20%)</th>
                            <th class="text-center">GK (30%)</th>
                            <th class="text-center">CK (50%)</th>
                            <th class="text-center">Tong</th>
                            <th class="text-center">Xep loai</th>
                            <?php if (isAcademicManager() && !$selectedSection['is_locked']): ?>
                            <th class="text-center">Sua</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gradeRows as $gr):
                        $letterColor = match($gr['letter_grade']) {
                            'A','B+','B' => 'success',
                            'C+','C'    => 'warning',
                            'D+','D'    => 'secondary',
                            'F'         => 'danger',
                            default     => 'light'
                        };
                    ?>
                    <tr>
                        <td><code class="small"><?php echo htmlspecialchars($gr['student_code']); ?></code></td>
                        <td class="small"><?php echo htmlspecialchars($gr['full_name']); ?></td>
                        <td class="text-center small"><?php echo $gr['process_score'] !== null ? number_format($gr['process_score'],2) : '<span class="text-muted">—</span>'; ?></td>
                        <td class="text-center small"><?php echo $gr['midterm_score'] !== null ? number_format($gr['midterm_score'],2) : '<span class="text-muted">—</span>'; ?></td>
                        <td class="text-center small"><?php echo $gr['final_score'] !== null ? number_format($gr['final_score'],2) : '<span class="text-muted">—</span>'; ?></td>
                        <td class="text-center">
                            <?php if ($gr['total_score'] !== null): ?>
                            <strong><?php echo number_format($gr['total_score'],2); ?></strong>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($gr['letter_grade']): ?>
                            <span class="badge bg-<?php echo $letterColor; ?>"><?php echo $gr['letter_grade']; ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <?php if (isAcademicManager() && !$selectedSection['is_locked']): ?>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:2px 6px"
                                    data-bs-toggle="modal" data-bs-target="#editGradeModal"
                                    data-ss-id="<?php echo $gr['ss_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($gr['full_name']); ?>"
                                    data-process="<?php echo $gr['process_score']??''; ?>"
                                    data-midterm="<?php echo $gr['midterm_score']??''; ?>"
                                    data-final="<?php echo $gr['final_score']??''; ?>"
                                    data-note="<?php echo htmlspecialchars($gr['note']??''); ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /.admin-content -->
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal sua diem -->
<?php if (isAcademicManager()): ?>
<div class="modal fade" id="editGradeModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chinh sua Diem</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post" action="grades.php?section_id=<?php echo $filterSection; ?>&semester_id=<?php echo $filterSem; ?>">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save_grade">
            <input type="hidden" name="student_subject_id" id="editSsId">
            <div class="modal-body">
                <p class="fw-semibold mb-3" id="editStudentName"></p>
                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label small">Qua trinh (20%)</label>
                        <input type="number" name="process_score" id="editProcess" class="form-control form-control-sm"
                               min="0" max="10" step="0.01">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Giua ky (30%)</label>
                        <input type="number" name="midterm_score" id="editMidterm" class="form-control form-control-sm"
                               min="0" max="10" step="0.01">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Cuoi ky (50%)</label>
                        <input type="number" name="final_score" id="editFinal" class="form-control form-control-sm"
                               min="0" max="10" step="0.01">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Ghi chu</label>
                        <input type="text" name="note" id="editNote" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Luu diem</button>
            </div>
        </form>
    </div></div>
</div>
<script>
document.getElementById('editGradeModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editSsId').value = b.dataset.ssId;
    document.getElementById('editStudentName').textContent = b.dataset.name;
    document.getElementById('editProcess').value = b.dataset.process;
    document.getElementById('editMidterm').value = b.dataset.midterm;
    document.getElementById('editFinal').value = b.dataset.final;
    document.getElementById('editNote').value = b.dataset.note;
});
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
