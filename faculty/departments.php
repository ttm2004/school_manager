<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Bộ môn';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isFacultyManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
        header('Location: departments.php');
        exit();
    }

    $action = trim($_POST['action'] ?? '');

    // ── ADD ──────────────────────────────────────────────────
    if ($action === 'add') {
        $deptCode = trim($_POST['department_code'] ?? '');
        $deptName = trim($_POST['department_name'] ?? '');
        $headId   = (int)($_POST['head_teacher_id'] ?? 0);
        $desc     = trim($_POST['description'] ?? '');

        if ($deptCode === '' || $deptName === '') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Mã bộ môn và tên bộ môn không được để trống.'];
            header('Location: departments.php');
            exit();
        }

        // Kiểm tra unique code per faculty
        $stmtCheck = $conn->prepare("SELECT id FROM departments WHERE faculty_id = ? AND department_code = ? AND deleted_at IS NULL LIMIT 1");
        $stmtCheck->bind_param('is', $facultyId, $deptCode);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            $stmtCheck->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Mã bộ môn đã tồn tại trong khoa này.'];
            header('Location: departments.php');
            exit();
        }
        $stmtCheck->close();

        // Validate head_teacher thuộc faculty
        $headIdParam = null;
        if ($headId > 0) {
            $stmtHead = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND faculty_id = ? LIMIT 1");
            $stmtHead->bind_param('ii', $headId, $facultyId);
            $stmtHead->execute();
            if ($stmtHead->get_result()->num_rows === 0) {
                $stmtHead->close();
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Trưởng bộ môn không hợp lệ.'];
                header('Location: departments.php');
                exit();
            }
            $stmtHead->close();
            $headIdParam = $headId;
        }

        $stmtIns = $conn->prepare("INSERT INTO departments (faculty_id, department_code, department_name, head_teacher_id, description) VALUES (?, ?, ?, ?, ?)");
        $stmtIns->bind_param('issss', $facultyId, $deptCode, $deptName, $headIdParam, $desc);
        $stmtIns->execute();
        $newId = (int)$conn->insert_id;
        $stmtIns->close();

        logAudit($conn, $userId, 'create', 'faculty', 'departments', $newId, null,
            json_encode(['department_code' => $deptCode, 'department_name' => $deptName]), $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Thêm bộ môn thành công.'];
        header('Location: departments.php');
        exit();
    }

    // ── EDIT ─────────────────────────────────────────────────
    if ($action === 'edit') {
        $deptId   = (int)($_POST['dept_id'] ?? 0);
        $deptName = trim($_POST['department_name'] ?? '');
        $headId   = (int)($_POST['head_teacher_id'] ?? 0);
        $desc     = trim($_POST['description'] ?? '');

        if ($deptId <= 0 || $deptName === '') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: departments.php');
            exit();
        }
        if (!assertFacultyOwnership($conn, 'departments', $deptId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền chỉnh sửa bộ môn thuộc khoa khác.'];
            header('Location: departments.php');
            exit();
        }

        // Validate head_teacher
        $headIdParam = null;
        if ($headId > 0) {
            $stmtHead = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND faculty_id = ? LIMIT 1");
            $stmtHead->bind_param('ii', $headId, $facultyId);
            $stmtHead->execute();
            if ($stmtHead->get_result()->num_rows === 0) {
                $stmtHead->close();
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Trưởng bộ môn không hợp lệ.'];
                header('Location: departments.php');
                exit();
            }
            $stmtHead->close();
            $headIdParam = $headId;
        }

        // Lấy dữ liệu cũ
        $stmtOld = $conn->prepare("SELECT department_name, head_teacher_id, description FROM departments WHERE id = ? LIMIT 1");
        $stmtOld->bind_param('i', $deptId);
        $stmtOld->execute();
        $oldRow = $stmtOld->get_result()->fetch_assoc();
        $stmtOld->close();

        $stmtUpd = $conn->prepare("UPDATE departments SET department_name = ?, head_teacher_id = ?, description = ? WHERE id = ? AND faculty_id = ?");
        $stmtUpd->bind_param('siisi', $deptName, $headIdParam, $desc, $deptId, $facultyId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'update', 'faculty', 'departments', $deptId,
            json_encode($oldRow),
            json_encode(['department_name' => $deptName, 'head_teacher_id' => $headIdParam, 'description' => $desc]),
            $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật bộ môn thành công.'];
        header('Location: departments.php');
        exit();
    }

    // ── SOFT DELETE ───────────────────────────────────────────
    if ($action === 'soft_delete') {
        $deptId = (int)($_POST['dept_id'] ?? 0);
        if ($deptId <= 0 || !assertFacultyOwnership($conn, 'departments', $deptId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền xóa bộ môn này.'];
            header('Location: departments.php');
            exit();
        }

        // Soft delete department
        $stmtDel = $conn->prepare("UPDATE departments SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND faculty_id = ?");
        $stmtDel->bind_param('iii', $userId, $deptId, $facultyId);
        $stmtDel->execute();
        $stmtDel->close();

        // Bỏ phân bộ môn cho GV thuộc bộ môn này (KHÔNG xóa GV)
        $stmtUnassign = $conn->prepare("UPDATE teachers SET department_id = NULL WHERE department_id = ? AND faculty_id = ?");
        $stmtUnassign->bind_param('ii', $deptId, $facultyId);
        $stmtUnassign->execute();
        $stmtUnassign->close();

        logAudit($conn, $userId, 'delete', 'faculty', 'departments', $deptId, null, null, $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã xóa bộ môn (có thể khôi phục).'];
        header('Location: departments.php');
        exit();
    }

    // ── RESTORE ───────────────────────────────────────────────
    if ($action === 'restore') {
        $deptId = (int)($_POST['dept_id'] ?? 0);
        if ($deptId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: departments.php?archived=1');
            exit();
        }

        // Kiểm tra ownership (kể cả deleted)
        $stmtChk = $conn->prepare("SELECT id FROM departments WHERE id = ? AND faculty_id = ? LIMIT 1");
        $stmtChk->bind_param('ii', $deptId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền khôi phục bộ môn này.'];
            header('Location: departments.php?archived=1');
            exit();
        }
        $stmtChk->close();

        $stmtRes = $conn->prepare("UPDATE departments SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND faculty_id = ?");
        $stmtRes->bind_param('ii', $deptId, $facultyId);
        $stmtRes->execute();
        $stmtRes->close();

        logAudit($conn, $userId, 'restore', 'faculty', 'departments', $deptId, null, null, $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã khôi phục bộ môn.'];
        header('Location: departments.php');
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hành động không hợp lệ.'];
    header('Location: departments.php');
    exit();
}

// ── GET Handler ───────────────────────────────────────────────
$flash      = getFlash();
$showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';

// Lấy danh sách bộ môn
if ($showArchived) {
    $stmtDepts = $conn->prepare(
        "SELECT d.*, u.full_name AS head_name, del.full_name AS deleted_by_name,
                (SELECT COUNT(*) FROM teachers t WHERE t.department_id = d.id) AS teacher_count
         FROM departments d
         LEFT JOIN teachers th ON d.head_teacher_id = th.id
         LEFT JOIN users u ON th.user_id = u.id
         LEFT JOIN users del ON d.deleted_by = del.id
         WHERE d.faculty_id = ? AND d.deleted_at IS NOT NULL
         ORDER BY d.deleted_at DESC"
    );
} else {
    $stmtDepts = $conn->prepare(
        "SELECT d.*, u.full_name AS head_name,
                (SELECT COUNT(*) FROM teachers t WHERE t.department_id = d.id AND t.faculty_id = ?) AS teacher_count
         FROM departments d
         LEFT JOIN teachers th ON d.head_teacher_id = th.id
         LEFT JOIN users u ON th.user_id = u.id
         WHERE d.faculty_id = ? AND d.deleted_at IS NULL
         ORDER BY d.department_name ASC"
    );
}

if ($showArchived) {
    $stmtDepts->bind_param('i', $facultyId);
} else {
    $stmtDepts->bind_param('ii', $facultyId, $facultyId);
}
$stmtDepts->execute();
$departments = $stmtDepts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtDepts->close();

// Lấy danh sách GV để chọn trưởng bộ môn
$teachers = [];
$stmtT = $conn->prepare("SELECT t.id, t.teacher_code, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.faculty_id = ? ORDER BY u.full_name ASC");
$stmtT->bind_param('i', $facultyId);
$stmtT->execute();
$teachers = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtT->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mở/đóng menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-diagram-3-fill me-2 text-navy" aria-hidden="true"></i>Bộ môn
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($showArchived): ?>
            <a href="departments.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Danh sách hiện tại
            </a>
            <?php else: ?>
            <a href="departments.php?archived=1" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-archive me-1" aria-hidden="true"></i>Đã xóa
            </a>
            <?php endif; ?>
            <?php if (isFacultyManager() && !$showArchived): ?>
            <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addDeptModal"
                    aria-label="Thêm bộ môn mới">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Thêm bộ môn
            </button>
            <?php endif; ?>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Đăng xuất
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-diagram-3-fill me-2" aria-hidden="true"></i>
                <?php echo $showArchived ? 'Bộ môn đã xóa' : 'Danh sách Bộ môn'; ?>
                <span class="badge bg-light text-dark ms-2"><?php echo count($departments); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã BM</th>
                            <th>Tên bộ môn</th>
                            <th>Trưởng BM</th>
                            <th class="text-center">Số GV</th>
                            <th>Mô tả</th>
                            <?php if ($showArchived): ?>
                            <th>Ngày xóa</th>
                            <th>Người xóa</th>
                            <?php endif; ?>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="<?php echo $showArchived ? 8 : 6; ?>" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                <?php echo $showArchived ? 'Không có bộ môn nào đã xóa.' : 'Chưa có bộ môn nào.'; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($departments as $d): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($d['department_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($d['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['head_name'] ?? '—'); ?></td>
                            <td class="text-center"><?php echo (int)$d['teacher_count']; ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($d['description'] ?? ''); ?></td>
                            <?php if ($showArchived): ?>
                            <td class="text-muted small"><?php echo htmlspecialchars($d['deleted_at'] ?? ''); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($d['deleted_by_name'] ?? '—'); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if ($showArchived): ?>
                                <?php if (isFacultyManager()): ?>
                                <form method="post" action="departments.php" class="d-inline">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="dept_id" value="<?php echo (int)$d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success"
                                            aria-label="Khôi phục bộ môn <?php echo htmlspecialchars($d['department_name']); ?>">
                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Khôi phục
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php else: ?>
                                <?php if (isFacultyManager()): ?>
                                <button class="btn btn-sm btn-outline-navy me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editDeptModal"
                                        data-id="<?php echo (int)$d['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($d['department_code']); ?>"
                                        data-name="<?php echo htmlspecialchars($d['department_name']); ?>"
                                        data-head="<?php echo (int)($d['head_teacher_id'] ?? 0); ?>"
                                        data-desc="<?php echo htmlspecialchars($d['description'] ?? ''); ?>"
                                        aria-label="Sửa bộ môn <?php echo htmlspecialchars($d['department_name']); ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="departments.php" class="d-inline"
                                      onsubmit="return confirm('Xóa bộ môn này? Giảng viên thuộc bộ môn sẽ được bỏ phân bộ môn.')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="soft_delete">
                                    <input type="hidden" name="dept_id" value="<?php echo (int)$d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            aria-label="Xóa bộ môn <?php echo htmlspecialchars($d['department_name']); ?>">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.admin-content -->

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div><!-- /.admin-main -->

<!-- ── Add Department Modal ── -->
<?php if (isFacultyManager() && !$showArchived): ?>
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDeptModalLabel">Thêm Bộ môn mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_dept_code" class="form-label">Mã bộ môn <span class="text-danger">*</span></label>
                        <input type="text" id="add_dept_code" name="department_code" class="form-control" required
                               placeholder="VD: BM_CNPM">
                    </div>
                    <div class="mb-3">
                        <label for="add_dept_name" class="form-label">Tên bộ môn <span class="text-danger">*</span></label>
                        <input type="text" id="add_dept_name" name="department_name" class="form-control" required
                               placeholder="VD: Bộ môn Công nghệ Phần mềm">
                    </div>
                    <div class="mb-3">
                        <label for="add_head_teacher" class="form-label">Trưởng bộ môn</label>
                        <select id="add_head_teacher" name="head_teacher_id" class="form-select">
                            <option value="0">-- Chưa chọn --</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>">
                                <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add_desc" class="form-label">Mô tả</label>
                        <textarea id="add_desc" name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Thêm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Edit Department Modal ── -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-labelledby="editDeptModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="departments.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="dept_id" id="edit_dept_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDeptModalLabel">Sửa Bộ môn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Mã bộ môn</label>
                        <input type="text" id="edit_dept_code_display" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="edit_dept_name" class="form-label">Tên bộ môn <span class="text-danger">*</span></label>
                        <input type="text" id="edit_dept_name" name="department_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_head_teacher" class="form-label">Trưởng bộ môn</label>
                        <select id="edit_head_teacher" name="head_teacher_id" class="form-select">
                            <option value="0">-- Chưa chọn --</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>">
                                <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_desc" class="form-label">Mô tả</label>
                        <textarea id="edit_desc" name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Populate edit modal
document.getElementById('editDeptModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('edit_dept_id').value = btn.dataset.id;
    document.getElementById('edit_dept_code_display').value = btn.dataset.code;
    document.getElementById('edit_dept_name').value = btn.dataset.name;
    document.getElementById('edit_head_teacher').value = btn.dataset.head || '0';
    document.getElementById('edit_desc').value = btn.dataset.desc || '';
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
