<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
$pageTitle = 'Quản lý hồ sơ';

$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, intval($_GET['page'] ?? 1));
$limit         = 20;
$offset        = ($page - 1) * $limit;

$where = []; $params = []; $types = '';
if ($status_filter !== 'all') { $where[] = 'r.status=?'; $params[] = $status_filter; $types .= 's'; }
if ($search) {
    $where[] = '(r.fullname LIKE ? OR r.phone LIKE ? OR r.email LIKE ? OR r.identification LIKE ?)';
    $like = "%$search%"; $params = array_merge($params, [$like,$like,$like,$like]); $types .= 'ssss';
}
$wSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

// Count
$cSQL = "SELECT COUNT(*) as c FROM adm_registrations r $wSQL";
if ($params) { $cs = $conn->prepare($cSQL); $cs->bind_param($types,...$params); $cs->execute(); $total = $cs->get_result()->fetch_assoc()['c']; }
else { $total = $conn->query($cSQL)->fetch_assoc()['c']; }
$totalPages = ceil($total / $limit);

// Data
$dSQL = "SELECT r.*, m.major_name, am.method_name
    FROM adm_registrations r
    LEFT JOIN majors m ON r.major_id = m.id
    LEFT JOIN adm_methods am ON r.method_code = am.code
    $wSQL ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
$allP = array_merge($params, [$limit, $offset]);
$allT = $types . 'ii';
$stmt = $conn->prepare($dSQL);
$stmt->bind_param($allT, ...$allP);
$stmt->execute();
$rows = $stmt->get_result();

// Counts for tabs
$counts = [];
foreach (['all','pending','approved','rejected'] as $s) {
    $q = $s === 'all' ? "SELECT COUNT(*) as c FROM adm_registrations" : "SELECT COUNT(*) as c FROM adm_registrations WHERE status='$s'";
    $counts[$s] = $conn->query($q)->fetch_assoc()['c'] ?? 0;
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_SESSION['adm_success'])): ?>
<div class="alert alert-success auto-dismiss"><?php echo $_SESSION['adm_success']; unset($_SESSION['adm_success']); ?></div>
<?php endif; ?>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (['all'=>'Tất cả','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'] as $s => $label):
                    $cls = $status_filter === $s ? 'btn-navy' : 'btn-outline-secondary';
                ?>
                <a href="?status=<?php echo $s; ?>&q=<?php echo urlencode($search); ?>" class="btn btn-sm <?php echo $cls; ?>">
                    <?php echo $label; ?> <span class="badge bg-light text-dark ms-1"><?php echo $counts[$s]; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <form class="d-flex gap-2" method="GET">
                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Tìm tên, SĐT, CCCD..." value="<?php echo htmlspecialchars($search); ?>" style="width:220px;">
                <button class="btn btn-sm btn-navy"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-alt me-2"></i>Danh sách hồ sơ (<?php echo number_format($total); ?>)</span>
        <a href="../api/export_registrations.php?status=<?php echo $status_filter; ?>&q=<?php echo urlencode($search); ?>" class="btn btn-sm" style="background:#28a745;color:#fff;">
            <i class="fas fa-file-csv me-1"></i>Xuất CSV
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr>
                    <th>Mã HS</th><th>Thông tin</th><th>Ngành / Phương thức</th>
                    <th>Ngày đăng ký</th><th>Trạng thái</th><th>Thao tác</th>
                </tr></thead>
                <tbody>
                <?php if ($rows && $rows->num_rows > 0):
                    while ($r = $rows->fetch_assoc()):
                    $sc = ['pending'=>'badge-pending','approved'=>'badge-approved','rejected'=>'badge-rejected'];
                    $st = ['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'];
                ?>
                <tr>
                    <td><code>#<?php echo str_pad($r['id'],6,'0',STR_PAD_LEFT); ?></code></td>
                    <td>
                        <strong><?php echo htmlspecialchars($r['fullname']); ?></strong><br>
                        <small class="text-muted">
                            <i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($r['phone']); ?>
                            &nbsp;|&nbsp;<i class="fas fa-id-card fa-xs me-1"></i><?php echo htmlspecialchars($r['identification']); ?>
                        </small>
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars($r['major_name'] ?? '—'); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($r['method_name'] ?? '—'); ?></small>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($r['created_at'])); ?><br>
                        <small class="text-muted"><?php echo date('H:i', strtotime($r['created_at'])); ?></small></td>
                    <td><span class="badge-status <?php echo $sc[$r['status']]; ?>"><?php echo $st[$r['status']]; ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="registration_detail.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-navy" title="Xem chi tiết"><i class="fas fa-eye"></i></a>
                            <?php if ($r['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-success" onclick="processReg(<?php echo $r['id']; ?>,'approved')" title="Duyệt"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="processReg(<?php echo $r['id']; ?>,'rejected')" title="Từ chối"><i class="fas fa-times"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>Không có hồ sơ nào
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-body border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">Hiển thị <?php echo $offset+1; ?>–<?php echo min($offset+$limit,$total); ?> / <?php echo $total; ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <li class="page-item <?php echo $i==$page?'active':''; ?>">
                <a class="page-link" href="?status=<?php echo $status_filter; ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<script>
function processReg(id, action) {
    if (!confirm((action==='approved'?'Duyệt':'Từ chối') + ' hồ sơ này?')) return;
    fetch('../api/process_registration.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id, action})
    }).then(r=>r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Lỗi: ' + d.message);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
