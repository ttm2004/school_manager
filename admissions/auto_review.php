<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);
$pageTitle = 'Xét tuyển tự động';
include __DIR__ . '/includes/header.php';

$success = $error = '';

// ── Kiểm tra giai đoạn đợt tuyển sinh ──────────────────────
$roundPhase  = getRoundPhase();
$isReviewing = isReviewingPhase();
$roundMsg    = getRoundStatusMessage();
$activeRound = getActiveRound();

// auto_review CHỈ mở khi đang trong giai đoạn xét tuyển
// Tất cả giai đoạn khác đều khóa hoàn toàn
$reviewAllowed = in_array($roundPhase, ['reviewing', 'supp_reviewing']);
$reviewLocked  = !$reviewAllowed;

// Trong giai đoạn xét tuyển: tất cả đều được thao tác (không phân biệt admin/staff)
$canManualReview = $reviewAllowed;

// Load majors
$majors = $conn->query("SELECT id, major_name, major_code FROM majors WHERE status='open' ORDER BY major_name");

// Handle auto-review action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Run auto-review: approve candidates whose total score >= threshold
    if ($action === 'run_auto_review') {
        if (!hasPermission('admissions', 'run_auto_review')) {
            $error = 'Bạn không có quyền chạy xét tuyển tự động.';
        } elseif ($reviewLocked) {
            $error = '⚠️ Chức năng xét tuyển chỉ khả dụng trong giai đoạn xét tuyển.';
        } else {
        $major_id  = intval($_POST['major_id'] ?? 0);
        $threshold = floatval($_POST['threshold'] ?? 0);
        $quota     = intval($_POST['quota'] ?? 0);

        if ($major_id && $threshold > 0) {
            $sql = "SELECT id, (math_score + literature_score + english_score) as total_score
                    FROM admission_applications
                    WHERE major_id = ? AND status IN ('new','checking')
                    ORDER BY total_score DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $major_id);
            $stmt->execute();
            $candidates = $stmt->get_result();
            $stmt->close();

            $approved = 0; $rejected = 0; $rank = 0;
            while ($c = $candidates->fetch_assoc()) {
                $rank++;
                if ($c['total_score'] >= $threshold && ($quota === 0 || $rank <= $quota)) {
                    $upd = $conn->prepare("UPDATE admission_applications SET status='approved' WHERE id=?");
                    $upd->bind_param('i', $c['id']); $upd->execute(); $upd->close();
                    $approved++;
                } else {
                    $upd = $conn->prepare("UPDATE admission_applications SET status='rejected' WHERE id=?");
                    $upd->bind_param('i', $c['id']); $upd->execute(); $upd->close();
                    $rejected++;
                }
            }
            $success = "Xét tuyển hoàn tất: <strong>$approved</strong> hồ sơ được duyệt, <strong>$rejected</strong> hồ sơ bị từ chối.";
        } else {
            $error = 'Vui lòng chọn ngành và nhập điểm chuẩn.';
        }
        } // end hasPermission
    }

    if ($action === 'bulk_approve') {
        if (!hasPermission('admissions', 'approve_application')) {
            $error = 'Bạn không có quyền duyệt hồ sơ.';
        } elseif ($reviewLocked) {
            $error = '⚠️ Chức năng xét tuyển chỉ khả dụng trong giai đoạn xét tuyển.';
        } else {
            $ids = $_POST['ids'] ?? []; $count = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $upd = $conn->prepare("UPDATE admission_applications SET status='approved' WHERE id=?");
                    $upd->bind_param('i', $id); $upd->execute(); $upd->close(); $count++;
                }
            }
            $success = "Đã duyệt <strong>$count</strong> hồ sơ.";
        }
    }

    if ($action === 'bulk_reject') {
        if (!hasPermission('admissions', 'approve_application')) {
            $error = 'Bạn không có quyền từ chối hồ sơ.';
        } elseif ($reviewLocked) {
            $error = '⚠️ Chức năng xét tuyển chỉ khả dụng trong giai đoạn xét tuyển.';
        } else {
            $ids = $_POST['ids'] ?? []; $count = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $upd = $conn->prepare("UPDATE admission_applications SET status='rejected' WHERE id=?");
                    $upd->bind_param('i', $id); $upd->execute(); $upd->close(); $count++;
                }
            }
            $success = "Đã từ chối <strong>$count</strong> hồ sơ.";
        }
    }
}

// Load pending applications for manual review
$filter_major = intval($_GET['major_id'] ?? 0);
$whereSQL = $filter_major ? "WHERE aa.major_id = $filter_major AND aa.status IN ('new','checking')" : "WHERE aa.status IN ('new','checking')";

$pending = $conn->query("
    SELECT aa.*, m.major_name, am.method_name,
           (aa.math_score + aa.literature_score + aa.english_score) as total_score
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id = m.id
    LEFT JOIN admission_methods am ON aa.method_id = am.id
    $whereSQL
    ORDER BY total_score DESC
");

$pendingCount = $pending ? $pending->num_rows : 0;

// Reload majors for filter
$majorsFilter = $conn->query("SELECT id, major_name FROM majors WHERE status='open' ORDER BY major_name");

// include 'includes/header.php';
// include 'includes/sidebar.php';
?>
        <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <!-- Modal thông báo trạng thái đợt tuyển sinh -->
        <?php
        $arColor = $reviewLocked ? '#6b7280' : '#059669';
        $arBg    = $reviewLocked ? 'rgba(107,114,128,.08)' : 'rgba(16,185,129,.08)';
        $arIconBg= $reviewLocked ? 'rgba(107,114,128,.15)' : 'rgba(16,185,129,.15)';
        $arIcon  = $reviewLocked ? 'bi-lock-fill' : 'bi-robot';
        $arTitle = $reviewLocked ? 'Chức năng bị khóa' : 'Đang trong giai đoạn xét tuyển';
        $lockReasonMap = [
            'no_round'       => 'Không có đợt tuyển sinh nào đang hoạt động.',
            'before_reg'     => 'Chưa đến thời gian nhận hồ sơ.',
            'reg_open'       => 'Đang nhận hồ sơ — chưa đến giai đoạn xét tuyển.',
            'enrolling'      => 'Đã qua giai đoạn xét tuyển — đang trong thời gian nhập học.',
            'supp_enrolling' => 'Đang trong giai đoạn nhập học bổ sung.',
            'after_enroll'   => 'Đã hết hạn nhập học.',
            'completed'      => 'Đợt tuyển sinh đã hoàn tất.',
        ];
        $arBody = $reviewLocked
            ? ($lockReasonMap[$roundPhase] ?? 'Không trong giai đoạn xét tuyển.') . ' Chức năng xét tuyển tự động chỉ khả dụng khi đợt tuyển sinh ở trạng thái <strong>Đang xét tuyển</strong>.'
            : 'Hệ thống đang trong giai đoạn xét tuyển. Tất cả chức năng xét tuyển đã được mở. Bạn có thể chạy xét tuyển tự động hoặc duyệt/từ chối thủ công.';
        $arDuration = $reviewLocked ? 8000 : 5000;
        ?>
        <div class="modal fade" id="arStatusModal" tabindex="-1" <?php echo $reviewLocked ? 'data-bs-backdrop="static" data-bs-keyboard="false"' : ''; ?>>
            <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
                <div class="modal-content border-0 shadow-lg overflow-hidden">
                    <div class="modal-header border-0 pb-0" style="background:<?php echo $arBg; ?>">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:42px;height:42px;border-radius:50%;background:<?php echo $arIconBg; ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                                <i class="bi <?php echo $arIcon; ?>" style="color:<?php echo $arColor; ?>;"></i>
                            </div>
                            <h5 class="modal-title fw-bold mb-0" style="color:<?php echo $arColor; ?>;"><?php echo $arTitle; ?></h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" id="arModalClose"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <p class="mb-0" style="font-size:.9rem;line-height:1.6;"><?php echo $arBody; ?></p>
                        <div class="mt-3 d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:4px;border-radius:4px;">
                                <div id="arModalProgress" class="progress-bar" style="width:100%;background:<?php echo $arColor; ?>;transition:width linear;"></div>
                            </div>
                            <small class="text-muted" id="arModalCountdown" style="font-size:.72rem;white-space:nowrap;"></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = new bootstrap.Modal(document.getElementById('arStatusModal'));
            modal.show();
            const duration = <?php echo $arDuration; ?>;
            const bar = document.getElementById('arModalProgress');
            const countdown = document.getElementById('arModalCountdown');
            let remaining = Math.ceil(duration / 1000);
            countdown.textContent = remaining + 's';
            const tick = setInterval(function() { remaining--; countdown.textContent = remaining + 's'; if (remaining <= 0) { clearInterval(tick); modal.hide(); } }, 1000);
            requestAnimationFrame(function() { requestAnimationFrame(function() { bar.style.transitionDuration = duration + 'ms'; bar.style.width = '0%'; }); });
            document.getElementById('arModalClose').addEventListener('click', function() { clearInterval(tick); });
        });
        </script>

        <div class="row g-4 mb-4">
            <!-- Auto Review Config -->
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-robot me-2"></i>Cấu hình xét tuyển tự động
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Hệ thống sẽ tự động duyệt/từ chối hồ sơ có trạng thái <strong>Mới</strong> hoặc <strong>Đang xét</strong>
                            dựa trên điểm chuẩn và chỉ tiêu bạn nhập.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="run_auto_review">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Ngành xét tuyển <span class="text-danger">*</span></label>
                                <select name="major_id" class="form-select" required>
                                    <option value="">-- Chọn ngành --</option>
                                    <?php if ($majors): while ($m = $majors->fetch_assoc()): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Điểm chuẩn tối thiểu <span class="text-danger">*</span></label>
                                <input type="number" name="threshold" class="form-control" step="0.25" min="0" max="30" placeholder="VD: 18.5" required>
                                <div class="form-text">Tổng 3 môn (Toán + Văn + Anh). Tối đa 30 điểm.</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Chỉ tiêu tuyển sinh</label>
                                <input type="number" name="quota" class="form-control" min="0" placeholder="0 = không giới hạn">
                                <div class="form-text">Nhập 0 hoặc để trống nếu không giới hạn chỉ tiêu.</div>
                            </div>
                            <div class="alert alert-warning py-2 small">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Thao tác này sẽ cập nhật trạng thái tất cả hồ sơ đang chờ của ngành đã chọn. Không thể hoàn tác.
                            </div>
                            <button type="submit" class="btn btn-navy w-100" onclick="return confirm('Xác nhận chạy xét tuyển tự động?')"
                                <?php echo !hasPermission('admissions','run_auto_review') ? 'disabled title="Bạn không có quyền thực hiện"' : ''; ?>
                                <?php echo $reviewLocked ? 'disabled title="Chỉ khả dụng trong giai đoạn xét tuyển"' : ''; ?>>
                                <i class="bi bi-play-circle-fill me-2"></i>Chạy xét tuyển tự động
                            </button>
                            <?php if (!hasPermission('admissions','run_auto_review')): ?>
                            <div class="text-muted small mt-2"><i class="bi bi-lock me-1"></i>Chỉ Trưởng phòng mới có quyền chạy xét tuyển tự động.</div>
                            <?php elseif ($reviewLocked): ?>
                            <div class="text-muted small mt-2"><i class="bi bi-lock me-1"></i>Chức năng chỉ khả dụng khi đợt tuyển sinh ở trạng thái <strong>Đang xét tuyển</strong>.</div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pending Summary -->
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-hourglass-split me-2"></i>Hồ sơ chờ xét (<span class="text-gold"><?php echo $pendingCount; ?></span>)</span>
                        <form method="GET" class="d-flex gap-2">
                            <select name="major_id" class="form-select form-select-sm" style="width:180px" onchange="this.form.submit()">
                                <option value="">Tất cả ngành</option>
                                <?php if ($majorsFilter): while ($m = $majorsFilter->fetch_assoc()): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo $filter_major==$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['major_name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <form method="POST" id="bulkForm">
                            <div class="p-2 border-bottom d-flex gap-2">
                                <?php if (hasPermission('admissions','approve_application') && $canManualReview): ?>
                                <button type="submit" name="action" value="bulk_approve" class="btn btn-sm btn-success" onclick="return confirmBulk('duyệt')">
                                    <i class="bi bi-check-all me-1"></i>Duyệt đã chọn
                                </button>
                                <button type="submit" name="action" value="bulk_reject" class="btn btn-sm btn-danger" onclick="return confirmBulk('từ chối')">
                                    <i class="bi bi-x-lg me-1"></i>Từ chối đã chọn
                                </button>
                                <?php elseif ($reviewLocked): ?>
                                <span class="text-muted small"><i class="bi bi-lock me-1"></i>Chỉ khả dụng trong giai đoạn xét tuyển</span>
                                <?php else: ?>
                                <span class="text-muted small"><i class="bi bi-lock me-1"></i>Chỉ xem — không có quyền duyệt</span>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="toggleAll()">Chọn tất cả</button>
                            </div>
                            <div class="table-responsive" style="max-height:380px;overflow-y:auto;">
                                <table class="table table-hover table-sm mb-0">
                                    <thead style="position:sticky;top:0;z-index:1;">
                                        <tr><th width="30"><input type="checkbox" id="checkAll"></th><th>Họ tên</th><th>Ngành</th><th>Toán</th><th>Văn</th><th>Anh</th><th class="text-success">Tổng</th><th>Trạng thái</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($pending && $pending->num_rows > 0): while ($app = $pending->fetch_assoc()): ?>
                                        <tr>
                                            <td><input type="checkbox" name="ids[]" value="<?php echo $app['id']; ?>" class="app-check"></td>
                                            <td>
                                                <div class="fw-bold" style="font-size:0.82rem;"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                                <div class="text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($app['email']); ?></div>
                                            </td>
                                            <td class="text-muted" style="font-size:0.78rem;"><?php echo mb_substr($app['major_name'] ?? '--', 0, 20); ?></td>
                                            <td class="text-center small"><?php echo number_format($app['math_score'] ?? 0, 1); ?></td>
                                            <td class="text-center small"><?php echo number_format($app['literature_score'] ?? 0, 1); ?></td>
                                            <td class="text-center small"><?php echo number_format($app['english_score'] ?? 0, 1); ?></td>
                                            <td class="text-center fw-bold text-success"><?php echo number_format($app['total_score'] ?? 0, 2); ?></td>
                                            <td><span class="badge bg-<?php echo $app['status']=='new'?'warning':'info'; ?> small"><?php echo $app['status']=='new'?'Mới':'Đang xét'; ?></span></td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-check2-all fs-3 d-block mb-1 text-success"></i>Không có hồ sơ nào đang chờ xét
                                        </td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Ban Tuyển sinh</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
function toggleAll() {
    const checks = document.querySelectorAll('.app-check');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.app-check').forEach(c => c.checked = this.checked);
});
function confirmBulk(action) {
    const checked = document.querySelectorAll('.app-check:checked').length;
    if (checked === 0) { alert('Vui lòng chọn ít nhất một hồ sơ.'); return false; }
    return confirm(`Xác nhận ${action} ${checked} hồ sơ đã chọn?`);
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
