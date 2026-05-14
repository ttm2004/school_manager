<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Quản lý Đợt Tuyển sinh';
$filter_mode = (($_GET['mode'] ?? 'system') === 'test') ? 'test' : 'system';
$modeLabel = $filter_mode === 'test' ? 'Demo/Test' : 'Dữ liệu thật';

$rs = $conn->prepare("SELECT * FROM admission_rounds WHERE data_mode=? ORDER BY year DESC, id DESC");
$rs->bind_param('s', $filter_mode);
$rs->execute();
$rounds = $rs->get_result();

// Edit view
$editRound = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $es  = $conn->prepare("SELECT * FROM admission_rounds WHERE id=? AND data_mode=?");
    $es->bind_param('is', $eid, $filter_mode); $es->execute();
    $editRound = $es->get_result()->fetch_assoc(); $es->close();
}

$statusLabels = [
    'draft'         => ['secondary', 'Nháp'],
    'open'          => ['success',   'Đang nhận hồ sơ'],
    'reviewing'     => ['danger',    'Đang xét tuyển'],
    'enrolling'     => ['primary',   'Đang nhập học'],
    'supplementary' => ['warning',   'Đợt bổ sung'],
    'completed'     => ['dark',      'Hoàn tất'],
];

include __DIR__ . '/includes/header.php';
$flash = getFlash();
?>
<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show">
    <i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i>
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Form thêm/sửa -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-plus me-2"></i><?php echo $editRound ? 'Chỉnh sửa đợt tuyển sinh' : 'Tạo đợt tuyển sinh mới'; ?> <span class="badge <?php echo $filter_mode === 'test' ? 'bg-warning text-dark' : 'bg-success'; ?> ms-2"><?php echo $modeLabel; ?></span></span>
        <?php if ($editRound): ?>
        <a href="rounds.php?mode=<?php echo urlencode($filter_mode); ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-plus me-1"></i>Tạo mới</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <!-- Dữ liệu edit truyền qua JS -->
        <div id="roundFormData" data-edit='<?php echo $editRound ? htmlspecialchars(json_encode($editRound), ENT_QUOTES) : "null"; ?>'></div>

        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Năm <span class="text-danger">*</span></label>
                <input type="number" id="f_year" class="form-control" value="<?php echo $editRound['year'] ?? date('Y'); ?>" min="2020" max="2099" required>
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold">Tên đợt <span class="text-danger">*</span></label>
                <input type="text" id="f_name" class="form-control" value="<?php echo htmlspecialchars($editRound['name'] ?? 'Tuyển sinh đại học ' . date('Y')); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Trạng thái</label>
                <select id="f_status" class="form-select">
                    <?php foreach ($statusLabels as $val => [$cls, $lbl]): ?>
                    <option value="<?php echo $val; ?>" <?php echo ($editRound['status'] ?? 'draft') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Đợt chính -->
        <div class="p-3 mb-3 rounded" style="background:#f8f9ff;border:1px solid #e2e8f0;">
            <h6 class="fw-bold mb-3 text-navy"><i class="bi bi-1-circle-fill me-2"></i>Đợt xét tuyển chính thức</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Bắt đầu nhận hồ sơ <span class="text-danger">*</span></label>
                    <input type="datetime-local" id="f_reg_start" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['reg_start'])) : ''; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Kết thúc nhận hồ sơ <span class="text-danger">*</span></label>
                    <input type="datetime-local" id="f_reg_end" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['reg_end'])) : ''; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Bắt đầu xét tuyển <span class="text-danger">*</span></label>
                    <input type="datetime-local" id="f_review_start" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['review_start'])) : ''; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Kết thúc xét tuyển <span class="text-danger">*</span></label>
                    <input type="datetime-local" id="f_review_end" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['review_end'])) : ''; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Hạn nhập học <span class="text-danger">*</span></label>
                    <input type="datetime-local" id="f_enroll_deadline" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['enroll_deadline'])) : ''; ?>" required>
                </div>
            </div>
        </div>

        <!-- Đợt bổ sung -->
        <div class="p-3 mb-3 rounded" style="background:#fffbf0;border:1px solid #fde68a;">
            <h6 class="fw-bold mb-1 text-warning"><i class="bi bi-2-circle-fill me-2"></i>Đợt bổ sung <small class="text-muted fw-normal">(để trống nếu không có)</small></h6>
            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Bắt đầu nhận hồ sơ bổ sung</label>
                    <input type="datetime-local" id="f_supp_reg_start" class="form-control" value="<?php echo ($editRound && $editRound['supp_reg_start']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_reg_start'])) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Kết thúc nhận hồ sơ bổ sung</label>
                    <input type="datetime-local" id="f_supp_reg_end" class="form-control" value="<?php echo ($editRound && $editRound['supp_reg_end']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_reg_end'])) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Kết thúc xét bổ sung</label>
                    <input type="datetime-local" id="f_supp_review_end" class="form-control" value="<?php echo ($editRound && $editRound['supp_review_end']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_review_end'])) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Hạn nhập học bổ sung</label>
                    <input type="datetime-local" id="f_supp_enroll_deadline" class="form-control" value="<?php echo ($editRound && $editRound['supp_enroll_deadline']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_enroll_deadline'])) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Điểm chuẩn bổ sung cao hơn</label>
                    <input type="number" id="f_supp_score_bonus" class="form-control" step="0.25" min="0" max="5" value="<?php echo $editRound['supp_score_bonus'] ?? 0; ?>">
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold small">Ghi chú</label>
            <textarea id="f_notes" class="form-control" rows="2"><?php echo htmlspecialchars($editRound['notes'] ?? ''); ?></textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-gold" id="btnSaveRound">
                <i class="bi bi-save me-1"></i><?php echo $editRound ? 'Cập nhật' : 'Tạo đợt tuyển sinh'; ?>
            </button>
            <?php if ($editRound): ?>
            <a href="rounds.php?mode=<?php echo urlencode($filter_mode); ?>" class="btn btn-outline-secondary">Hủy</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Danh sách đợt -->
<div class="card">
    <div class="card-header"><i class="bi bi-list-ul me-2"></i>Danh sách đợt tuyển sinh</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Năm</th><th>Tên đợt</th><th>Nhận hồ sơ</th><th>Xét tuyển</th><th>Hạn nhập học</th><th>Bổ sung</th><th>Trạng thái</th><th>Thao tác</th>
                </tr></thead>
                <tbody>
                <?php if ($rounds && $rounds->num_rows > 0): while ($r = $rounds->fetch_assoc()):
                    [$cls, $lbl] = $statusLabels[$r['status']] ?? ['secondary', $r['status']];
                ?>
                <tr id="round-row-<?php echo $r['id']; ?>">
                    <td class="fw-bold text-navy"><?php echo $r['year']; ?></td>
                    <td>
                        <div class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></div>
                        <?php if ($r['notes']): ?><div class="text-muted small"><?php echo mb_substr($r['notes'],0,50); ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?php echo date('d/m/Y', strtotime($r['reg_start'])); ?><br>→ <?php echo date('d/m/Y', strtotime($r['reg_end'])); ?>
                    </td>
                    <td class="small text-muted">
                        <?php echo date('d/m/Y', strtotime($r['review_start'])); ?><br>→ <?php echo date('d/m/Y', strtotime($r['review_end'])); ?>
                    </td>
                    <td class="small text-muted"><?php echo date('d/m/Y', strtotime($r['enroll_deadline'])); ?></td>
                    <td class="small text-muted">
                        <?php if ($r['supp_reg_start']): ?>
                        <?php echo date('d/m/Y', strtotime($r['supp_reg_start'])); ?> →<br>
                        <?php echo date('d/m/Y', strtotime($r['supp_enroll_deadline'])); ?>
                        <?php if ($r['supp_score_bonus'] > 0): ?>
                        <span class="badge bg-warning text-dark">+<?php echo $r['supp_score_bonus']; ?> điểm</span>
                        <?php endif; ?>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $cls; ?> mb-1"><?php echo $lbl; ?></span><br>
                        <select class="form-select form-select-sm mt-1" style="width:auto;font-size:.72rem;"
                            onchange="changeStatus(<?php echo $r['id']; ?>, this.value, this)">
                            <?php foreach ($statusLabels as $val => [$c, $l]): ?>
                            <option value="<?php echo $val; ?>" <?php echo $r['status']===$val?'selected':''; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="?mode=<?php echo urlencode($filter_mode); ?>&edit=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="bi bi-pencil-fill"></i></a>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRound(<?php echo $r['id']; ?>, this)" title="Xóa"><i class="bi bi-trash-fill"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Chưa có đợt tuyển sinh nào</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content || '';
const editId  = <?php echo $editRound ? (int)$editRound['id'] : 'null'; ?>;
const DATA_MODE = <?php echo json_encode($filter_mode); ?>;

function admFetch(data) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fd.append('module', 'rounds');
    fd.append('data_mode', DATA_MODE);
    Object.entries(data).forEach(([k, v]) => { if (v !== null && v !== undefined) fd.append(k, v); });
    return fetch('/university/admissions/api/actions.php', {
        method: 'POST', body: fd, credentials: 'same-origin'
    }).then(r => r.json());
}

function showToast(type, msg) {
    const el = document.createElement('div');
    el.className = `alert alert-${type==='success'?'success':'danger'} alert-dismissible fade show position-fixed shadow`;
    el.style.cssText = 'top:1rem;right:1rem;z-index:9999;min-width:300px;';
    el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle-fill':'exclamation-circle-fill'} me-2"></i>${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

// Lưu đợt (thêm hoặc sửa)
document.getElementById('btnSaveRound').addEventListener('click', function() {
    const data = {
        action:               editId ? 'edit' : 'add',
        id:                   editId,
        year:                 document.getElementById('f_year').value,
        name:                 document.getElementById('f_name').value.trim(),
        reg_start:            document.getElementById('f_reg_start').value,
        reg_end:              document.getElementById('f_reg_end').value,
        review_start:         document.getElementById('f_review_start').value,
        review_end:           document.getElementById('f_review_end').value,
        enroll_deadline:      document.getElementById('f_enroll_deadline').value,
        supp_reg_start:       document.getElementById('f_supp_reg_start').value || '',
        supp_reg_end:         document.getElementById('f_supp_reg_end').value || '',
        supp_review_end:      document.getElementById('f_supp_review_end').value || '',
        supp_enroll_deadline: document.getElementById('f_supp_enroll_deadline').value || '',
        supp_score_bonus:     document.getElementById('f_supp_score_bonus').value,
        status:               document.getElementById('f_status').value,
        notes:                document.getElementById('f_notes').value,
    };

    if (!data.name || !data.reg_start || !data.reg_end || !data.review_start || !data.review_end || !data.enroll_deadline) {
        showToast('error', 'Vui lòng điền đầy đủ các mốc thời gian bắt buộc.');
        return;
    }

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang lưu...';

    admFetch(data).then(res => {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-save me-1"></i>' + (editId ? 'Cập nhật' : 'Tạo đợt tuyển sinh');
        if (res.success) {
            showToast('success', res.message);
            setTimeout(() => window.location.href = 'rounds.php?mode=' + encodeURIComponent(DATA_MODE), 800);
        } else {
            showToast('error', res.message);
        }
    }).catch(() => {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-save me-1"></i>' + (editId ? 'Cập nhật' : 'Tạo đợt tuyển sinh');
        showToast('error', 'Lỗi kết nối.');
    });
});

// Đổi trạng thái nhanh
function changeStatus(id, status, sel) {
    sel.disabled = true;
    admFetch({ action: 'change_status', id, status })
        .then(res => {
            sel.disabled = false;
            if (res.success) {
                showToast('success', res.message);
                // Cập nhật badge
                const row = document.getElementById('round-row-' + id);
                if (row) {
                    const badge = row.querySelector('.badge');
                    const labels = <?php echo json_encode(array_map(fn($v) => $v[1], $statusLabels), JSON_UNESCAPED_UNICODE); ?>;
                    const classes = <?php echo json_encode(array_map(fn($v) => $v[0], $statusLabels)); ?>;
                    const keys = <?php echo json_encode(array_keys($statusLabels)); ?>;
                    const idx = keys.indexOf(status);
                    if (badge && idx >= 0) {
                        badge.className = `badge bg-${classes[idx]} mb-1`;
                        badge.textContent = labels[idx];
                    }
                }
            } else {
                showToast('error', res.message);
                // Revert select
                sel.value = sel.dataset.prev || sel.value;
            }
        })
        .catch(() => { sel.disabled = false; showToast('error', 'Lỗi kết nối.'); });
}

// Lưu giá trị trước khi đổi để revert nếu lỗi
document.querySelectorAll('select[onchange^="changeStatus"]').forEach(sel => {
    sel.addEventListener('focus', function() { this.dataset.prev = this.value; });
});

// Xóa đợt
function deleteRound(id, btn) {
    if (!confirm('Xóa đợt tuyển sinh này? Thao tác không thể hoàn tác.')) return;
    btn.disabled = true;
    admFetch({ action: 'delete', id })
        .then(res => {
            if (res.success) {
                showToast('success', res.message);
                document.getElementById('round-row-' + id)?.remove();
            } else { btn.disabled = false; showToast('error', res.message); }
        })
        .catch(() => { btn.disabled = false; showToast('error', 'Lỗi kết nối.'); });
}
</script>
