<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Giảng viên';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $teacher_code   = trim($_POST['teacher_code'] ?? '');
        $full_name      = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $faculty_id     = intval($_POST['faculty_id'] ?? 0);
        $degree         = trim($_POST['degree'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $password       = trim($_POST['password'] ?? '123456');

        if ($teacher_code && $full_name) {
            // Tạo username từ teacher_code (viết thường)
            $username = strtolower($teacher_code);

            // Kiểm tra username đã tồn tại chưa
            $chk = $conn->prepare("SELECT id FROM users WHERE username=?");
            $chk->bind_param('s', $username);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($exists) {
                $error = "Tên đăng nhập '$username' đã tồn tại. Vui lòng dùng mã GV khác.";
            } else {
                // Tạo user mới
                $uStmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?,?,?,?,?,'teacher',1)");
                $uStmt->bind_param('sssss', $username, $password, $full_name, $email, $phone);
                if ($uStmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    $uStmt->close();
                    // Tạo giảng viên
                    $tStmt = $conn->prepare("INSERT INTO teachers (user_id, teacher_code, faculty_id, degree, specialization) VALUES (?,?,?,?,?)");
                    $tStmt->bind_param('isiss', $new_user_id, $teacher_code, $faculty_id, $degree, $specialization);
                    $tStmt->execute() ? $success = "Thêm giảng viên thành công! Tài khoản: <strong>$username</strong> / <strong>$password</strong>" : $error = 'Lỗi tạo giảng viên: ' . $conn->error;
                    $tStmt->close();
                } else {
                    $error = 'Lỗi tạo tài khoản: ' . $conn->error;
                    $uStmt->close();
                }
            }
        } else {
            $error = 'Vui lòng nhập đầy đủ Mã GV và Họ tên.';
        }
    }

    if ($action === 'edit') {
        $id             = intval($_POST['id'] ?? 0);
        $teacher_code   = trim($_POST['teacher_code'] ?? '');
        $full_name      = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $faculty_id     = intval($_POST['faculty_id'] ?? 0);
        $degree         = trim($_POST['degree'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $user_id        = intval($_POST['user_id'] ?? 0);
        $new_password   = trim($_POST['new_password'] ?? '');

        if ($id) {
            // Cập nhật teachers
            $stmt = $conn->prepare("UPDATE teachers SET teacher_code=?, faculty_id=?, degree=?, specialization=? WHERE id=?");
            $stmt->bind_param('sissi', $teacher_code, $faculty_id, $degree, $specialization, $id);
            $stmt->execute();
            $stmt->close();

            // Cập nhật users
            if ($user_id) {
                if ($new_password) {
                    $uStmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, password=? WHERE id=?");
                    $uStmt->bind_param('ssssi', $full_name, $email, $phone, $new_password, $user_id);
                } else {
                    $uStmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
                    $uStmt->bind_param('sssi', $full_name, $email, $phone, $user_id);
                }
                $uStmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
                $uStmt->close();
            } else {
                $success = 'Cập nhật thành công!';
            }
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            // Lấy user_id để xóa luôn user
            $row = $conn->query("SELECT user_id FROM teachers WHERE id=$id")->fetch_assoc();
            $stmt = $conn->prepare("DELETE FROM teachers WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            // Xóa user liên quan (nếu có)
            if ($row && $row['user_id']) {
                $conn->query("DELETE FROM users WHERE id=" . intval($row['user_id']));
            }
            $success = 'Xóa giảng viên thành công!';
        }
    }
}

$teachers  = $conn->query("SELECT t.*, u.full_name, u.email, u.phone, u.username, u.id as uid, f.faculty_name FROM teachers t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN faculties f ON t.faculty_id=f.id ORDER BY u.full_name");
$faculties = $conn->query("SELECT * FROM faculties ORDER BY faculty_name");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Giảng viên</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge-fill me-2"></i>Danh sách Giảng viên</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm mới
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>#</th><th>Mã GV</th><th>Họ tên</th><th>Tài khoản</th><th>Khoa</th><th>Học vị</th><th>Chuyên ngành</th><th>Điện thoại</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($teachers && $teachers->num_rows > 0): $idx=1; while ($t = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold text-navy"><?php echo htmlspecialchars($t['teacher_code']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($t['full_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($t['email']); ?></div>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($t['username']); ?></td>
                                <td><?php echo htmlspecialchars($t['faculty_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-navy"><?php echo htmlspecialchars($t['degree']); ?></span></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($t['specialization']); ?></td>
                                <td class="small"><?php echo htmlspecialchars($t['phone']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $t['id']; ?>"
                                        data-uid="<?php echo $t['uid']; ?>"
                                        data-code="<?php echo htmlspecialchars($t['teacher_code']); ?>"
                                        data-fullname="<?php echo htmlspecialchars($t['full_name']); ?>"
                                        data-email="<?php echo htmlspecialchars($t['email']); ?>"
                                        data-phone="<?php echo htmlspecialchars($t['phone']); ?>"
                                        data-faculty="<?php echo $t['faculty_id']; ?>"
                                        data-degree="<?php echo htmlspecialchars($t['degree']); ?>"
                                        data-specialization="<?php echo htmlspecialchars($t['specialization']); ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa giảng viên này? Tài khoản liên quan cũng sẽ bị xóa!')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Thêm Giảng viên mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Hệ thống sẽ tự động tạo tài khoản đăng nhập với username = Mã GV (viết thường).
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã giảng viên <span class="text-danger">*</span></label>
                            <input type="text" name="teacher_code" class="form-control" required placeholder="VD: GV006" style="text-transform:uppercase">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mật khẩu mặc định</label>
                            <input type="text" name="password" class="form-control" value="123456" placeholder="Mật khẩu đăng nhập">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required placeholder="VD: ThS. Nguyễn Văn A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="gv006@gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Điện thoại</label>
                            <input type="text" name="phone" class="form-control" placeholder="0901xxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Khoa</label>
                            <select name="faculty_id" class="form-select">
                                <option value="">-- Chọn khoa --</option>
                                <?php if ($faculties): while ($f = $faculties->fetch_assoc()): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học vị</label>
                            <select name="degree" class="form-select">
                                <option value="">-- Chọn --</option>
                                <option value="Cử nhân">Cử nhân</option>
                                <option value="Thạc sĩ">Thạc sĩ</option>
                                <option value="Tiến sĩ">Tiến sĩ</option>
                                <option value="PGS.TS">PGS.TS</option>
                                <option value="GS.TS">GS.TS</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Chuyên ngành</label>
                            <input type="text" name="specialization" class="form-control" placeholder="VD: Công nghệ phần mềm">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Giảng viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="user_id" id="editUid">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã giảng viên</label>
                            <input type="text" name="teacher_code" id="editCode" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mật khẩu mới <small class="text-muted">(để trống = không đổi)</small></label>
                            <input type="text" name="new_password" class="form-control" placeholder="Nhập mật khẩu mới...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Họ và tên</label>
                            <input type="text" name="full_name" id="editFullname" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Điện thoại</label>
                            <input type="text" name="phone" id="editPhone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Khoa</label>
                            <select name="faculty_id" id="editFaculty" class="form-select">
                                <option value="">-- Chọn khoa --</option>
                                <?php
                                $faculties2 = $conn->query("SELECT * FROM faculties ORDER BY faculty_name");
                                if ($faculties2): while ($f = $faculties2->fetch_assoc()): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học vị</label>
                            <select name="degree" id="editDegree" class="form-select">
                                <option value="">-- Chọn --</option>
                                <option value="Cử nhân">Cử nhân</option>
                                <option value="Thạc sĩ">Thạc sĩ</option>
                                <option value="Tiến sĩ">Tiến sĩ</option>
                                <option value="PGS.TS">PGS.TS</option>
                                <option value="GS.TS">GS.TS</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Chuyên ngành</label>
                            <input type="text" name="specialization" id="editSpecialization" class="form-control">
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
    document.getElementById('editId').value             = btn.dataset.id;
    document.getElementById('editUid').value            = btn.dataset.uid;
    document.getElementById('editCode').value           = btn.dataset.code;
    document.getElementById('editFullname').value       = btn.dataset.fullname;
    document.getElementById('editEmail').value          = btn.dataset.email;
    document.getElementById('editPhone').value          = btn.dataset.phone;
    document.getElementById('editFaculty').value        = btn.dataset.faculty;
    document.getElementById('editDegree').value         = btn.dataset.degree;
    document.getElementById('editSpecialization').value = btn.dataset.specialization;
});
</script>
</body></html>
