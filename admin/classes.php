<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quan ly Lop hoc';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $class_code  = trim($_POST['class_code'] ?? '');
        $class_name  = trim($_POST['class_name'] ?? '');
        $major_id    = intval($_POST['major_id'] ?? 0);
        $school_year = trim($_POST['school_year'] ?? '');
        if ($class_code && $class_name && $major_id) {
            $stmt = $conn->prepare("INSERT INTO classes (class_code, class_name, major_id, school_year) VALUES (?,?,?,?)");
            $stmt->bind_param('ssis', $class_code, $class_name, $major_id, $school_year);
            $stmt->execute() ? $success = 'Them lop thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui long dien day du thong tin.'; }
    }
    if ($action === 'edit') {
        $id          = intval($_POST['id'] ?? 0);
        $class_code  = trim($_POST['class_code'] ?? '');
        $class_name  = trim($_POST['class_name'] ?? '');
        $major_id    = intval($_POST['major_id'] ?? 0);
        $school_year = trim($_POST['school_year'] ?? '');
        if ($id && $class_name) {
            $stmt = $conn->prepare("UPDATE classes SET class_code=?, class_name=?, major_id=?, school_year=? WHERE id=?");
            $stmt->bind_param('ssisi', $class_code, $class_name, $major_id, $school_year, $id);
            $stmt->execute() ? $success = 'Cap nhat thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        }
    }
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM classes WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xoa thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
        }
    }
}

// Xem sinh vien theo lop
$viewClass = null;
$classStudents = null;
if (isset($_GET['view_class'])) {
    $vcid = intval($_GET['view_class']);
    $vs = $conn->prepare("SELECT c.*, m.major_name, f.faculty_name FROM classes c LEFT JOIN majors m ON c.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id WHERE c.id=?");
    $vs->bind_param('i', $vcid);
    $vs->execute();
    $viewClass = $vs->get_result()->fetch_assoc();
    $vs->close();

    $classStudents = $conn->prepare("
        SELECT s.*, u.full_name, u.email, u.phone
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ?
        ORDER BY u.full_name ASC
    ");
    $classStudents->bind_param('i', $vcid);
    $classStudents->execute();
    $classStudents = $classStudents->get_result();
}

$classes = $conn->query("SELECT c.*, m.major_name, f.faculty_name, COUNT(s.id) as student_count FROM classes c LEFT JOIN majors m ON c.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id LEFT JOIN students s ON c.id=s.class_id GROUP BY c.id ORDER BY c.class_name");
$majors  = $conn->query("SELECT m.*, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id=f.id ORDER BY f.faculty_name, m.major_name");

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quan ly Lop hoc</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <?php if ($viewClass): ?>
        <!-- Xem sinh vien cua lop -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-people-fill me-2"></i>
                    Danh sach sinh vien lop: <strong><?php echo htmlspecialchars($viewClass['class_name']); ?></strong>
                    <span class="badge bg-navy ms-2"><?php echo htmlspecialchars($viewClass['class_code']); ?></span>
                </span>
                <a href="classes.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Quay lai</a>
            </div>
            <div class="card-body pb-2 pt-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <div class="text-muted small">Nganh</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($viewClass['major_name'] ?? '--'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <div class="text-muted small">Khoa</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($viewClass['faculty_name'] ?? '--'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3">
                            <div class="text-muted small">Nam hoc</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($viewClass['school_year'] ?? '--'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>MSSV</th>
                                <th>Ho ten</th>
                                <th>Gioi tinh</th>
                                <th>Ngay sinh</th>
                                <th>Email</th>
                                <th>Dien thoai</th>
                                <th>Tinh trang</th>
                                <th>Thao tac</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($classStudents && $classStudents->num_rows > 0): $idx=1; while ($sv = $classStudents->fetch_assoc()): ?>
                            <tr>
                                <td class="text-muted"><?php echo $idx++; ?></td>
                                <td><span class="badge bg-navy"><?php echo htmlspecialchars($sv['student_code']); ?></span></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($sv['full_name']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($sv['gender'] ?? '--'); ?></td>
                                <td class="text-muted small"><?php echo !empty($sv['birthday']) ? date('d/m/Y', strtotime($sv['birthday'])) : '--'; ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($sv['email'] ?? '--'); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($sv['phone'] ?? '--'); ?></td>
                                <td>
                                    <?php
                                    $statusMap = ['Dang hoc'=>'success','Bao luu'=>'warning','Thoi hoc'=>'danger','Da tot nghiep'=>'info'];
                                    $st = $sv['academic_status'] ?? 'Dang hoc';
                                    $stColor = $statusMap[$st] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $stColor; ?>"><?php echo htmlspecialchars($st); ?></span>
                                </td>
                                <td>
                                    <a href="students.php?view=<?php echo $sv['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-people fs-3 d-block mb-2"></i>Lop nay chua co sinh vien nao</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Danh sach lop -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-collection-fill me-2"></i>Danh sach Lop hoc</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Them moi</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Ma lop</th><th>Ten lop</th><th>Nganh</th><th>Khoa</th><th>Nam hoc</th><th>So SV</th><th>Thao tac</th></tr></thead>
                        <tbody>
                            <?php if ($classes && $classes->num_rows > 0): $idx=1; while ($c = $classes->fetch_assoc()): ?>
                            <tr class="<?php echo (isset($_GET['view_class']) && $_GET['view_class']==$c['id']) ? 'table-primary' : ''; ?>">
                                <td><?php echo $idx++; ?></td>
                                <td><span class="badge bg-navy"><?php echo htmlspecialchars($c['class_code']); ?></span></td>
                                <td>
                                    <a href="?view_class=<?php echo $c['id']; ?>" class="fw-bold text-navy text-decoration-none">
                                        <?php echo htmlspecialchars($c['class_name']); ?>
                                        <i class="bi bi-people-fill ms-1 text-muted" style="font-size:.75rem;"></i>
                                    </a>
                                </td>
                                <td class="fw-bold small"><?php echo htmlspecialchars($c['major_name'] ?? 'N/A'); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($c['faculty_name'] ?? 'N/A'); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($c['school_year']); ?></td>
                                <td>
                                    <a href="?view_class=<?php echo $c['id']; ?>" class="badge bg-<?php echo $c['student_count']>0?'success':'secondary'; ?> text-decoration-none">
                                        <i class="bi bi-people-fill me-1"></i><?php echo $c['student_count']; ?> SV
                                    </a>
                                </td>
                                <td>
                                    <a href="?view_class=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="Xem sinh vien">
                                        <i class="bi bi-people-fill"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $c['id']; ?>"
                                        data-class_code="<?php echo htmlspecialchars($c['class_code']); ?>"
                                        data-class_name="<?php echo htmlspecialchars($c['class_name']); ?>"
                                        data-major="<?php echo $c['major_id']; ?>"
                                        data-year="<?php echo htmlspecialchars($c['school_year']); ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xoa lop nay?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
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
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-collection me-2"></i>Them Lop hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Ma lop <span class="text-danger">*</span></label><input type="text" name="class_code" class="form-control" required placeholder="VD: D22CNTT01"></div>
                    <div class="mb-3"><label class="form-label">Ten lop <span class="text-danger">*</span></label><input type="text" name="class_name" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Nganh <span class="text-danger">*</span></label>
                        <select name="major_id" class="form-select" required>
                            <option value="">-- Chon nganh --</option>
                            <?php if ($majors): while ($m = $majors->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?> (<?php echo htmlspecialchars($m['faculty_name']); ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Nam hoc</label><input type="text" name="school_year" class="form-control" placeholder="VD: 2022-2026"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Luu</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chinh sua Lop hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Ma lop</label><input type="text" name="class_code" id="editClassCode" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Ten lop <span class="text-danger">*</span></label><input type="text" name="class_name" id="editClassName" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Nganh</label>
                        <select name="major_id" id="editMajor" class="form-select">
                            <option value="">-- Chon nganh --</option>
                            <?php
                            $majors2 = $conn->query("SELECT m.*, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id=f.id ORDER BY f.faculty_name, m.major_name");
                            if ($majors2): while ($m = $majors2->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?> (<?php echo htmlspecialchars($m['faculty_name']); ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Nam hoc</label><input type="text" name="school_year" id="editYear" class="form-control"></div>
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
    document.getElementById('editId').value        = btn.dataset.id;
    document.getElementById('editClassCode').value = btn.dataset.class_code;
    document.getElementById('editClassName').value = btn.dataset.class_name;
    document.getElementById('editMajor').value     = btn.dataset.major;
    document.getElementById('editYear').value      = btn.dataset.year;
});
</script>
</body></html>
