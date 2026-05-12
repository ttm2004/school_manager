<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Tin Tuyển sinh';

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$total   = (int)($conn->query("SELECT COUNT(*) as c FROM admission_news")->fetch_assoc()['c'] ?? 0);
$stmt    = $conn->prepare("SELECT * FROM admission_news ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$news        = $stmt->get_result();
$stmt->close();
$totalPages  = (int)ceil($total / $perPage);

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
        <span><i class="bi bi-newspaper me-2"></i>Danh sách Tin Tuyển sinh
            <span class="badge bg-gold text-navy ms-1"><?php echo $total; ?></span>
        </span>
        <button class="btn btn-gold btn-sm" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-1"></i>Thêm mới
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="newsTable">
                <thead>
                    <tr><th>#</th><th>Tiêu đề</th><th>Thời gian</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    <?php if ($news && $news->num_rows > 0): $idx=$offset+1; while ($n = $news->fetch_assoc()): ?>
                    <tr id="news-row-<?php echo $n['id']; ?>">
                        <td><?php echo $idx++; ?></td>
                        <td>
                            <?php if ($n['image']): ?>
                            <img src="<?php echo htmlspecialchars($n['image']); ?>" alt="" class="rounded me-2" style="width:50px;height:35px;object-fit:cover">
                            <?php endif; ?>
                            <span class="fw-bold"><?php echo htmlspecialchars($n['title']); ?></span>
                        </td>
                        <td class="text-muted small">
                            <?php if ($n['start_date']): ?>
                            <?php echo date('d/m/Y', strtotime($n['start_date'])); ?> — <?php echo date('d/m/Y', strtotime($n['end_date'])); ?>
                            <?php else: ?>--<?php endif; ?>
                        </td>
                        <td>
                            <button class="badge border-0 bg-<?php echo $n['status']=='show'?'success':'secondary'; ?> text-white"
                                style="cursor:pointer;"
                                onclick="toggleNews(<?php echo $n['id']; ?>, this)">
                                <?php echo $n['status']=='show'?'Hiển thị':'Ẩn'; ?>
                            </button>
                        </td>
                        <td class="text-muted small"><?php echo date('d/m/Y', strtotime($n['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($n), ENT_QUOTES); ?>)">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="deleteNews(<?php echo $n['id']; ?>, this)">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có tin tuyển sinh</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav class="p-3"><ul class="pagination justify-content-center mb-0">
            <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
            <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
            <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal thêm -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-newspaper me-2"></i>Thêm Tin Tuyển sinh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-semibold">Tiêu đề <span class="text-danger">*</span></label><input type="text" id="add_title" class="form-control" required></div>
                <div class="mb-3"><label class="form-label fw-semibold">Nội dung <span class="text-danger">*</span></label><textarea id="add_content" class="form-control" rows="5" required></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold">URL Hình ảnh</label><input type="text" id="add_image" class="form-control" placeholder="https://..."></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label fw-semibold">Ngày bắt đầu</label><input type="date" id="add_start_date" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Ngày kết thúc</label><input type="date" id="add_end_date" class="form-control"></div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Trạng thái</label>
                        <select id="add_status" class="form-select"><option value="show">Hiển thị</option><option value="hide">Ẩn</option></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-gold" id="btnAdd"><i class="bi bi-save me-1"></i>Lưu</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal sửa -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Tin Tuyển sinh</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                <div class="mb-3"><label class="form-label fw-semibold">Tiêu đề <span class="text-danger">*</span></label><input type="text" id="edit_title" class="form-control" required></div>
                <div class="mb-3"><label class="form-label fw-semibold">Nội dung <span class="text-danger">*</span></label><textarea id="edit_content" class="form-control" rows="5" required></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold">URL Hình ảnh</label><input type="text" id="edit_image" class="form-control"></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label fw-semibold">Ngày bắt đầu</label><input type="date" id="edit_start_date" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Ngày kết thúc</label><input type="date" id="edit_end_date" class="form-control"></div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Trạng thái</label>
                        <select id="edit_status" class="form-select"><option value="show">Hiển thị</option><option value="hide">Ẩn</option></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-gold" id="btnEdit"><i class="bi bi-save me-1"></i>Cập nhật</button>
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
    fd.append('module', 'news');
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
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

function openAddModal() {
    ['add_title','add_content','add_image','add_start_date','add_end_date'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('add_status').value = 'show';
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function openEditModal(n) {
    document.getElementById('edit_id').value = n.id;
    document.getElementById('edit_title').value = n.title;
    document.getElementById('edit_content').value = n.content;
    document.getElementById('edit_image').value = n.image || '';
    document.getElementById('edit_start_date').value = n.start_date || '';
    document.getElementById('edit_end_date').value = n.end_date || '';
    document.getElementById('edit_status').value = n.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Thêm
document.getElementById('btnAdd').addEventListener('click', function() {
    const title   = document.getElementById('add_title').value.trim();
    const content = document.getElementById('add_content').value.trim();
    if (!title || !content) { showToast('error', 'Vui lòng nhập tiêu đề và nội dung.'); return; }
    this.disabled = true;
    admFetch({
        action: 'add', title, content,
        image:      document.getElementById('add_image').value,
        start_date: document.getElementById('add_start_date').value,
        end_date:   document.getElementById('add_end_date').value,
        status:     document.getElementById('add_status').value,
    }).then(res => {
        this.disabled = false;
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('addModal'))?.hide();
            showToast('success', res.message);
            setTimeout(() => location.reload(), 800);
        } else { showToast('error', res.message); }
    }).catch(() => { this.disabled = false; showToast('error', 'Lỗi kết nối.'); });
});

// Sửa
document.getElementById('btnEdit').addEventListener('click', function() {
    const title   = document.getElementById('edit_title').value.trim();
    const content = document.getElementById('edit_content').value.trim();
    if (!title || !content) { showToast('error', 'Vui lòng nhập tiêu đề và nội dung.'); return; }
    this.disabled = true;
    admFetch({
        action: 'edit',
        id:         document.getElementById('edit_id').value,
        title, content,
        image:      document.getElementById('edit_image').value,
        start_date: document.getElementById('edit_start_date').value,
        end_date:   document.getElementById('edit_end_date').value,
        status:     document.getElementById('edit_status').value,
    }).then(res => {
        this.disabled = false;
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
            showToast('success', res.message);
            setTimeout(() => location.reload(), 800);
        } else { showToast('error', res.message); }
    }).catch(() => { this.disabled = false; showToast('error', 'Lỗi kết nối.'); });
});

// Toggle hiển thị/ẩn
function toggleNews(id, btn) {
    admFetch({ action: 'toggle', id })
        .then(res => {
            if (res.success) {
                const isShow = res.data?.status === 'show';
                btn.className = `badge border-0 bg-${isShow?'success':'secondary'} text-white`;
                btn.style.cursor = 'pointer';
                btn.textContent = isShow ? 'Hiển thị' : 'Ẩn';
                showToast('success', res.message);
            } else { showToast('error', res.message); }
        })
        .catch(() => showToast('error', 'Lỗi kết nối.'));
}

// Xóa
function deleteNews(id, btn) {
    if (!confirm('Xóa tin này? Thao tác không thể hoàn tác.')) return;
    btn.disabled = true;
    admFetch({ action: 'delete', id })
        .then(res => {
            if (res.success) {
                showToast('success', res.message);
                document.getElementById('news-row-' + id)?.remove();
            } else { btn.disabled = false; showToast('error', res.message); }
        })
        .catch(() => { btn.disabled = false; showToast('error', 'Lỗi kết nối.'); });
}
</script>
