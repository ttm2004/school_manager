<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Kết quả Xét tuyển';
include __DIR__ . '/includes/header.php';

$filter_major  = intval($_GET['major_id'] ?? 0);
$filter_status = trim($_GET['status'] ?? 'approved');
$filter_search = trim($_GET['q'] ?? '');
$filter_mode   = trim($_GET['mode'] ?? 'system');
if (!in_array($filter_mode, ['system','test'], true)) $filter_mode = 'system';
$perPage = 20;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = ["aa.status IN ('approved','rejected','enrolled')"];
$params = []; $types = '';
if ($filter_mode !== 'all') { $where[] = 'aa.data_mode=?'; $params[] = $filter_mode; $types .= 's'; }
if ($filter_status && in_array($filter_status, ['approved','rejected','enrolled'])) {
    $where[] = 'aa.status=?'; $params[] = $filter_status; $types .= 's';
}
if ($filter_major) { $where[] = 'aa.major_id=?'; $params[] = $filter_major; $types .= 'i'; }
if ($filter_search) {
    $where[] = '(aa.full_name LIKE ? OR aa.citizen_id LIKE ?)';
    $like = "%$filter_search%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
$wSQL = 'WHERE ' . implode(' AND ', $where);

$cSQL = "SELECT COUNT(*) as c FROM admission_applications aa $wSQL";
if ($params) { $cs = $conn->prepare($cSQL); $cs->bind_param($types,...$params); $cs->execute(); $total = $cs->get_result()->fetch_assoc()['c']; $cs->close(); }
else { $total = $conn->query($cSQL)->fetch_assoc()['c']; }
$totalPages = ceil($total / $perPage);

$dSQL = "SELECT aa.*, m.major_name, m.major_code, am.method_name,
    (aa.math_score + aa.literature_score + aa.english_score) as total_score
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id = m.id
    LEFT JOIN admission_methods am ON aa.method_id = am.id
    $wSQL ORDER BY total_score DESC LIMIT ? OFFSET ?";
$allP = array_merge($params, [$perPage, $offset]);
$allT = $types . 'ii';
$stmt = $conn->prepare($dSQL);
$stmt->bind_param($allT, ...$allP);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$majors = $conn->query("SELECT id, major_name FROM majors ORDER BY major_name");

// Summary stats
$modeSql = $filter_mode === 'all' ? '1=1' : "data_mode='" . $conn->real_escape_string($filter_mode) . "'";
$sumApproved = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='approved' AND $modeSql")->fetch_assoc()['c'] ?? 0;
$sumRejected = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='rejected' AND $modeSql")->fetch_assoc()['c'] ?? 0;
$sumEnrolled = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='enrolled' AND $modeSql")->fetch_assoc()['c'] ?? 0;
?>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #059669">
            <div class="ic" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-check-circle-fill"></i></div>
            <div class="vl"><?php echo number_format($sumApproved); ?></div>
            <div class="lb">Đã trúng tuyển</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #dc2626">
            <div class="ic" style="background:rgba(239,68,68,.12);color:#dc2626"><i class="bi bi-x-circle-fill"></i></div>
            <div class="vl"><?php echo number_format($sumRejected); ?></div>
            <div class="lb">Không trúng tuyển</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #7c3aed">
            <div class="ic" style="background:rgba(139,92,246,.12);color:#7c3aed"><i class="bi bi-person-check-fill"></i></div>
            <div class="vl"><?php echo number_format($sumEnrolled); ?></div>
            <div class="lb">Đã nhập học</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-trophy-fill me-2"></i>Kết quả xét tuyển</span>
        <a href="?<?php echo http_build_query(['mode'=>$filter_mode,'status'=>$filter_status,'major_id'=>$filter_major,'q'=>$filter_search,'export'=>1]); ?>"
           class="btn btn-sm" style="background:#28a745;color:#fff"><i class="bi bi-file-earmark-csv me-1"></i>Xuất CSV</a>
    </div>
    <!-- Filter -->
    <div class="card-body border-bottom py-2">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <select name="mode" class="form-select form-select-sm" style="width:150px">
                    <option value="system" <?php echo $filter_mode==='system'?'selected':''; ?>>Dữ liệu thật</option>
                    <option value="test" <?php echo $filter_mode==='test'?'selected':''; ?>>Test / Demo</option>
                                    </select>
            </div>
            <div>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Tên, CCCD..." value="<?php echo htmlspecialchars($filter_search); ?>" style="width:180px">
            </div>
            <div>
                <select name="status" class="form-select form-select-sm" style="width:150px">
                    <option value="">Tất cả kết quả</option>
                    <option value="approved" <?php echo $filter_status==='approved'?'selected':''; ?>>Trúng tuyển</option>
                    <option value="rejected" <?php echo $filter_status==='rejected'?'selected':''; ?>>Không trúng tuyển</option>
                    <option value="enrolled" <?php echo $filter_status==='enrolled'?'selected':''; ?>>Đã nhập học</option>
                </select>
            </div>
            <div>
                <select name="major_id" class="form-select form-select-sm" style="width:200px">
                    <option value="">Tất cả ngành</option>
                    <?php if ($majors): while ($m = $majors->fetch_assoc()): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo $filter_major==$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['major_name']); ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search me-1"></i>Lọc</button>
            <?php if ($filter_status || $filter_major || $filter_search || $filter_mode !== 'system'): ?>
            <a href="results.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x me-1"></i>Xóa lọc</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr>
                    <th>Hạng</th><th>Họ tên</th><th>Ngành</th><th>Phương thức</th>
                    <th>Toán</th><th>Văn</th><th>Anh</th><th>Tổng điểm</th><th>Kết quả</th>
                </tr></thead>
                <tbody>
                <?php
                $sMap = ['approved'=>['Trúng tuyển','bs-approved'],'rejected'=>['Không trúng','bs-rejected'],'enrolled'=>['Đã nhập học','bs-enrolled']];
                if ($rows && $rows->num_rows > 0):
                    $rank = $offset + 1;
                    while ($r = $rows->fetch_assoc()):
                    $s = $sMap[$r['status']] ?? [$r['status'],''];
                ?>
                <tr>
                    <td class="fw-bold text-muted"><?php echo $rank++; ?></td>
                    <td>
                        <div class="fw-semibold small"><?php echo htmlspecialchars($r['full_name']); ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?php echo htmlspecialchars($r['citizen_id']??''); ?></div>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($r['major_name']??'—'); ?></td>
                    <td class="small text-muted"><?php echo mb_substr($r['method_name']??'—',0,20); ?></td>
                    <td class="text-center"><?php echo number_format($r['math_score']??0,2); ?></td>
                    <td class="text-center"><?php echo number_format($r['literature_score']??0,2); ?></td>
                    <td class="text-center"><?php echo number_format($r['english_score']??0,2); ?></td>
                    <td class="text-center fw-bold fs-6 text-success"><?php echo number_format($r['total_score']??0,2); ?></td>
                    <td><span class="bs <?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có dữ liệu</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <small class="text-muted">Hiển thị <?php echo $offset+1; ?>–<?php echo min($offset+$perPage,$total); ?> / <?php echo $total; ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
            <li class="page-item <?php echo $p==$page?'active':''; ?>">
                <a class="page-link" href="?mode=<?php echo urlencode($filter_mode); ?>&status=<?php echo urlencode($filter_status); ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
