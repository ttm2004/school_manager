<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Nhat ky Thao tac';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tai khoan chua duoc gan vao khoa nao.'];
    header('Location: /university/login.php');
    exit();
}

$flash = getFlash();

// Filters
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$actor      = trim($_GET['actor'] ?? '');

// Build WHERE
$whereParts = ["al.module = 'faculty'"];
$bindTypes  = '';
$bindValues = [];

if ($dateFrom !== '') {
    $whereParts[] = 'al.created_at >= ?';
    $bindTypes   .= 's';
    $bindValues[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $whereParts[] = 'al.created_at <= ?';
    $bindTypes   .= 's';
    $bindValues[] = $dateTo . ' 23:59:59';
}
if ($actionType !== '') {
    $whereParts[] = 'al.action_type = ?';
    $bindTypes   .= 's';
    $bindValues[] = $actionType;
}
if ($actor !== '') {
    $whereParts[] = 'u.full_name LIKE ?';
    $bindTypes   .= 's';
    $bindValues[] = '%' . $actor . '%';
}

$whereSQL = implode(' AND ', $whereParts);

// Count
$countSQL = "SELECT COUNT(*) AS c FROM faculty_audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE {$whereSQL}";
if ($bindTypes !== '') {
    $stmtCount = $conn->prepare($countSQL);
    $stmtCount->bind_param($bindTypes, ...$bindValues);
    $stmtCount->execute();
    $totalLogs = (int)($stmtCount->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtCount->close();
} else {
    $totalLogs = (int)($conn->query($countSQL)->fetch_assoc()['c'] ?? 0);
}

$pag = paginate($totalLogs, $page, $perPage);

// Fetch
$dataSQL = "SELECT al.id, al.action_type, al.module, al.table_name, al.record_id,
                   al.ip_address, al.created_at,
                   u.full_name AS actor_name
            FROM faculty_audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE {$whereSQL}
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?";

$allTypes  = $bindTypes . 'ii';
$allValues = array_merge($bindValues, [$pag['per_page'], $pag['offset']]);

if ($allTypes !== 'ii') {
    $stmtData = $conn->prepare($dataSQL);
    $stmtData->bind_param($allTypes, ...$allValues);
    $stmtData->execute();
    $logs = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();
} else {
    $stmtData = $conn->prepare($dataSQL);
    $stmtData->bind_param('ii', $pag['per_page'], $pag['offset']);
    $stmtData->execute();
    $logs = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();
}

$qsParts = [];
if ($dateFrom !== '') $qsParts[] = 'date_from=' . urlencode($dateFrom);
if ($dateTo !== '')   $qsParts[] = 'date_to=' . urlencode($dateTo);
if ($actionType !== '') $qsParts[] = 'action_type=' . urlencode($actionType);
if ($actor !== '')    $qsParts[] = 'actor=' . urlencode($actor);
$queryString = implode('&', $qsParts);

$actionBadgeMap = [
    'create'       => ['success', 'Tao moi'],
    'update'       => ['info', 'Cap nhat'],
    'delete'       => ['danger', 'Xoa'],
    'submit'       => ['warning', 'Gui'],
    'approve'      => ['success', 'Duyet'],
    'reject'       => ['danger', 'Tu choi'],
    'restore'      => ['secondary', 'Khoi phuc'],
    'export'       => ['primary', 'Xuat'],
    'login_denied' => ['dark', 'Tu choi DX'],
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
                <i class="bi bi-shield-check me-2 text-navy" aria-hidden="true"></i>Nhat ky Thao tac
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
                <form method="get" action="audit_log.php" class="row g-3 align-items-end">
                    <div class="col-6 col-md-2">
                        <label for="date_from" class="form-label">Tu ngay</label>
                        <input type="date" id="date_from" name="date_from" class="form-control"
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="date_to" class="form-label">Den ngay</label>
                        <input type="date" id="date_to" name="date_to" class="form-control"
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="action_type" class="form-label">Loai hanh dong</label>
                        <select id="action_type" name="action_type" class="form-select">
                            <option value="">-- Tat ca --</option>
                            <?php foreach (array_keys($actionBadgeMap) as $at): ?>
                            <option value="<?php echo htmlspecialchars($at); ?>"
                                <?php echo $actionType === $at ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($actionBadgeMap[$at][1]); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="actor" class="form-label">Nguoi thuc hien</label>
                        <input type="text" id="actor" name="actor" class="form-control"
                               placeholder="Ten nguoi dung..."
                               value="<?php echo htmlspecialchars($actor); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Loc nhat ky">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Loc
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-check me-2" aria-hidden="true"></i>
                Nhat ky Thao tac
                <span class="badge bg-light text-dark ms-2"><?php echo number_format($totalLogs); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Thoi gian</th>
                            <th>Nguoi thuc hien</th>
                            <th>Hanh dong</th>
                            <th>Bang</th>
                            <th>Record ID</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Khong co nhat ky nao.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <?php $ab = $actionBadgeMap[$log['action_type']] ?? ['secondary', $log['action_type']]; ?>
                        <tr>
                            <td class="text-muted small"><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                            <td><span class="badge bg-<?php echo $ab[0]; ?>"><?php echo $ab[1]; ?></span></td>
                            <td><code><?php echo htmlspecialchars($log['table_name']); ?></code></td>
                            <td><?php echo (int)$log['record_id']; ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pag['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Hien thi <?php echo $pag['offset'] + 1; ?>-<?php echo min($pag['offset'] + $pag['per_page'], $pag['total']); ?>
                    / <?php echo number_format($pag['total']); ?>
                </small>
                <?php echo renderPagination($pag, $queryString); ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot
    </div>
</div>

<?php include 'includes/footer.php'; ?>
