<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Sinh viên';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $student_code = trim($_POST['student_code'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        if ($user_id && $student_code) {
            $stmt = $conn->prepare("INSERT INTO students (user_id, student_code, class_id, address, birthday, gender) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('isisss', $user_id, $student_code, $class_id, $address, $birthday, $gender);
            $stmt->execute() ? $success = 'Thêm sinh viên thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng điền đầy đủ thông tin.'; }
    }
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $student_code = trim($_POST['student_code'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        if ($id) {
            $stmt = $conn->prepare("UPDATE students SET student_code=?, class_id=?, address=?, birthday=?, gender=? WHERE id=?");
            $stmt->bind_param('sisssi', $student_code, $class_id, $address, $birthday, $gender, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM students WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }
}

$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$baseQuery = "FROM students s LEFT JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN majors m ON c.major_id=m.id";
if ($search) {
    $like = "%$search%";
    $countStmt = $conn->prepare("SELECT COUNT(*) as c $baseQuery WHERE u.full_name LIKE ? OR s.student_code LIKE ?");
    $countStmt->bind_param('ss', $like, $like);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();
    $stmt = $conn->prepare("SELECT s.*, u.full_name, u.email, u.username, c.class_name, m.major_name $baseQuery WHERE u.full_name LIKE ? OR s.student_code LIKE ? ORDER BY s.id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ssii', $like, $like, $perPage, $offset);
} else {
    $total = $conn->query("SELECT COUNT(*) as c $baseQuery")->fetch_assoc()['c'];
    $stmt = $conn->prepare("SELECT s.*, u.full_name, u.email, u.username, c.class_name, m.major_name $baseQuery ORDER BY s.id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$students = $stmt->get_result();
$stmt->close();
$totalPages = ceil($total / $perPage);

$classes = $conn->query("SELECT c.*, m.major_name FROM classes c LEFT JOIN majors m ON c.major_id=m.id ORDER BY c.class_name");
$users = $conn->query("SELECT u.* FROM users u WHERE u.role='student' ORDER BY u.full_name");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Sinh viên</span>
        </div>
    </div>
    <div class="admin-content">
        <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-fill me-2"></i>Danh sách Sinh viên (<?php echo $total; ?>)</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Thêm mới</button>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="input-group" style="max-width:400px;">
                        <input type="text" name="search" class="form-control" placeholder="Tìm theo tên, mã SV..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-navy" type="submit"><i class="bi bi-search"></i></button>
                        <?php if ($search): ?><a href="students.php" class="btn btn-outline-secondary">Xóa</a><?php endif; ?>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Mã SV</th><th>Họ tên</th><th>Lớp</th><th>Ngành</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            <?php if ($students && $students->num_rows > 0): $idx=$offset+1; while ($s = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold text-navy"><?php echo htmlspecialchars($s['student_code']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($s['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($s['class_name'] ?? 'N/A'); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($s['major_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $s['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($s['student_code']); ?>"
                                        data-class="<?php echo $s['class_id']; ?>"
                                        data-address="<?php echo htmlspecialchars($s['address']); ?>"
                                        data-birthday="<?php echo $s['birthday']; ?>"
                                        data-gender="<?php echo $s['gender']; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa sinh viên này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav><ul class="pagination justify-content-center mt-3">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Thêm Sinh viên</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tài khoản người dùng <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-select" required>
                                <option value="">-- Chọn tài khoản --</option>
                                <?php if ($users): while ($u = $users->fetch_assoc()): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($u['username']); ?>)</option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Mã sinh viên <span class="text-danger">*</span></label><input type="text" name="student_code" class="form-control" required placeholder="VD: SV2024001"></div>
                        <div class="col-md-6">
                            <label class="form-label">Lớp</label>
                            <select name="class_id" class="form-select">
                                <option value="">-- Chọn lớp --</option>
                                <?php if ($classes): while ($c = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?> (<?php echo htmlspecialchars($c['major_name']); ?>)</option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Giới tính</label><select name="gender" class="form-select"><option value="">-- Chọn --</option><option value="male">Nam</option><option value="female">Nữ</option><option value="other">Khác</option></select></div>
                        <div class="col-md-6"><label class="form-label">Ngày sinh</label><input type="date" name="birthday" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Địa chỉ</label><input type="text" name="address" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Sinh viên</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Mã sinh viên</label><input type="text" name="student_code" id="editCode" class="form-control"></div>
                        <div class="col-md-6">
                            <label class="form-label">Lớp</label>
                            <select name="class_id" id="editClass" class="form-select">
                                <option value="">-- Chọn lớp --</option>
                                <?php
                                $classes2 = $conn->query("SELECT c.*, m.major_name FROM classes c LEFT JOIN majors m ON c.major_id=m.id ORDER BY c.class_name");
                                if ($classes2): while ($c = $classes2->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?> (<?php echo htmlspecialchars($c['major_name']); ?>)</option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Giới tính</label><select name="gender" id="editGender" class="form-select"><option value="">-- Chọn --</option><option value="male">Nam</option><option value="female">Nữ</option><option value="other">Khác</option></select></div>
                        <div class="col-md-6"><label class="form-label">Ngày sinh</label><input type="date" name="birthday" id="editBirthday" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Địa chỉ</label><input type="text" name="address" id="editAddress" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cập nhật</button></div>
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
    document.getElementById('editCode').value = btn.dataset.code;
    document.getElementById('editClass').value = btn.dataset.class;
    document.getElementById('editAddress').value = btn.dataset.address;
    document.getElementById('editBirthday').value = btn.dataset.birthday;
    document.getElementById('editGender').value = btn.dataset.gender;
});
</script>
</body></html>
