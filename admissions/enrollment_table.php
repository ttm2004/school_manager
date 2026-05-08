<?php
/**
 * Partial: bảng danh sách hồ sơ nhập học
 * Được include trực tiếp từ enrollment.php (lần đầu)
 * và load qua AJAX (các lần sau, khi ?ajax=1)
 *
 * Biến cần có: $applications, $tab, $offset, $total, $totalPages, $page,
 *              $enrollLocked, $canEnroll, $isManager, $filter_major, $filter_search
 */

// Nếu gọi qua AJAX — bootstrap lại các biến cần thiết
if (isset($_GET['ajax'])) {
    require_once '../config/database.php';
    require_once '../includes/auth.php';
    requireAnyRole(['admissions_manager', 'admissions_staff']);

    $isManager   = hasRole('admissions_manager');
    $canEnroll   = hasPermission('admissions', 'manage_enrollment');
    $roundPhase  = getRoundPhase();
    $enrollAllowed = in_array($roundPhase, ['enrolling', 'supp_enrolling']);
    $enrollLocked  = !$enrollAllowed;

    $tab          = $_GET['tab'] ?? 'approved';
    $filter_major = intval($_GET['major_id'] ?? 0);
    $filter_search= trim($_GET['q'] ?? '');
    $statusFilter = $tab === 'enrolled' ? 'enrolled' : 'approved';

    $where  = ["aa.status='$statusFilter'"];
    $params = []; $types = '';
    if ($filter_major) { $where[] = 'aa.major_id=?'; $params[] = $filter_major; $types .= 'i'; }
    if ($filter_search) {
        $like = "%$filter_search%";
        $where[] = '(aa.full_name LIKE ? OR aa.email LIKE ? OR aa.citizen_id LIKE ?)';
        $params = array_merge($params, [$like, $like, $like]); $types .= 'sss';
    }
    $wSQL    = 'WHERE ' . implode(' AND ', $where);
    $perPage = 15;
    $page    = max(1, intval($_GET['page'] ?? 1));
    $offset  = ($page - 1) * $perPage;

    $cSQL = "SELECT COUNT(*) c FROM admission_applications aa $wSQL";
    if ($params) { $cs = $conn->prepare($cSQL); $cs->bind_param($types, ...$params); $cs->execute(); $total = (int)$cs->get_result()->fetch_assoc()['c']; $cs->close(); }
    else { $total = (int)$conn->query($cSQL)->fetch_assoc()['c']; }
    $totalPages = ceil($total / $perPage);

    $dSQL = "SELECT aa.*, m.major_name, m.major_code, am.method_name,
        (aa.math_score+aa.literature_score+aa.english_score) as total_score,
        (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email LIMIT 1) as has_account
        FROM admission_applications aa
        LEFT JOIN majors m ON aa.major_id=m.id
        LEFT JOIN admission_methods am ON aa.method_id=am.id
        $wSQL ORDER BY aa.created_at DESC LIMIT ? OFFSET ?";
    $allP = array_merge($params, [$perPage, $offset]); $allT = $types . 'ii';
    $stmt = $conn->prepare($dSQL); $stmt->bind_param($allT, ...$allP); $stmt->execute();
    $applications = $stmt->get_result(); $stmt->close();
}
?>
<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr>
            <th>#</th><th>Họ tên</th><th>Ngành</th><th>Phương thức</th>
            <th>Tổng điểm</th><th>Ngày nộp</th>
            <?php if ($tab === 'enrolled'): ?><th>Tài khoản SV</th><?php endif; ?>
            <th>Thao tác</th>
        </tr></thead>
        <tbody>
        <?php if ($applications && $applications->num_rows > 0):
            $idx = $offset + 1;
            while ($app = $applications->fetch_assoc()): ?>
        <tr id="row-<?php echo $app['id']; ?>">
            <td class="text-muted small"><?php echo $idx++; ?></td>
            <td>
                <div class="fw-semibold small"><?php echo htmlspecialchars($app['full_name']); ?></div>
                <div class="text-muted" style="font-size:.72rem"><?php echo htmlspecialchars($app['email']); ?></div>
                <?php if ($app['phone']): ?>
                <div class="text-muted" style="font-size:.72rem"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($app['phone']); ?></div>
                <?php endif; ?>
            </td>
            <td class="small text-muted"><?php echo htmlspecialchars($app['major_name'] ?? '--'); ?></td>
            <td class="small text-muted"><?php echo mb_substr($app['method_name'] ?? '--', 0, 20); ?></td>
            <td class="fw-bold text-success"><?php echo number_format($app['total_score'] ?? 0, 2); ?></td>
            <td class="text-muted small"><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>

            <?php if ($tab === 'enrolled'): ?>
            <td>
                <?php if ($app['has_account']): ?>
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Đã cấp</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Chưa cấp</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>

            <td>
                <?php if ($tab !== 'enrolled'): ?>
                    <?php if ($canEnroll && !$enrollLocked): ?>
                    <button class="btn btn-sm btn-success btn-enroll"
                        data-id="<?php echo $app['id']; ?>"
                        data-name="<?php echo htmlspecialchars($app['full_name'], ENT_QUOTES); ?>">
                        <i class="bi bi-person-check-fill me-1"></i>Nhập học
                    </button>
                    <?php elseif ($enrollLocked): ?>
                    <span class="text-muted small"><i class="bi bi-lock me-1"></i>Bị khóa</span>
                    <?php else: ?>
                    <span class="text-muted small"><i class="bi bi-lock me-1"></i>Không có quyền</span>
                    <?php endif; ?>
                <?php else: ?>
                <div class="d-flex gap-1 flex-wrap">
                    <?php if (!$app['has_account'] && $canEnroll && !$enrollLocked): ?>
                    <button class="btn btn-sm btn-gold btn-create-account"
                        data-id="<?php echo $app['id']; ?>"
                        data-name="<?php echo htmlspecialchars($app['full_name'], ENT_QUOTES); ?>"
                        data-major-id="<?php echo intval($app['major_id']); ?>">
                        <i class="bi bi-person-plus-fill me-1"></i>Cấp TK
                    </button>
                    <?php elseif ($app['has_account']): ?>
                    <span class="btn btn-sm btn-outline-success disabled"><i class="bi bi-check2 me-1"></i>Đã cấp TK</span>
                    <?php elseif ($enrollLocked): ?>
                    <span class="text-muted small"><i class="bi bi-lock me-1"></i>Bị khóa</span>
                    <?php endif; ?>

                    <?php if ($isManager && !$enrollLocked): ?>
                    <button class="btn btn-sm btn-outline-danger btn-cancel-enroll"
                        data-id="<?php echo $app['id']; ?>"
                        data-name="<?php echo htmlspecialchars($app['full_name'], ENT_QUOTES); ?>"
                        title="Hủy nhập học (chỉ Trưởng phòng)">
                        <i class="bi bi-x-circle"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có hồ sơ nào
        </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
    <small class="text-muted">Hiển thị <?php echo $offset+1; ?>–<?php echo min($offset+$perPage,$total); ?> / <?php echo $total; ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <li class="page-item <?php echo $p==$page?'active':''; ?>">
            <a class="page-link page-ajax" href="#" data-page="<?php echo $p; ?>"><?php echo $p; ?></a>
        </li>
        <?php endfor; ?>
    </ul></nav>
</div>
<?php endif; ?>

<?php if (isset($_GET['ajax'])) exit(); ?>
