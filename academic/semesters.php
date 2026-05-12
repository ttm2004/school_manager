<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Quan ly Hoc ky';
$userId    = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yeu cau khong hop le.'];
        header('Location: semesters.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chi Truong phong moi co quyen quan ly hoc ky.'];
        header('Location: semesters.php'); exit();
    }
    $action = trim($_POST['action'] ?? '');

    if ($action === 'add') {
        $name           = trim($_POST['name'] ?? '');
        $schoolYear     = trim($_POST['school_year'] ?? '');
        $startDate      = trim($_POST['start_date'] ?? '') ?: null;
        $endDate        = trim($_POST['end_date'] ?? '') ?: null;
        $regStart       = trim($_POST['register_start'] ?? '') ?: null;
        $regEnd         = trim($_POST['register_end'] ?? '') ?: null;
        $proposalStart  = trim($_POST['proposal_start'] ?? '') ?: null;
        $proposalEnd    = trim($_POST['proposal_end'] ?? '') ?: null;
        $approvalStart  = trim($_POST['approval_start'] ?? '') ?: null;
        $approvalEnd    = trim($_POST['approval_end'] ?? '') ?: null;
        $gradeDeadline  = trim($_POST['grade_submit_deadline'] ?? '') ?: null;
        $proposalDeadline = trim($_POST['proposal_deadline'] ?? '') ?: null;
        $status         = trim($_POST['status'] ?? 'upcoming');
        if ($name && $schoolYear) {
            $policyWindow = academicPolicySemesterWindowFromRow(['semester_name' => $name, 'school_year' => $schoolYear]);
            if ($policyWindow) {
                $startDate = $policyWindow['start_date'];
                $endDate = $policyWindow['end_date'];
            }
            $stmt = $conn->prepare(
                "INSERT INTO semesters (semester_name, school_year, start_date, end_date,
                 proposal_start, proposal_end, approval_start, approval_end,
                 register_start, register_end, grade_submit_deadline, proposal_deadline, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('sssssssssssss', $name,$schoolYear,$startDate,$endDate,
                $proposalStart,$proposalEnd,$approvalStart,$approvalEnd,
                $regStart,$regEnd,$gradeDeadline,$proposalDeadline,$status);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Them hoc ky thanh cong.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        } else { $_SESSION['_flash']=['type'=>'danger','message'=>'Vui long nhap ten va nam hoc.']; }
        header('Location: semesters.php'); exit();
    }

    if ($action === 'edit') {
        $id             = (int)($_POST['id'] ?? 0);
        $name           = trim($_POST['name'] ?? '');
        $schoolYear     = trim($_POST['school_year'] ?? '');
        $startDate      = trim($_POST['start_date'] ?? '') ?: null;
        $endDate        = trim($_POST['end_date'] ?? '') ?: null;
        $regStart       = trim($_POST['register_start'] ?? '') ?: null;
        $regEnd         = trim($_POST['register_end'] ?? '') ?: null;
        $proposalStart  = trim($_POST['proposal_start'] ?? '') ?: null;
        $proposalEnd    = trim($_POST['proposal_end'] ?? '') ?: null;
        $approvalStart  = trim($_POST['approval_start'] ?? '') ?: null;
        $approvalEnd    = trim($_POST['approval_end'] ?? '') ?: null;
        $gradeDeadline  = trim($_POST['grade_submit_deadline'] ?? '') ?: null;
        $proposalDeadline = trim($_POST['proposal_deadline'] ?? '') ?: null;
        $status         = trim($_POST['status'] ?? 'upcoming');
        if ($id && $name) {
            $policyWindow = academicPolicySemesterWindowFromRow(['semester_name' => $name, 'school_year' => $schoolYear]);
            if ($policyWindow) {
                $startDate = $policyWindow['start_date'];
                $endDate = $policyWindow['end_date'];
            }
            $stmt = $conn->prepare(
                "UPDATE semesters SET semester_name=?, school_year=?, start_date=?, end_date=?,
                 proposal_start=?, proposal_end=?, approval_start=?, approval_end=?,
                 register_start=?, register_end=?, grade_submit_deadline=?, proposal_deadline=?, status=?
                 WHERE id=?"
            );
            $stmt->bind_param('sssssssssssssi', $name,$schoolYear,$startDate,$endDate,
                $proposalStart,$proposalEnd,$approvalStart,$approvalEnd,
                $regStart,$regEnd,$gradeDeadline,$proposalDeadline,$status,$id);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Cap nhat thanh cong.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        }
        header('Location: semesters.php'); exit();
    }

    // Mo dang ky mon hoc
    if ($action === 'open_registration') {
        $id   = (int)($_POST['id'] ?? 0);
        $days = max(1, (int)($_POST['reg_days'] ?? 7));
        $regStart = date('Y-m-d H:i:s');
        $regEnd   = date('Y-m-d H:i:s', strtotime("+$days days"));
        $stmt = $conn->prepare("UPDATE semesters SET register_start=?, register_end=?, status='open' WHERE id=?");
        $stmt->bind_param('ssi', $regStart, $regEnd, $id);
        if ($stmt->execute()) {
            // Thong bao cho tat ca SV
            $svList = $conn->query("SELECT id FROM users WHERE role='student' AND status=1")->fetch_all(MYSQLI_ASSOC);
            $title = 'Mo dang ky hoc phan';
            $content = "Hoc ky da mo dang ky hoc phan. Han dang ky: " . date('d/m/Y H:i', strtotime($regEnd));
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, content) VALUES (?,?,?)");
            foreach ($svList as $sv) {
                $stmtN->bind_param('iss', $sv['id'], $title, $content);
                $stmtN->execute();
            }
            $stmtN->close();
            $_SESSION['_flash'] = ['type'=>'success','message'=>"Da mo dang ky. Han: ".date('d/m/Y H:i', strtotime($regEnd))." Da thong bao ".count($svList)." sinh vien."];
        }
        $stmt->close();
        header('Location: semesters.php'); exit();
    }

    // Dong dang ky
    if ($action === 'close_registration') {
        $id = (int)($_POST['id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE semesters SET register_end=?, status='active' WHERE id=?");
        $stmt->bind_param('si', $now, $id);
        $stmt->execute(); $stmt->close();
        $_SESSION['_flash'] = ['type'=>'warning','message'=>'Da dong dang ky hoc phan.'];
        header('Location: semesters.php'); exit();
    }
}

$flash = getFlash();
$semesters = $conn->query(
    "SELECT sm.*,
            (SELECT COUNT(*) FROM course_sections cs WHERE cs.semester_id=sm.id) AS section_count,
            (SELECT COUNT(*) FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id WHERE cs.semester_id=sm.id) AS reg_count
     FROM semesters sm ORDER BY sm.id DESC"
)->fetch_all(MYSQLI_ASSOC);

$statusLabels = [
    'upcoming' => ['secondary','Sap toi'],
    'open'     => ['success', 'Mo dang ky'],
    'active'   => ['primary', 'Dang hoc'],
    'closed'   => ['dark',    'Da ket thuc'],
];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-calendar3 me-2 text-navy"></i>Quan ly Hoc ky</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (isAcademicManager()): ?>
        <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addSemModal">
            <i class="bi bi-plus-lg me-1"></i>Them hoc ky
        </button>
        <?php endif; ?>
        <span class="text-muted small align-self-center"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
    </div>
</div>
<div class="admin-content">

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3">
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Danh sach Hoc ky</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Hoc ky</th><th>Nam hoc</th><th>Thoi gian hoc</th>
                    <th>De xuat / duyet</th><th>Dang ky mon</th><th>Han nop diem</th>
                    <th class="text-center">Lop HP</th><th class="text-center">Luot DK</th>
                    <th>Trang thai</th><th class="text-center">Thao tac</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($semesters as $sm):
                [$sColor, $sLabel] = $statusLabels[$sm['status']] ?? ['secondary', $sm['status']];
                $isRegOpen = $sm['status'] === 'open' && $sm['register_end'] && strtotime($sm['register_end']) > time();
            ?>
            <tr>
                <td class="fw-semibold"><?php echo htmlspecialchars($sm['semester_name']); ?></td>
                <td><?php echo htmlspecialchars($sm['school_year']); ?></td>
                <td class="small text-muted">
                    <?php echo $sm['start_date'] ? date('d/m/Y', strtotime($sm['start_date'])) : '—'; ?>
                    <?php echo $sm['end_date'] ? ' → '.date('d/m/Y', strtotime($sm['end_date'])) : ''; ?>
                </td>
                <td class="small text-muted">
                    <div>DX: <?php echo $sm['proposal_start'] ? date('d/m H:i', strtotime($sm['proposal_start'])) : '—'; ?>
                        → <?php echo $sm['proposal_end'] ? date('d/m H:i', strtotime($sm['proposal_end'])) : '—'; ?></div>
                    <div>Duyet: <?php echo $sm['approval_start'] ? date('d/m H:i', strtotime($sm['approval_start'])) : '—'; ?>
                        → <?php echo $sm['approval_end'] ? date('d/m H:i', strtotime($sm['approval_end'])) : '—'; ?></div>
                </td>
                <td class="small">
                    <?php if ($isRegOpen): ?>
                    <span class="badge bg-success">Dang mo</span>
                    <br><small class="text-muted">Den <?php echo date('d/m/Y H:i', strtotime($sm['register_end'])); ?></small>
                    <?php elseif ($sm['register_start']): ?>
                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($sm['register_start'])); ?> → <?php echo $sm['register_end'] ? date('d/m/Y', strtotime($sm['register_end'])) : '?'; ?></small>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="small text-muted"><?php echo $sm['grade_submit_deadline'] ? date('d/m/Y', strtotime($sm['grade_submit_deadline'])) : '—'; ?></td>
                <td class="text-center"><span class="badge bg-light text-dark"><?php echo $sm['section_count']; ?></span></td>
                <td class="text-center"><span class="badge bg-light text-dark"><?php echo $sm['reg_count']; ?></span></td>
                <td><span class="badge bg-<?php echo $sColor; ?>"><?php echo $sLabel; ?></span></td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                    <?php if (isAcademicManager()): ?>
                    <?php if (!$isRegOpen && $sm['status'] !== 'closed'): ?>
                    <button class="btn btn-xs btn-success" style="font-size:.7rem;padding:2px 6px"
                            data-bs-toggle="modal" data-bs-target="#openRegModal"
                            data-sem-id="<?php echo $sm['id']; ?>"
                            data-sem-name="<?php echo htmlspecialchars($sm['semester_name']); ?>">
                        <i class="bi bi-unlock"></i> Mo DK
                    </button>
                    <?php elseif ($isRegOpen): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Dong dang ky?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="close_registration">
                        <input type="hidden" name="id" value="<?php echo $sm['id']; ?>">
                        <button class="btn btn-xs btn-warning" style="font-size:.7rem;padding:2px 6px">
                            <i class="bi bi-lock"></i> Dong DK
                        </button>
                    </form>
                    <?php endif; ?>
                    <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:2px 6px"
                            data-bs-toggle="modal" data-bs-target="#editSemModal"
                            data-id="<?php echo $sm['id']; ?>"
                            data-name="<?php echo htmlspecialchars($sm['semester_name']); ?>"
                            data-year="<?php echo htmlspecialchars($sm['school_year']); ?>"
                            data-start="<?php echo $sm['start_date']??''; ?>"
                            data-end="<?php echo $sm['end_date']??''; ?>"
                            data-proposal-start="<?php echo $sm['proposal_start'] ? date('Y-m-d\TH:i', strtotime($sm['proposal_start'])) : ''; ?>"
                            data-proposal-end="<?php echo $sm['proposal_end'] ? date('Y-m-d\TH:i', strtotime($sm['proposal_end'])) : ''; ?>"
                            data-approval-start="<?php echo $sm['approval_start'] ? date('Y-m-d\TH:i', strtotime($sm['approval_start'])) : ''; ?>"
                            data-approval-end="<?php echo $sm['approval_end'] ? date('Y-m-d\TH:i', strtotime($sm['approval_end'])) : ''; ?>"
                            data-reg-start="<?php echo $sm['register_start'] ? date('Y-m-d\TH:i', strtotime($sm['register_start'])) : ''; ?>"
                            data-reg-end="<?php echo $sm['register_end'] ? date('Y-m-d\TH:i', strtotime($sm['register_end'])) : ''; ?>"
                            data-grade-deadline="<?php echo $sm['grade_submit_deadline']??''; ?>"
                            data-proposal-deadline="<?php echo $sm['proposal_deadline']??''; ?>"
                            data-status="<?php echo $sm['status']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal them hoc ky -->
<div class="modal fade" id="addSemModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Them Hoc ky moi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post" action="semesters.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Ten hoc ky <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="VD: Hoc ky 1"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Nam hoc <span class="text-danger">*</span></label>
                    <input type="text" name="school_year" class="form-control" required placeholder="VD: 2025-2026"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ngay bat dau hoc</label>
                    <input type="date" name="start_date" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ngay ket thuc hoc</label>
                    <input type="date" name="end_date" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Bat dau Khoa de xuat</label>
                    <input type="datetime-local" name="proposal_start" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ket thuc Khoa de xuat</label>
                    <input type="datetime-local" name="proposal_end" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Bat dau Phong DT duyet</label>
                    <input type="datetime-local" name="approval_start" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ket thuc Phong DT duyet</label>
                    <input type="datetime-local" name="approval_end" class="form-control"></div>
                <input type="hidden" name="proposal_deadline">
                <div class="col-md-6"><label class="form-label fw-semibold">Han nop diem (GV)</label>
                    <input type="date" name="grade_submit_deadline" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Trang thai</label>
                    <select name="status" class="form-select">
                        <option value="upcoming">Sap toi</option>
                        <option value="active">Dang hoc</option>
                        <option value="closed">Da ket thuc</option>
                    </select></div>
            </div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
                <button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Luu</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Modal sua hoc ky -->
<div class="modal fade" id="editSemModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Chinh sua Hoc ky</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post" action="semesters.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editSemId">
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Ten hoc ky</label>
                    <input type="text" name="name" id="editSemName" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Nam hoc</label>
                    <input type="text" name="school_year" id="editSemYear" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ngay bat dau</label>
                    <input type="date" name="start_date" id="editSemStart" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ngay ket thuc</label>
                    <input type="date" name="end_date" id="editSemEnd" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Bat dau Khoa de xuat</label>
                    <input type="datetime-local" name="proposal_start" id="editProposalStart" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ket thuc Khoa de xuat</label>
                    <input type="datetime-local" name="proposal_end" id="editProposalEnd" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Bat dau Phong DT duyet</label>
                    <input type="datetime-local" name="approval_start" id="editApprovalStart" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ket thuc Phong DT duyet</label>
                    <input type="datetime-local" name="approval_end" id="editApprovalEnd" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Bat dau dang ky</label>
                    <input type="datetime-local" name="register_start" id="editRegStart" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Ket thuc dang ky</label>
                    <input type="datetime-local" name="register_end" id="editRegEnd" class="form-control"></div>
                <input type="hidden" name="proposal_deadline" id="editProposalDeadline">
                <div class="col-md-6"><label class="form-label fw-semibold">Han nop diem (GV)</label>
                    <input type="date" name="grade_submit_deadline" id="editGradeDeadline" class="form-control"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Trang thai</label>
                    <select name="status" id="editSemStatus" class="form-select">
                        <option value="upcoming">Sap toi</option>
                        <option value="open">Mo dang ky</option>
                        <option value="active">Dang hoc</option>
                        <option value="closed">Da ket thuc</option>
                    </select></div>
            </div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
                <button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Luu</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Modal mo dang ky -->
<div class="modal fade" id="openRegModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-success text-white"><h5 class="modal-title">Mo Dang ky Hoc phan</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <form method="post" action="semesters.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="open_registration">
            <input type="hidden" name="id" id="openRegSemId">
            <div class="modal-body">
                <p>Hoc ky: <strong id="openRegSemName"></strong></p>
                <label class="form-label fw-semibold">So ngay mo dang ky</label>
                <input type="number" name="reg_days" class="form-control" value="14" min="1" max="60">
                <div class="form-text">He thong se tu dong thong bao den tat ca sinh vien.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-unlock me-1"></i>Mo dang ky</button>
            </div>
        </form>
    </div></div>
</div>

<script>
document.getElementById('editSemModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editSemId').value = b.dataset.id;
    document.getElementById('editSemName').value = b.dataset.name;
    document.getElementById('editSemYear').value = b.dataset.year;
    document.getElementById('editSemStart').value = b.dataset.start;
    document.getElementById('editSemEnd').value = b.dataset.end;
    document.getElementById('editProposalStart').value = b.dataset.proposalStart;
    document.getElementById('editProposalEnd').value = b.dataset.proposalEnd;
    document.getElementById('editApprovalStart').value = b.dataset.approvalStart;
    document.getElementById('editApprovalEnd').value = b.dataset.approvalEnd;
    document.getElementById('editRegStart').value = b.dataset.regStart;
    document.getElementById('editRegEnd').value = b.dataset.regEnd;
    document.getElementById('editGradeDeadline').value = b.dataset.gradeDeadline;
    document.getElementById('editProposalDeadline').value = b.dataset.proposalDeadline;
    document.getElementById('editSemStatus').value = b.dataset.status;
});
document.getElementById('openRegModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('openRegSemId').value = b.dataset.semId;
    document.getElementById('openRegSemName').textContent = b.dataset.semName;
});
</script>
<?php include 'includes/footer.php'; ?>
