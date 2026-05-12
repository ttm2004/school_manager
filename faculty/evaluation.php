<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Danh gia Giang vien';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tai khoan chua duoc gan vao khoa nao.'];
    header('Location: /university/login.php');
    exit();
}

$flash = getFlash();

// Lay danh sach ky danh gia
$periods = [];
$stmtPeriods = $conn->prepare("SELECT id, name, start_date, end_date FROM evaluation_periods ORDER BY id DESC");
$stmtPeriods->execute();
$periods = $stmtPeriods->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPeriods->close();

$selectedPeriodId = (int)($_GET['period_id'] ?? ($periods[0]['id'] ?? 0));
$search           = trim($_GET['search'] ?? '');
$minScore         = trim($_GET['min_score'] ?? '');

// Lay ket qua danh gia
$evalResults = [];
$facultySummary = ['avg_score' => null, 'evaluated_count' => 0, 'not_evaluated_count' => 0];

if ($selectedPeriodId > 0) {
    $whereParts = ['t.faculty_id = ?'];
    $bindTypes  = 'i';
    $bindValues = [$facultyId];

    if ($search !== '') {
        $whereParts[] = 'u.full_name LIKE ?';
        $bindTypes   .= 's';
        $bindValues[] = '%' . $search . '%';
    }

    $whereSQL = implode(' AND ', $whereParts);

    // Lay tat ca GV trong khoa
    $stmtTeachers = $conn->prepare(
        "SELECT t.id, t.teacher_code, u.full_name
         FROM teachers t JOIN users u ON t.user_id = u.id
         WHERE {$whereSQL}
         ORDER BY u.full_name ASC"
    );
    $stmtTeachers->bind_param($bindTypes, ...$bindValues);
    $stmtTeachers->execute();
    $allTeachers = $stmtTeachers->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtTeachers->close();

    // Lay ket qua danh gia theo ky
    $teacherIds = array_column($allTeachers, 'id');
    $evalByTeacher = [];

    if (!empty($teacherIds)) {
        $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
        $types = str_repeat('i', count($teacherIds)) . 'i';
        $params = array_merge($teacherIds, [$selectedPeriodId]);

        $stmtEval = $conn->prepare(
            "SELECT er.teacher_id, AVG(er.total_score) AS avg_score, COUNT(er.id) AS total_responses
             FROM evaluation_results er
             WHERE er.teacher_id IN ({$placeholders}) AND er.period_id = ?
             GROUP BY er.teacher_id"
        );
        $stmtEval->bind_param($types, ...$params);
        $stmtEval->execute();
        $evalRows = $stmtEval->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtEval->close();

        foreach ($evalRows as $row) {
            $evalByTeacher[$row['teacher_id']] = $row;
        }
    }

    // Gop du lieu
    $totalScore = 0;
    $evaluatedCount = 0;
    foreach ($allTeachers as $t) {
        $eval = $evalByTeacher[$t['id']] ?? null;
        $avgScore = $eval ? round((float)$eval['avg_score'], 2) : null;
        $responses = $eval ? (int)$eval['total_responses'] : 0;

        // Filter by min_score
        if ($minScore !== '' && is_numeric($minScore)) {
            if ($avgScore === null || $avgScore < (float)$minScore) continue;
        }

        $evalResults[] = array_merge($t, [
            'avg_score'       => $avgScore,
            'total_responses' => $responses,
        ]);

        if ($avgScore !== null) {
            $totalScore += $avgScore;
            $evaluatedCount++;
        }
    }

    $notEvaluatedCount = count($allTeachers) - $evaluatedCount;
    $facultySummary = [
        'avg_score'          => $evaluatedCount > 0 ? round($totalScore / $evaluatedCount, 2) : null,
        'evaluated_count'    => $evaluatedCount,
        'not_evaluated_count'=> $notEvaluatedCount,
    ];
}

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
                <i class="bi bi-star-fill me-2 text-navy" aria-hidden="true"></i>Danh gia Giang vien
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">
                <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
            </span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Dang xuat
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dong"></button>
        </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="evaluation.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="period_id" class="form-label">Ky danh gia</label>
                        <select id="period_id" name="period_id" class="form-select">
                            <?php if (empty($periods)): ?>
                            <option value="0">Chua co ky danh gia</option>
                            <?php else: ?>
                            <?php foreach ($periods as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"
                                <?php echo $selectedPeriodId === (int)$p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="search" class="form-label">Tim kiem GV</label>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="Ten giang vien..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="min_score" class="form-label">Diem toi thieu</label>
                        <input type="number" id="min_score" name="min_score" class="form-control"
                               min="0" max="5" step="0.1"
                               value="<?php echo htmlspecialchars($minScore); ?>"
                               placeholder="VD: 3.0">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Loc ket qua danh gia">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Loc
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Faculty summary -->
        <?php if ($selectedPeriodId > 0): ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-4">
                <div class="stat-card-admin stat-bg-1">
                    <div class="stat-icon"><i class="bi bi-star-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2">
                        <?php echo $facultySummary['avg_score'] !== null ? number_format($facultySummary['avg_score'], 2) : '—'; ?>
                    </div>
                    <div class="stat-label">Diem TB toan khoa</div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="stat-card-admin stat-bg-2">
                    <div class="stat-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo $facultySummary['evaluated_count']; ?></div>
                    <div class="stat-label">GV da duoc danh gia</div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="stat-card-admin stat-bg-3">
                    <div class="stat-icon"><i class="bi bi-dash-circle" aria-hidden="true"></i></div>
                    <div class="stat-value mt-2"><?php echo $facultySummary['not_evaluated_count']; ?></div>
                    <div class="stat-label">GV chua duoc danh gia</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Results table -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-table me-2" aria-hidden="true"></i>Ket qua Danh gia Giang vien
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Ma GV</th>
                            <th>Ho ten</th>
                            <th class="text-center">Diem TB</th>
                            <th class="text-center">So luot DG</th>
                            <th>Nhan xet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($evalResults)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                <?php echo $selectedPeriodId > 0 ? 'Chua co ket qua danh gia.' : 'Vui long chon ky danh gia.'; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($evalResults as $r): ?>
                        <?php $isLow = $r['avg_score'] !== null && $r['avg_score'] < 3.0; ?>
                        <tr class="<?php echo $isLow ? 'table-warning' : ''; ?>">
                            <td><code><?php echo htmlspecialchars($r['teacher_code']); ?></code></td>
                            <td>
                                <a href="teacher_detail.php?id=<?php echo (int)$r['id']; ?>">
                                    <?php echo htmlspecialchars($r['full_name']); ?>
                                </a>
                            </td>
                            <td class="text-center">
                                <?php if ($r['avg_score'] !== null): ?>
                                <span class="fw-bold <?php echo $isLow ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo number_format($r['avg_score'], 2); ?>/5.0
                                    <?php if ($isLow): ?>
                                    <i class="bi bi-exclamation-triangle-fill ms-1" aria-label="Diem thap" title="Diem TB < 3.0"></i>
                                    <?php endif; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">Chua co ket qua</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $r['total_responses']; ?></td>
                            <td>
                                <?php if ($r['avg_score'] === null): ?>
                                <span class="text-muted small">Chua co ket qua danh gia</span>
                                <?php elseif ($isLow): ?>
                                <span class="badge bg-warning text-dark">Can cai thien</span>
                                <?php elseif ($r['avg_score'] >= 4.0): ?>
                                <span class="badge bg-success">Tot</span>
                                <?php else: ?>
                                <span class="badge bg-info text-dark">Kha</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot
    </div>
</div>

<?php include 'includes/footer.php'; ?>
