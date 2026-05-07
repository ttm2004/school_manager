<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quan ly Nganh hoc';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $major_code    = trim($_POST['major_code'] ?? '');
        $major_name    = trim($_POST['major_name'] ?? '');
        $faculty_id    = intval($_POST['faculty_id'] ?? 0);
        $total_credits = intval($_POST['total_credits'] ?? 120);
        $tuition_per_credit = floatval($_POST['tuition_per_credit'] ?? 450000);
        $description   = trim($_POST['description'] ?? '');
        $status        = trim($_POST['status'] ?? 'open');
        if ($major_code && $major_name && $faculty_id) {
            $stmt = $conn->prepare("INSERT INTO majors (faculty_id, major_code, major_name, total_credits, tuition_per_credit, description, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('issidss', $faculty_id, $major_code, $major_name, $total_credits, $tuition_per_credit, $description, $status);
            $stmt->execute() ? $success = 'Them nganh thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui long dien day du thong tin.'; }
    }
    if ($action === 'edit') {
        $id            = intval($_POST['id'] ?? 0);
        $major_code    = trim($_POST['major_code'] ?? '');
        $major_name    = trim($_POST['major_name'] ?? '');
        $faculty_id    = intval($_POST['faculty_id'] ?? 0);
        $total_credits = intval($_POST['total_credits'] ?? 120);
        $tuition_per_credit = floatval($_POST['tuition_per_credit'] ?? 450000);
        $description   = trim($_POST['description'] ?? '');
        $status        = trim($_POST['status'] ?? 'open');
        if ($id && $major_name) {
            $stmt = $conn->prepare("UPDATE majors SET faculty_id=?, major_code=?, major_name=?, total_credits=?, tuition_per_credit=?, description=?, status=? WHERE id=?");
            $stmt->bind_param('issidssi', $faculty_id, $major_code, $major_name, $total_credits, $tuition_per_credit, $description, $status, $id);
            $stmt->execute() ? $success = 'Cap nhat thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        }
    }
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM majors WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xoa thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        }
    }
}

$majors = $conn->query("SELECT m.*, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id=f.id ORDER BY f.faculty_name, m.major_name");
$faculties = $conn->query("SELECT * FROM faculties ORDER BY faculty_name");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quan ly Nganh hoc</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-book-fill me-2"></i>Danh sach Nganh hoc</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Them moi</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Ma nganh</th><th>Ten nganh</th><th>Khoa</th><th>Tin chi</th><th>Hoc phi/TC</th><th>Trang thai</th><th>Thao tac</th></tr></thead>
                        <tbody>
                            <?php if ($majors && $majors->num_rows > 0): $idx=1; while ($m = $majors->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td><span class="badge bg-navy"><?php echo htmlspecialchars($m['major_code']); ?></span></td>
                                <td>
                                    <a href="/university/admin/curriculum.php?major_id=<?php echo $m['id']; ?>" class="fw-bold text-navy text-decoration-none">
                                        <?php echo htmlspecialchars($m['major_name']); ?>
                                        <i class="bi bi-box-arrow-up-right ms-1" style="font-size:0.7rem;opacity:0.6;"></i>
                                    </a>
                                    <?php if ($m['description']): ?><div class="text-muted small"><?php echo mb_substr($m['description'],0,60); ?></div><?php endif; ?>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($m['faculty_name'] ?? 'N/A'); ?></td>
                                <td class="text-center"><?php echo $m['total_credits']; ?></td>
                                <td class="text-success small"><?php echo number_format($m['tuition_per_credit'],0,',','.'); ?>d</td>
                                <td><span class="badge bg-<?php echo $m['status']=='open'?'success':'secondary'; ?>"><?php echo $m['status']=='open'?'Dang tuyen':'Dong'; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $m['id']; ?>"
                                        data-major_code="<?php echo htmlspecialchars($m['major_code']); ?>"
                                        data-major_name="<?php echo htmlspecialchars($m['major_name']); ?>"
                                        data-faculty_id="<?php echo $m['faculty_id']; ?>"
                                        data-total_credits="<?php echo $m['total_credits']; ?>"
                                        data-tuition_per_credit="<?php echo $m['tuition_per_credit']; ?>"
                                        data-description="<?php echo htmlspecialchars($m['description'] ?? ''); ?>"
                                        data-status="<?php echo $m['status']; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xoa nganh nay?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Chua co du lieu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-book me-2"></i>Them Nganh hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Ma nganh <span class="text-danger">*</span></label><input type="text" name="major_code" class="form-control" required placeholder="VD: 7480201"></div>
                        <div class="col-md-8"><label class="form-label">Ten nganh <span class="text-danger">*</span></label><input type="text" name="major_name" class="form-control" required></div>
                        <div class="col-md-6">
                            <label class="form-label">Khoa <span class="text-danger">*</span></label>
                            <select name="faculty_id" class="form-select" required>
                                <option value="">-- Chon khoa --</option>
                                <?php $faculties->data_seek(0); while ($f = $faculties->fetch_assoc()): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Tong tin chi</label><input type="number" name="total_credits" class="form-control" value="120" min="0"></div>
                        <div class="col-md-3"><label class="form-label">Hoc phi/TC (d)</label><input type="number" name="tuition_per_credit" class="form-control" value="450000" min="0"></div>
                        <div class="col-12"><label class="form-label">Mo ta</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                        <div class="col-md-4">
                            <label class="form-label">Trang thai</label>
                            <select name="status" class="form-select">
                                <option value="open">Dang tuyen</option>
                                <option value="closed">Dong</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Luu</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chinh sua Nganh hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Ma nganh</label><input type="text" name="major_code" id="editMajorCode" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label">Ten nganh <span class="text-danger">*</span></label><input type="text" name="major_name" id="editMajorName" class="form-control" required></div>
                        <div class="col-md-6">
                            <label class="form-label">Khoa</label>
                            <select name="faculty_id" id="editFacultyId" class="form-select">
                                <option value="">-- Chon khoa --</option>
                                <?php
                                $faculties3 = $conn->query("SELECT * FROM faculties ORDER BY faculty_name");
                                while ($f = $faculties3->fetch_assoc()): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Tong tin chi</label><input type="number" name="total_credits" id="editTotalCredits" class="form-control" min="0"></div>
                        <div class="col-md-3"><label class="form-label">Hoc phi/TC</label><input type="number" name="tuition_per_credit" id="editTuitionPerCredit" class="form-control" min="0"></div>
                        <div class="col-12"><label class="form-label">Mo ta</label><textarea name="description" id="editDescription" class="form-control" rows="2"></textarea></div>
                        <div class="col-md-4">
                            <label class="form-label">Trang thai</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="open">Dang tuyen</option>
                                <option value="closed">Dong</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cap nhat</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value = btn.dataset.id;
    document.getElementById('editMajorCode').value = btn.dataset.major_code;
    document.getElementById('editMajorName').value = btn.dataset.major_name;
    document.getElementById('editFacultyId').value = btn.dataset.faculty_id;
    document.getElementById('editTotalCredits').value = btn.dataset.total_credits;
    document.getElementById('editTuitionPerCredit').value = btn.dataset.tuition_per_credit;
    document.getElementById('editDescription').value = btn.dataset.description;
    document.getElementById('editStatus').value = btn.dataset.status;
});
</script>
</body></html>
