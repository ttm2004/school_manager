<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Phương thức Xét tuyển';

$methods = $conn->query("SELECT am.*, COUNT(aa.id) as app_count FROM admission_methods am LEFT JOIN admission_applications aa ON am.id=aa.method_id GROUP BY am.id ORDER BY am.id");

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

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-2"></i>Danh sách Phương thức Xét tuyển</span>
        <button class="btn btn-gold btn-sm" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-1"></i>Thêm mới
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="methodsTable">
                <thead>
                    <tr><th>#</th><th>Tên phương thức</th><th>Điều kiện</th><th>Hồ sơ</th><th>Trạng thái</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    <?php if ($methods && $methods->num_rows > 0): $idx=1; while ($m = $methods->fetch_assoc()): ?>
                    <tr id="method-row-<?php echo $m['id']; ?>">
                        <td><?php echo $idx++; ?></td>
                        <td>
                            <div class="fw-bold text-navy"><?php echo htmlspecialchars($m['method_name']); ?></div>
                            <?php if ($m['description']): ?><div class="text-muted small"><?php echo mb_substr($m['description'],0,80); ?>...</div><?php endif; ?>
                        </td>
                        <td class="text-muted small"><?php echo mb_substr($m['condition_text'] ?? '',0,100); ?></td>
                        <td><span class="badge bg-navy"><?php echo $m['app_count']; ?> hồ sơ</span></td>
                        <td><span class="badge bg-<?php echo $m['status']=='open'?'success':'secondary'; ?>"><?php echo $m['status']=='open'?'Đang mở':'Đóng'; ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>)">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="deleteMethod(<?php echo $m['id']; ?>, this)">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal thêm -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-list-check me-2"></i>Thêm Phương thức Xét tuyển</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tên phương thức <span class="text-danger">*</span></label>
                    <input type="text" id="add_method_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mô tả</label>
                    <textarea id="add_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Điều kiện xét tuyển</label>
                    <textarea id="add_condition" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select id="add_status" class="form-select">
                        <option value="open">Đang mở</option>
                        <option value="closed">Đóng</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-gold" id="btnAdd">
                    <i class="bi bi-save me-1"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal sửa -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Phương thức</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tên phương thức <span class="text-danger">*</span></label>
                    <input type="text" id="edit_method_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mô tả</label>
                    <textarea id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Điều kiện xét tuyển</label>
                    <textarea id="edit_condition" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select id="edit_status" class="form-select">
                        <option value="open">Đang mở</option>
                        <option value="closed">Đóng</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-gold" id="btnEdit">
                    <i class="bi bi-save me-1"></i>Cập nhật
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function admFetch(data) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fd.append('module', 'methods');
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    return fetch('/university/admissions/api/actions.php', {
        method: 'POST', body: fd, credentials: 'same-origin'
    }).then(r => r.json());
}

function showToast(type, msg) {
    const el = document.createElement('div');
    el.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed shadow`;
    el.style.cssText = 'top:1rem;right:1rem;z-index:9999;min-width:300px;';
    el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle-fill':'exclamation-circle-fill'} me-2"></i>${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

function openAddModal() {
    document.getElementById('add_method_name').value = '';
    document.getElementById('add_description').value = '';
    document.getElementById('add_condition').value = '';
    document.getElementById('add_status').value = 'open';
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function openEditModal(m) {
    document.getElementById('edit_id').value = m.id;
    document.getElementById('edit_method_name').value = m.method_name;
    document.getElementById('edit_description').value = m.description || '';
    document.getElementById('edit_condition').value = m.condition_text || '';
    document.getElementById('edit_status').value = m.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Thêm
document.getElementById('btnAdd').addEventListener('click', function() {
    const name = document.getElementById('add_method_name').value.trim();
    if (!name) { showToast('error', 'Vui lòng nhập tên phương thức.'); return; }
    this.disabled = true;
    admFetch({
        action: 'add',
        method_name: name,
        description: document.getElementById('add_description').value,
        condition_text: document.getElementById('add_condition').value,
        status: document.getElementById('add_status').value,
    }).then(res => {
        this.disabled = false;
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('addModal'))?.hide();
            showToast('success', res.message);
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('error', res.message);
        }
    }).catch(() => { this.disabled = false; showToast('error', 'Lỗi kết nối.'); });
});

// Sửa
document.getElementById('btnEdit').addEventListener('click', function() {
    const name = document.getElementById('edit_method_name').value.trim();
    if (!name) { showToast('error', 'Vui lòng nhập tên phương thức.'); return; }
    this.disabled = true;
    admFetch({
        action: 'edit',
        id: document.getElementById('edit_id').value,
        method_name: name,
        description: document.getElementById('edit_description').value,
        condition_text: document.getElementById('edit_condition').value,
        status: document.getElementById('edit_status').value,
    }).then(res => {
        this.disabled = false;
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
            showToast('success', res.message);
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('error', res.message);
        }
    }).catch(() => { this.disabled = false; showToast('error', 'Lỗi kết nối.'); });
});

// Xóa
function deleteMethod(id, btn) {
    if (!confirm('Xóa phương thức này? Thao tác không thể hoàn tác.')) return;
    btn.disabled = true;
    admFetch({ action: 'delete', id })
        .then(res => {
            if (res.success) {
                showToast('success', res.message);
                document.getElementById('method-row-' + id)?.remove();
            } else {
                btn.disabled = false;
                showToast('error', res.message);
            }
        })
        .catch(() => { btn.disabled = false; showToast('error', 'Lỗi kết nối.'); });
}
</script>
