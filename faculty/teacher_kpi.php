<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager','faculty_staff','dept_head','faculty_lecturer']);

$pageTitle = 'KPI Giang vien';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Tai khoan chua duoc gan vao khoa nao.'];
    header('Location: /university/login.php'); exit();
}

$myTeacherId = 0;
$stmtMyT = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? AND faculty_id = ? LIMIT 1");
$stmtMyT->bind_param('ii', $userId, $facultyId);
$stmtMyT->execute();
$myTeacherRow = $stmtMyT->get_result()->fetch_assoc();
$stmtMyT->close();
if ($myTeacherRow) $myTeacherId = (int)$myTeacherRow['id'];

// Kiem tra bang ton tai
$tableExists = $conn->query("SHOW TABLES LIKE 'teacher_kpi'")->num_rows > 0;

// Lay ky KPI
$periods = [];
if ($tableExists) {
    $periods = $conn->query("SELECT * FROM teacher_kpi_periods ORDER BY year_start DESC")->fetch_all(MYSQLI_ASSOC);
}
$selectedPeriodId = (int)($_GET['period_id'] ?? ($periods[0]['id'] ?? 0));

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $action = trim($_POST['action'] ?? '');

    // GV nhap/cap nhat KPI cua minh
    if ($action === 'save_kpi') {
        if ($myTeacherId <= 0) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Ban khong phai giang vien.'];
            header('Location: teacher_kpi.php'); exit();
        }
        $periodId          = (int)($_POST['period_id'] ?? 0);
        $teachingPlan      = max(0, (int)($_POST['teaching_hours_plan'] ?? 0));
        $teachingActual    = max(0, (int)($_POST['teaching_hours_actual'] ?? 0));
        $researchProjects  = max(0, (int)($_POST['research_projects'] ?? 0));
        $papersPublished   = max(0, (int)($_POST['papers_published'] ?? 0));
        $papersInProgress  = max(0, (int)($_POST['papers_in_progress'] ?? 0));
        $thesisSupervised  = max(0, (int)($_POST['thesis_supervised'] ?? 0));
        $projectGraded     = max(0, (int)($_POST['project_graded'] ?? 0));
        $trainingCourses   = max(0, (int)($_POST['training_courses'] ?? 0));
        $note              = trim($_POST['note'] ?? '');

        $stmtKpi = $conn->prepare(
            "INSERT INTO teacher_kpi
             (teacher_id, period_id, faculty_id, teaching_hours_plan, teaching_hours_actual,
              research_projects, papers_published, papers_in_progress,
              thesis_supervised, project_graded, training_courses, note, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'draft')
             ON DUPLICATE KEY UPDATE
              teaching_hours_plan=VALUES(teaching_hours_plan),
              teaching_hours_actual=VALUES(teaching_hours_actual),
              research_projects=VALUES(research_projects),
              papers_published=VALUES(papers_published),
              papers_in_progress=VALUES(papers_in_progress),
              thesis_supervised=VALUES(thesis_supervised),
              project_graded=VALUES(project_graded),
              training_courses=VALUES(training_courses),
              note=VALUES(note),
              updated_at=NOW()"
        );
        $stmtKpi->bind_param('iiiiiiiiiiis',
            $myTeacherId, $periodId, $facultyId,
            $teachingPlan, $teachingActual,
            $researchProjects, $papersPublished, $papersInProgress,
            $thesisSupervised, $projectGraded, $trainingCourses, $note
        );
        if ($stmtKpi->execute()) {
            logAudit($conn, $userId, 'update', 'faculty', 'teacher_kpi', $myTeacherId, null,
                json_encode(['period_id'=>$periodId,'teaching_actual'=>$teachingActual]), $ip);
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Da luu KPI.'];
        } else {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Loi: '.$conn->error];
        }
        $stmtKpi->close();
        header('Location: teacher_kpi.php?period_id='.$periodId); exit();
    }

    // Truong khoa duyet KPI
    if ($action === 'approve_kpi' && isFacultyManager()) {
        $kpiId = (int)($_POST['kpi_id'] ?? 0);
        $stmtAppr = $conn->prepare(
            "UPDATE teacher_kpi SET status='approved', approved_by=?, approved_at=NOW()
             WHERE id=? AND faculty_id=? AND status='submitted'"
        );
        $stmtAppr->bind_param('iii', $userId, $kpiId, $facultyId);
        $stmtAppr->execute();
        $_SESSION['_flash'] = ['type'=>'success','message'=>'Da duyet KPI.'];
        $stmtAppr->close();
        header('Location: teacher_kpi.php?period_id='.$selectedPeriodId); exit();
    }

    // GV nop KPI
    if ($action === 'submit_kpi') {
        $kpiId = (int)($_POST['kpi_id'] ?? 0);
        $stmtSub = $conn->prepare(
            "UPDATE teacher_kpi SET status='submitted', submitted_at=NOW()
             WHERE id=? AND teacher_id=? AND status='draft'"
        );
        $stmtSub->bind_param('ii', $kpiId, $myTeacherId);
        $stmtSub->execute();
        $_SESSION['_flash'] = ['type'=>'success','message'=>'Da nop KPI cho Truong khoa.'];
        $stmtSub->close();
        header('Location: teacher_kpi.php?period_id='.$selectedPeriodId); exit();
    }
}

$flash = getFlash();

// Lay du lieu KPI
$kpiRows = [];
if ($tableExists && $selectedPeriodId > 0) {
    if (isFacultyManager() || isDeptHead()) {
        $stmtK = $conn->prepare(
            "SELECT tk.*, u.full_name AS teacher_name, t.teacher_code, t.degree,
                    ua.full_name AS approved_by_name
             FROM teacher_kpi tk
             JOIN teachers t ON tk.teacher_id = t.id
             JOIN users u ON t.user_id = u.id
             LEFT JOIN users ua ON tk.approved_by = ua.id
             WHERE tk.faculty_id = ? AND tk.period_id = ?
             ORDER BY u.full_name ASC"
        );
        $stmtK->bind_param('ii', $facultyId, $selectedPeriodId);
    } else {
        $stmtK = $conn->prepare(
            "SELECT tk.*, u.full_name AS teacher_name, t.teacher_code, t.degree,
                    ua.full_name AS approved_by_name
             FROM teacher_kpi tk
             JOIN teachers t ON tk.teacher_id = t.id
             JOIN users u ON t.user_id = u.id
             LEFT JOIN users ua ON tk.approved_by = ua.id
             WHERE tk.faculty_id = ? AND tk.period_id = ? AND tk.teacher_id = ?
             ORDER BY u.full_name ASC"
        );
        $stmtK->bind_param('iii', $facultyId, $selectedPeriodId, $myTeacherId);
    }
    $stmtK->execute();
    $kpiRows = $stmtK->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtK->close();
}

// KPI cua GV hien tai (de hien thi form nhap)
$myKpi = null;
if ($myTeacherId > 0 && $selectedPeriodId > 0 && $tableExists) {
    foreach ($kpiRows as $k) {
        if ((int)$k['teacher_id'] === $myTeacherId) { $myKpi = $k; break; }
    }
}

$statusLabels = ['draft'=>['Nhap','secondary'],'submitted'=>['Da nop','warning'],'approved'=>['Da duyet','success']];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-graph-up-arrow me-2 text-navy"></i>KPI Giang vien</span>
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

<?php if (!$tableExists): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Chua chay migration. Vui long chay <code>faculty_upgrade_migration.sql</code>.</div>
<?php else: ?>

<div class="row g-3 mb-4">
    <!-- Chon ky KPI -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Ky danh gia</div>
            <div class="card-body">
                <form method="get">
                    <select name="period_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($periods as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $selectedPeriodId==(int)$p['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($p['period_name']); ?>
                            <?php if ($p['status']==='open'): ?>(Dang mo)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>

    <!-- Form nhap KPI (GV) -->
    <?php if ($myTeacherId > 0 && $selectedPeriodId > 0): ?>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Nhap KPI cua toi</div>
            <div class="card-body">
                <form method="post" action="teacher_kpi.php?period_id=<?php echo $selectedPeriodId; ?>">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="save_kpi">
                    <input type="hidden" name="period_id" value="<?php echo $selectedPeriodId; ?>">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Gio giang KH</label>
                            <input type="number" name="teaching_hours_plan" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['teaching_hours_plan']??0); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Gio giang TT</label>
                            <input type="number" name="teaching_hours_actual" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['teaching_hours_actual']??0); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">De tai NCKH</label>
                            <input type="number" name="research_projects" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['research_projects']??0); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Bai bao da dang</label>
                            <input type="number" name="papers_published" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['papers_published']??0); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Bai bao dang viet</label>
                            <input type="number" name="papers_in_progress" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['papers_in_progress']??0); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Khoa luan HD</label>
                            <input type="number" name="thesis_supervised" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['thesis_supervised']??0); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Do an cham</label>
                            <input type="number" name="project_graded" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['project_graded']??0); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Khoa boi duong</label>
                            <input type="number" name="training_courses" class="form-control form-control-sm" min="0"
                                   value="<?php echo (int)($myKpi['training_courses']??0); ?>">
                        </div>
                        <div class="col-12">
                            <input type="text" name="note" class="form-control form-control-sm"
                                   placeholder="Ghi chu them..." value="<?php echo htmlspecialchars($myKpi['note']??''); ?>">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-save me-1"></i>Luu</button>
                            <?php if ($myKpi && $myKpi['status']==='draft'): ?>
                            <button type="button" class="btn btn-sm btn-warning"
                                    onclick="if(confirm('Nop KPI cho Truong khoa?')){
                                        document.getElementById('submitKpiForm').submit();}">
                                <i class="bi bi-send me-1"></i>Nop KPI
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                <?php if ($myKpi && $myKpi['status']==='draft'): ?>
                <form id="submitKpiForm" method="post" class="d-none">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="submit_kpi">
                    <input type="hidden" name="kpi_id" value="<?php echo $myKpi['id']; ?>">
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Bang KPI tong hop -->
<?php if (isFacultyManager() || isDeptHead()): ?>
<div class="card">
    <div class="card-header"><i class="bi bi-table me-2"></i>Tong hop KPI Giang vien
        <span class="badge bg-light text-dark ms-1"><?php echo count($kpiRows); ?></span>
    </div>
    <?php if (empty($kpiRows)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Chua co du lieu KPI.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Giang vien</th>
                    <th class="text-center">Gio giang KH/TT</th>
                    <th class="text-center">NCKH</th>
                    <th class="text-center">Bai bao</th>
                    <th class="text-center">Khoa luan</th>
                    <th class="text-center">Do an</th>
                    <th class="text-center">Boi duong</th>
                    <th>Trang thai</th>
                    <th class="text-center">Thao tac</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($kpiRows as $k):
                [$sLabel, $sColor] = $statusLabels[$k['status']] ?? [$k['status'],'secondary'];
                $loadPct = $k['teaching_hours_plan'] > 0
                    ? round($k['teaching_hours_actual']/$k['teaching_hours_plan']*100)
                    : 0;
                $loadColor = $loadPct >= 100 ? 'success' : ($loadPct >= 70 ? 'warning' : 'danger');
            ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?php echo htmlspecialchars($k['teacher_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($k['teacher_code']); ?> · <?php echo htmlspecialchars($k['degree']??''); ?></small>
                </td>
                <td class="text-center">
                    <span class="badge bg-<?php echo $loadColor; ?>">
                        <?php echo $k['teaching_hours_actual']; ?>/<?php echo $k['teaching_hours_plan']; ?>
                    </span>
                    <div style="font-size:.7rem" class="text-muted"><?php echo $loadPct; ?>%</div>
                </td>
                <td class="text-center"><?php echo $k['research_projects']; ?></td>
                <td class="text-center">
                    <?php echo $k['papers_published']; ?>
                    <?php if ($k['papers_in_progress']>0): ?>
                    <span class="text-muted">(+<?php echo $k['papers_in_progress']; ?>)</span>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?php echo $k['thesis_supervised']; ?></td>
                <td class="text-center"><?php echo $k['project_graded']; ?></td>
                <td class="text-center"><?php echo $k['training_courses']; ?></td>
                <td>
                    <span class="badge bg-<?php echo $sColor; ?>"><?php echo $sLabel; ?></span>
                    <?php if ($k['approved_by_name']): ?>
                    <br><small class="text-muted">Boi <?php echo htmlspecialchars($k['approved_by_name']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if (isFacultyManager() && $k['status']==='submitted'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Duyet KPI?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="approve_kpi">
                        <input type="hidden" name="kpi_id" value="<?php echo $k['id']; ?>">
                        <button class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Duyet</button>
                    </form>
                    <?php else: ?>
                    <span class="text-muted">—</span>
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

<?php endif; ?>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
