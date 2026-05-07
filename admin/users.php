<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý người dùng';

$success = $error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $status = intval($_POST['status'] ?? 1);
        if ($username && $password && $full_name) {
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('sssssi', $username, $password, $full_name, $email, $role, $status);
            $stmt->execute() ? $success = 'Thêm người dùng thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.'; }
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $status = intval($_POST['status'] ?? 1);
        $password = trim($_POST['password'] ?? '');
        if ($id && $full_name) {
            if ($password) {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=?, status=?, password=? WHERE id=?");
                $stmt->bind_param('sssisi', $full_name, $email, $role, $status, $password, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=?, status=? WHERE id=?");
                $stmt->bind_param('sssii', $full_name, $email, $role, $status, $id);
            }
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id && $id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa người dùng thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Không thể xóa tài khoản đang đăng nhập.'; }
    }
}

// Search
$search = trim($_GET['search'] ?? '');
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

if ($search) {
    $like = "%$search%";
    $countStmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?");
    $countStmt->bind_param('sss', $like, $like, $like);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('sssii', $like, $like, $like, $perPage, $offset);
} else {
    $total = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();
$totalPages = ceil($total / $perPage);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý người dùng</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </div>
    <div class="admin-content">
        <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-2"></i>Danh sách người dùng (<?php echo $total; ?>)</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm mới
                </button>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="input-group" style="max-width:400px;">
                        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-navy" type="submit"><i class="bi bi-search"></i></button>
                        <?php if ($search): ?><a href="users.php" class="btn btn-outline-secondary">Xóa</a><?php endif; ?>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover" id="dataTable">
                        <thead>
                            <tr><th>#</th><th>Tên đăng nhập</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($users && $users->num_rows > 0): $idx = $offset+1; while ($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <?php
                                    $roleMap = ['admin'=>['Quản trị','danger'],'student'=>['Sinh viên','primary'],'teacher'=>['Giảng viên','success']];
                                    $r = $roleMap[$u['role']] ?? [$u['role'],'secondary'];
                                    ?>
                                    <span class="badge bg-<?php echo $r[1]; ?>"><?php echo $r[0]; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $u['status']==1?'success':'secondary'; ?>">
                                        <?php echo $u['status']==1?'Hoạt động':'Khóa'; ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                        data-fullname="<?php echo htmlspecialchars($u['full_name']); ?>"
                                        data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                        data-role="<?php echo $u['role']; ?>"
                                        data-status="<?php echo $u['status']; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa người dùng này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Không có dữ liệu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Thêm người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="text" name="password" class="form-control" required value="123456">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vai trò</label>
                            <select name="role" class="form-select">
                                <option value="student">Sinh viên</option>
                                <option value="teacher">Giảng viên</option>
                                <option value="admin">Quản trị</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="1">Hoạt động</option>
                                <option value="0">Khóa</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" id="editUsername" class="form-control" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                            <input type="text" name="password" class="form-control" placeholder="Nhập mật khẩu mới...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="editFullname" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vai trò</label>
                            <select name="role" id="editRole" class="form-select">
                                <option value="student">Sinh viên</option>
                                <option value="teacher">Giảng viên</option>
                                <option value="admin">Quản trị</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="1">Hoạt động</option>
                                <option value="0">Khóa</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cập nhật</button>
                </div>
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
    document.getElementById('editUsername').value = btn.dataset.username;
    document.getElementById('editFullname').value = btn.dataset.fullname;
    document.getElementById('editEmail').value = btn.dataset.email;
    document.getElementById('editRole').value = btn.dataset.role;
    document.getElementById('editStatus').value = btn.dataset.status;
});
</script>
</body></html>
