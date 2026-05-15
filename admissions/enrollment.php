<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);

// ── AJAX: chỉ trả về partial table ──────────────────────────
if (isset($_GET['ajax'])) {
    $isManager   = hasRole('admissions_manager');
    $canEnroll   = hasPermission('admissions', 'manage_enrollment');
    $filter_mode  = trim($_GET['mode'] ?? 'system');
    if (!in_array($filter_mode, ['system','test'], true)) $filter_mode = 'system';
    $roundPhase  = getRoundPhase($filter_mode);
    $enrollAllowed = in_array($roundPhase, ['enrolling', 'supp_enrolling']);
    $enrollLocked  = !$enrollAllowed;

    $tab          = $_GET['tab'] ?? 'approved';
    $filter_major = intval($_GET['major_id'] ?? 0);
    $filter_search= trim($_GET['q'] ?? '');
    $statusFilter = $tab === 'enrolled' ? 'enrolled' : 'approved';

    $where  = ["aa.status='$statusFilter'"];
    $params = []; $types = '';
    if ($filter_mode !== 'all') { $where[] = 'aa.data_mode=?'; $params[] = $filter_mode; $types .= 's'; }
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

    if ($params) { $cs = $conn->prepare("SELECT COUNT(*) c FROM admission_applications aa $wSQL"); $cs->bind_param($types, ...$params); $cs->execute(); $total = (int)$cs->get_result()->fetch_assoc()['c']; $cs->close(); }
    else { $total = (int)$conn->query("SELECT COUNT(*) c FROM admission_applications aa $wSQL")->fetch_assoc()['c']; }
    $totalPages = ceil($total / $perPage);

    $dSQL = "SELECT aa.*, m.major_name, m.major_code, am.method_name, ar.year AS admission_year,
        (aa.math_score+aa.literature_score+aa.english_score) as total_score,
        (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email AND s.data_mode=aa.data_mode LIMIT 1) as has_account
        FROM admission_applications aa
        LEFT JOIN majors m ON aa.major_id=m.id
        LEFT JOIN admission_methods am ON aa.method_id=am.id
        LEFT JOIN admission_rounds ar ON ar.id=aa.round_id
        $wSQL ORDER BY aa.created_at DESC LIMIT ? OFFSET ?";
    $allP = array_merge($params, [$perPage, $offset]); $allT = $types . 'ii';
    $stmt = $conn->prepare($dSQL); $stmt->bind_param($allT, ...$allP); $stmt->execute();
    $applications = $stmt->get_result(); $stmt->close();

    include __DIR__ . '/enrollment_table.php';
    exit();
}

$pageTitle = 'Thủ tục Nhập học';

$isManager   = hasRole('admissions_manager');
$canEnroll   = hasPermission('admissions', 'manage_enrollment');
$filter_mode  = trim($_GET['mode'] ?? 'system');
if (!in_array($filter_mode, ['system','test'], true)) $filter_mode = 'system';
$roundPhase  = getRoundPhase($filter_mode);
$activeRound = getActiveRound($filter_mode);
$roundMsg    = getRoundStatusMessage($filter_mode);
$enrollAllowed = in_array($roundPhase, ['enrolling', 'supp_enrolling']);
$enrollLocked  = !$enrollAllowed;

// Counts
$modeCountSql = $filter_mode === 'all' ? '1=1' : "data_mode='" . $conn->real_escape_string($filter_mode) . "'";
$approvedCount = (int)($conn->query("SELECT COUNT(*) c FROM admission_applications WHERE status='approved' AND $modeCountSql")->fetch_assoc()['c'] ?? 0);
$enrolledCount = (int)($conn->query("SELECT COUNT(*) c FROM admission_applications WHERE status='enrolled' AND $modeCountSql")->fetch_assoc()['c'] ?? 0);

// Filter params
$tab          = $_GET['tab'] ?? 'approved';
$filter_major = intval($_GET['major_id'] ?? 0);
$filter_search= trim($_GET['q'] ?? '');
$statusFilter = $tab === 'enrolled' ? 'enrolled' : 'approved';

$where  = ["aa.status='$statusFilter'"];
$params = []; $types = '';
if ($filter_mode !== 'all') { $where[] = 'aa.data_mode=?'; $params[] = $filter_mode; $types .= 's'; }
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

$dSQL = "SELECT aa.*, m.major_name, m.major_code, am.method_name, ar.year AS admission_year,
    (aa.math_score+aa.literature_score+aa.english_score) as total_score,
    (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email AND s.data_mode=aa.data_mode LIMIT 1) as has_account
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id=m.id
    LEFT JOIN admission_methods am ON aa.method_id=am.id
    LEFT JOIN admission_rounds ar ON ar.id=aa.round_id
    $wSQL ORDER BY aa.created_at DESC LIMIT ? OFFSET ?";
$allP = array_merge($params, [$perPage, $offset]); $allT = $types . 'ii';
$stmt = $conn->prepare($dSQL); $stmt->bind_param($allT, ...$allP); $stmt->execute();
$applications = $stmt->get_result(); $stmt->close();

$majors  = $conn->query('SELECT id, major_name FROM majors ORDER BY major_name');
$classes = $conn->query('SELECT c.id, c.class_name, c.class_code, c.school_year, c.enrollment_year, m.major_name, m.id as major_id FROM classes c LEFT JOIN majors m ON c.major_id=m.id ORDER BY c.enrollment_year DESC, c.class_name');
$classArr = [];
if ($classes) while ($cl = $classes->fetch_assoc()) $classArr[] = $cl;

include __DIR__ . '/includes/header.php';
?>

<!-- ── Modal trạng thái ──────────────────────────────────── -->
<?php
$_mIcon  = $enrollLocked ? 'bi-lock-fill' : 'bi-unlock-fill';
$_mColor = $enrollLocked ? '#dc2626' : '#059669';
$_mBg    = $enrollLocked ? 'rgba(239,68,68,.08)' : 'rgba(16,185,129,.08)';
$_mTitle = $enrollLocked ? 'Trang chỉ xem' : 'Giai đoạn nhập học';
$lockMap = [
    'no_round'       => 'Không có đợt tuyển sinh nào đang hoạt động.',
    'before_reg'     => 'Chưa đến thời gian nhận hồ sơ.',
    'reg_open'       => 'Đang nhận hồ sơ — chưa đến giai đoạn nhập học.',
    'reviewing'      => 'Đang xét tuyển — chưa có kết quả chính thức.',
    'results'        => 'Đã công bố kết quả — thí sinh có thể tra cứu đậu/rớt, nhưng chưa mở thủ tục nhập học.',
    'supp_reviewing' => 'Đang xét tuyển bổ sung.',
    'after_enroll'   => 'Đã hết hạn nhập học chính thức.',
    'completed'      => 'Đợt tuyển sinh đã hoàn tất.',
];
$_mBody = $enrollLocked
    ? ($lockMap[$roundPhase] ?? 'Không trong giai đoạn nhập học.') . ' Các tác vụ chỉ khả dụng khi đợt tuyển sinh ở trạng thái <strong>Đang nhập học</strong>.'
    : ($roundPhase === 'supp_enrolling' ? 'Đang trong giai đoạn nhập học <strong>bổ sung</strong>.' : 'Đang trong giai đoạn nhập học chính thức.')
      . ($activeRound ? ' Hạn: <strong>' . date('d/m/Y H:i', strtotime($roundPhase === 'supp_enrolling' ? ($activeRound['supp_enroll_deadline'] ?? '') : ($activeRound['enroll_deadline'] ?? ''))) . '</strong>' : '');
if (!$isManager) $_mBody .= '<hr class="my-2"><small class="text-muted"><i class="bi bi-info-circle me-1"></i>Quyền của bạn: Xác nhận nhập học, cấp tài khoản sinh viên.</small>';
?>
<div class="modal fade" id="statusModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0" style="background:<?php echo $_mBg; ?>">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:42px;height:42px;border-radius:50%;background:<?php echo $enrollLocked?'rgba(239,68,68,.15)':'rgba(16,185,129,.15)';?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="bi <?php echo $_mIcon; ?>" style="color:<?php echo $_mColor; ?>;"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" style="color:<?php echo $_mColor; ?>;"><?php echo $_mTitle; ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="statusModalClose"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0" style="font-size:.9rem;line-height:1.6;"><?php echo $_mBody; ?></p>
                <?php if ($enrollLocked): ?><a href="rounds.php" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-calendar-range me-1"></i>Quản lý đợt tuyển sinh</a><?php endif; ?>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:4px;border-radius:4px;">
                        <div id="smProgress" class="progress-bar" style="width:100%;background:<?php echo $_mColor; ?>;transition:width linear;"></div>
                    </div>
                    <small class="text-muted" id="smCountdown" style="font-size:.72rem;white-space:nowrap;"></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
    const dur = <?php echo $enrollLocked ? 8000 : 5000; ?>;
    const bar = document.getElementById('smProgress');
    const cd  = document.getElementById('smCountdown');
    let rem = Math.ceil(dur/1000); cd.textContent = rem+'s';
    const t = setInterval(()=>{ rem--; cd.textContent=rem+'s'; if(rem<=0){clearInterval(t);modal.hide();} }, 1000);
    requestAnimationFrame(()=>requestAnimationFrame(()=>{ bar.style.transitionDuration=dur+'ms'; bar.style.width='0%'; }));
    document.getElementById('statusModalClose').addEventListener('click',()=>clearInterval(t));
});
</script>

<!-- ── Stats ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #059669">
            <div class="ic" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-check-circle-fill"></i></div>
            <div class="vl" id="statApproved"><?php echo $approvedCount; ?></div>
            <div class="lb">Đã duyệt — chờ nhập học</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #0d2d6b">
            <div class="ic" style="background:rgba(13,45,107,.1);color:#0d2d6b"><i class="bi bi-person-check-fill"></i></div>
            <div class="vl" id="statEnrolled"><?php echo $enrolledCount; ?></div>
            <div class="lb">Đã nhập học</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #f5a623">
            <div class="ic" style="background:rgba(245,166,35,.15);color:#d4891a"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="vl" id="statRate"><?php echo ($approvedCount+$enrolledCount)>0 ? round($enrolledCount/($approvedCount+$enrolledCount)*100) : 0; ?>%</div>
            <div class="lb">Tỷ lệ nhập học</div>
        </div>
    </div>
</div>

<!-- ── Tabs ──────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header p-0">
        <ul class="nav nav-tabs border-0" id="enrollTabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $tab!=='enrolled'?'active':''; ?> rounded-0 border-0 px-4 py-3 tab-link" href="#" data-tab="approved">
                    <i class="bi bi-clock-history me-1"></i>Chờ nhập học
                    <span class="badge bg-success ms-1" id="badgeApproved"><?php echo $approvedCount; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab==='enrolled'?'active':''; ?> rounded-0 border-0 px-4 py-3 tab-link" href="#" data-tab="enrolled">
                    <i class="bi bi-person-check me-1"></i>Đã nhập học
                    <span class="badge bg-navy ms-1" id="badgeEnrolled"><?php echo $enrolledCount; ?></span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Filter -->
    <div class="card-body border-bottom py-2">
        <div class="d-flex gap-2 flex-wrap align-items-end">
            <select id="filterMode" class="form-select form-select-sm" style="width:160px">
                <option value="system" <?php echo $filter_mode==='system'?'selected':''; ?>>Dữ liệu thật</option>
                <option value="test" <?php echo $filter_mode==='test'?'selected':''; ?>>Test / Demo</option>
                            </select>
            <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="Tìm tên, email, CCCD..." value="<?php echo htmlspecialchars($filter_search); ?>" style="width:220px">
            <select id="filterMajor" class="form-select form-select-sm" style="width:200px">
                <option value="">Tất cả ngành</option>
                <?php if ($majors): while ($mj = $majors->fetch_assoc()): ?>
                <option value="<?php echo $mj['id']; ?>" <?php echo $filter_major==$mj['id']?'selected':''; ?>><?php echo htmlspecialchars($mj['major_name']); ?></option>
                <?php endwhile; endif; ?>
            </select>
            <button class="btn btn-sm btn-navy" id="btnFilter"><i class="bi bi-search me-1"></i>Lọc</button>
            <button class="btn btn-sm btn-outline-secondary" id="btnClearFilter"><i class="bi bi-x me-1"></i>Xóa lọc</button>
        </div>
    </div>

    <!-- Table -->
    <div class="card-body p-0" id="tableWrapper">
        <?php include __DIR__ . '/enrollment_table.php'; ?>
    </div>
</div>

<!-- ── Modal Cấp Tài Khoản ──────────────────────────────── -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Cấp tài khoản Sinh viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3"><i class="bi bi-person me-1"></i>Sinh viên: <strong id="modalStudentName"></strong></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Chọn lớp <span class="text-danger">*</span></label>
                    <select id="modalClassSelect" class="form-select" required>
                        <option value="">-- Chọn lớp --</option>
                    </select>
                    <div class="form-text">Chỉ hiển thị lớp cùng ngành với sinh viên.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Chế độ đăng ký HK1 tự động</label>
                    <div class="d-flex flex-column gap-2">
                        <label class="form-check border rounded p-2 mb-0">
                            <input class="form-check-input" type="radio" name="modalAutoEnrollMode" value="system" checked>
                            <span class="form-check-label fw-semibold">Theo hệ thống thật</span>
                            <div class="small text-muted">Tìm HK1 đúng khóa/năm học và lớp HP đang mở trong học kỳ đó.</div>
                        </label>
                        <label class="form-check border rounded p-2 mb-0">
                            <input class="form-check-input" type="radio" name="modalAutoEnrollMode" value="test">
                            <span class="form-check-label fw-semibold">Theo học kỳ Test</span>
                            <div class="small text-muted">Ưu tiên học kỳ có tên Test cùng năm học để mô phỏng nhanh.</div>
                        </label>
                    </div>
                </div>
                <div class="alert alert-warning py-2 small">
                    <i class="bi bi-key me-1"></i><strong>Mã SV tự động:</strong> Năm + Mã ngành + 3 số ngẫu nhiên<br>
                    <i class="bi bi-lock me-1"></i><strong>Mật khẩu mặc định</strong> = Mã sinh viên.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-gold" id="btnCreateAccount">
                    <i class="bi bi-save me-1"></i>Cấp tài khoản
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Thông tin tài khoản mới ────────────────────── -->
<div class="modal fade" id="newAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-bottom:none;">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-person-check-fill me-2"></i>Cấp tài khoản thành công!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="newAccountInfo"></div>
                <div class="alert alert-info py-2 small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>Thông báo cho sinh viên. Mật khẩu = Mã sinh viên, có thể đổi sau khi đăng nhập.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
// ── Dữ liệu từ PHP ──────────────────────────────────────────
const allClasses   = <?php echo json_encode($classArr, JSON_UNESCAPED_UNICODE); ?>;
const enrollLocked = <?php echo $enrollLocked ? 'true' : 'false'; ?>;
const canEnroll    = <?php echo $canEnroll ? 'true' : 'false'; ?>;
const isManager    = <?php echo $isManager ? 'true' : 'false'; ?>;

// ── State ────────────────────────────────────────────────────
let currentTab    = '<?php echo $tab; ?>';
let currentMajor  = <?php echo $filter_major; ?>;
let currentSearch = <?php echo json_encode($filter_search); ?>;
let currentMode   = <?php echo json_encode($filter_mode); ?>;
let currentPage   = <?php echo $page; ?>;
let pendingAppId  = null;
let pendingMajorId= null;
let pendingTargetYear = 0;

// ── Helpers ──────────────────────────────────────────────────
function toast(type, msg) {
    if (window.tdmuToast) { window.tdmuToast[type]?.(msg) || window.tdmuToast.info(msg); }
    else { alert(msg); }
}

function updateStats(approved, enrolled) {
    const rate = (approved + enrolled) > 0 ? Math.round(enrolled / (approved + enrolled) * 100) : 0;
    document.getElementById('statApproved').textContent = approved;
    document.getElementById('statEnrolled').textContent = enrolled;
    document.getElementById('statRate').textContent     = rate + '%';
    document.getElementById('badgeApproved').textContent = approved;
    document.getElementById('badgeEnrolled').textContent = enrolled;
}

// ── Load bảng qua AJAX ───────────────────────────────────────
function loadTable(tab, major, search, page, mode = currentMode) {
    currentTab = tab; currentMajor = major; currentSearch = search; currentPage = page; currentMode = mode;

    // Cập nhật tab active
    document.querySelectorAll('.tab-link').forEach(a => {
        a.classList.toggle('active', a.dataset.tab === tab);
    });

    const wrapper = document.getElementById('tableWrapper');
    wrapper.style.opacity = '0.5';

    const params = new URLSearchParams({ tab, major_id: major, q: search, page, mode, ajax: 1 });
    fetch('enrollment.php?' + params.toString(), { credentials: 'same-origin' })
        .then(r => r.text())
        .then(html => {
            wrapper.innerHTML = html;
            wrapper.style.opacity = '1';
            bindTableEvents();
        })
        .catch(() => { wrapper.style.opacity = '1'; toast('error', 'Lỗi tải dữ liệu.'); });
}

// ── Bind events cho các nút trong bảng ──────────────────────
function bindTableEvents() {
    const bulkChecks = Array.from(document.querySelectorAll('.bulk-enroll-check'));
    const bulkAll = document.getElementById('bulkSelectAll');
    const bulkBtn = document.getElementById('btnBulkEnroll');
    const bulkCount = document.getElementById('bulkSelectedCount');
    const refreshBulkState = () => {
        const selected = bulkChecks.filter(cb => cb.checked);
        if (bulkCount) bulkCount.textContent = selected.length;
        if (bulkBtn) bulkBtn.disabled = selected.length === 0;
        if (bulkAll) {
            bulkAll.checked = bulkChecks.length > 0 && selected.length === bulkChecks.length;
            bulkAll.indeterminate = selected.length > 0 && selected.length < bulkChecks.length;
        }
    };
    bulkAll?.addEventListener('change', function() {
        bulkChecks.forEach(cb => cb.checked = this.checked);
        refreshBulkState();
    });
    bulkChecks.forEach(cb => cb.addEventListener('change', refreshBulkState));
    bulkBtn?.addEventListener('click', function() {
        const ids = bulkChecks.filter(cb => cb.checked).map(cb => cb.value);
        if (!ids.length) return;
        tdmuConfirm('Xác nhận nhập học ' + ids.length + ' hồ sơ test đã chọn?').then(ok => {
            if (!ok) return;
            doBulkEnroll(ids);
        });
    });

    // Nút Nhập học
    document.querySelectorAll('.btn-enroll').forEach(btn => {
        btn.addEventListener('click', function() {
            const id   = this.dataset.id;
            const name = this.dataset.name;
            tdmuConfirm('Xác nhận nhập học cho ' + name + '?').then(ok => {
                if (!ok) return;
                doEnroll(id, this.closest('tr'));
            });
        });
    });

    // Nút Hủy nhập học
    document.querySelectorAll('.btn-cancel-enroll').forEach(btn => {
        btn.addEventListener('click', function() {
            const id   = this.dataset.id;
            const name = this.dataset.name;
            tdmuConfirm('Hủy nhập học cho ' + name + '? Tài khoản sinh viên sẽ không bị xóa.', {type:'danger'}).then(ok => {
                if (!ok) return;
                doCancelEnroll(id, this.closest('tr'));
            });
        });
    });

    // Nút Cấp TK
    document.querySelectorAll('.btn-create-account').forEach(btn => {
        btn.addEventListener('click', function() {
            pendingAppId   = this.dataset.id;
            pendingMajorId = parseInt(this.dataset.majorId);
            pendingTargetYear = parseInt(this.dataset.targetYear || '0');
            document.getElementById('modalStudentName').textContent = this.dataset.name;
            const autoMode = currentMode === 'test' ? 'test' : 'system';
            const radio = document.querySelector(`input[name="modalAutoEnrollMode"][value="${autoMode}"]`);
            if (radio) radio.checked = true;
            buildClassSelect(pendingMajorId, pendingTargetYear);
            new bootstrap.Modal(document.getElementById('accountModal')).show();
        });
    });

    // Pagination
    document.querySelectorAll('.page-ajax').forEach(a => {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            loadTable(currentTab, currentMajor, currentSearch, parseInt(this.dataset.page));
        });
    });
}

// ── AJAX: Nhập học ───────────────────────────────────────────
function doBulkEnroll(ids) {
    const fd = new FormData();
    fd.append('action', 'bulk_enroll');
    fd.append('ids', JSON.stringify(ids));
    fd.append('data_mode', currentMode);

    fetch('enrollment_api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                const affected = parseInt(res.affected || ids.length);
                toast('success', res.msg);
                const approved = Math.max(0, parseInt(document.getElementById('statApproved').textContent) - affected);
                const enrolled = parseInt(document.getElementById('statEnrolled').textContent) + affected;
                updateStats(approved, enrolled);
                loadTable(currentTab, currentMajor, currentSearch, currentPage, currentMode);
            } else {
                toast('error', res.error);
            }
        })
        .catch(() => toast('error', 'Lỗi kết nối.'));
}

function doEnroll(id, row) {
    const fd = new FormData();
    fd.append('action', 'enroll');
    fd.append('id', id);
    fd.append('data_mode', currentMode);
    fetch('enrollment_api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                toast('success', res.msg);
                // Xóa dòng khỏi tab approved, cập nhật stats
                if (row) row.remove();
                const approved = parseInt(document.getElementById('statApproved').textContent) - 1;
                const enrolled = parseInt(document.getElementById('statEnrolled').textContent) + 1;
                updateStats(approved, enrolled);
            } else {
                toast('error', res.error);
            }
        })
        .catch(() => toast('error', 'Lỗi kết nối.'));
}

// ── AJAX: Hủy nhập học ──────────────────────────────────────
function doCancelEnroll(id, row) {
    const fd = new FormData();
    fd.append('action', 'cancel_enroll');
    fd.append('id', id);
    fd.append('data_mode', currentMode);
    fetch('enrollment_api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                toast('success', res.msg);
                if (row) row.remove();
                const approved = parseInt(document.getElementById('statApproved').textContent) + 1;
                const enrolled = parseInt(document.getElementById('statEnrolled').textContent) - 1;
                updateStats(approved, enrolled);
            } else {
                toast('error', res.error);
            }
        })
        .catch(() => toast('error', 'Lỗi kết nối.'));
}

// ── Build class select ───────────────────────────────────────
function buildClassSelect(majorId, targetYear = 0) {
    const sel = document.getElementById('modalClassSelect');
    sel.innerHTML = '<option value="">-- Ch?n l?p --</option>';
    let filtered = allClasses.filter(c => {
        const okMajor = !majorId || parseInt(c.major_id) === majorId;
        const okYear = !targetYear || parseInt(c.enrollment_year || 0) === targetYear;
        return okMajor && okYear;
    });
    if (!filtered.length) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = targetYear
            ? 'Chưa có lớp đúng ngành cho khóa ' + targetYear
            : 'Chưa có lớp phù hợp';
        sel.appendChild(opt);
        sel.disabled = true;
        return;
    }
    sel.disabled = false;
    filtered.forEach(cl => {
        const opt = document.createElement('option');
        opt.value = cl.id;
        opt.textContent = cl.class_name
            + (cl.enrollment_year ? ' - kh?a ' + cl.enrollment_year : '')
            + (cl.school_year ? ' (' + cl.school_year + ')' : '')
            + (filtered.length === allClasses.length ? ' ? ' + (cl.major_name || '') : '');
        sel.appendChild(opt);
    });
}

// ── AJAX: Cấp tài khoản ─────────────────────────────────────
document.getElementById('btnCreateAccount').addEventListener('click', function() {
    const classId = document.getElementById('modalClassSelect').value;
    if (!classId) { toast('warning', 'Vui lòng chọn lớp học.'); return; }

    const fd = new FormData();
    fd.append('action', 'create_account');
    fd.append('app_id', pendingAppId);
    fd.append('class_id', classId);
    fd.append('data_mode', currentMode);
    fd.append('auto_enroll_mode', document.querySelector('input[name="modalAutoEnrollMode"]:checked')?.value || 'system');

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';

    fetch('enrollment_api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save me-1"></i>Cấp tài khoản';
            bootstrap.Modal.getInstance(document.getElementById('accountModal'))?.hide();

            if (res.ok) {
                // Hiện modal thông tin tài khoản
                const acc = res.account;
                document.getElementById('newAccountInfo').innerHTML = `
                    <div class="col-6"><div class="text-muted small">Họ tên</div><div class="fw-bold">${acc.full_name}</div></div>
                    <div class="col-6"><div class="text-muted small">Mã sinh viên</div><div class="fw-bold text-navy">${acc.student_code}</div></div>
                    <div class="col-6"><div class="text-muted small">Tên đăng nhập</div><div class="fw-bold">${acc.username}</div></div>
                    <div class="col-6"><div class="text-muted small">Mật khẩu</div><div class="fw-bold text-danger">${acc.password}</div></div>
                `;
                new bootstrap.Modal(document.getElementById('newAccountModal')).show();
                // Cập nhật badge "Đã cấp" trong bảng
                const btn = document.querySelector(`.btn-create-account[data-id="${pendingAppId}"]`);
                if (btn) {
                    btn.outerHTML = '<span class="btn btn-sm btn-outline-success disabled"><i class="bi bi-check2 me-1"></i>Đã cấp TK</span>';
                }
            } else {
                toast('error', res.error);
            }
        })
        .catch(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save me-1"></i>Cấp tài khoản';
            toast('error', 'Lỗi kết nối.');
        });
});

// ── Tab switch ───────────────────────────────────────────────
document.querySelectorAll('.tab-link').forEach(a => {
    a.addEventListener('click', function(e) {
        e.preventDefault();
        loadTable(this.dataset.tab, currentMajor, currentSearch, 1);
    });
});

// ── Filter ───────────────────────────────────────────────────
document.getElementById('btnFilter').addEventListener('click', function() {
    loadTable(currentTab, document.getElementById('filterMajor').value, document.getElementById('filterSearch').value, 1, document.getElementById('filterMode').value);
});
document.getElementById('btnClearFilter').addEventListener('click', function() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterMajor').value  = '';
    document.getElementById('filterMode').value = 'system';
    loadTable(currentTab, '', '', 1, 'system');
});
document.getElementById('filterSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') document.getElementById('btnFilter').click();
});

// ── Init ─────────────────────────────────────────────────────
bindTableEvents();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
