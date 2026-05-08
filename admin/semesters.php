<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Học kỳ';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name           = trim($_POST['name'] ?? '');
        $school_year    = trim($_POST['school_year'] ?? '');
        $start_date     = trim($_POST['start_date'] ?? '') ?: null;
        $end_date       = trim($_POST['end_date'] ?? '') ?: null;
        $register_start = trim($_POST['register_start'] ?? '') ?: null;
        $register_end   = trim($_POST['register_end'] ?? '') ?: null;
        $status         = trim($_POST['status'] ?? 'closed');
        if ($name) {
            $stmt = $conn->prepare("INSERT INTO semesters (semester_name, school_year, start_date, end_date, register_start, register_end, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssss', $name, $school_year, $start_date, $end_date, $register_start, $register_end, $status);
            $stmt->execute() ? $success = 'Thêm học kỳ thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng nhập tên học kỳ.'; }
    }

    if ($action === 'edit') {
        $id             = intval($_POST['id'] ?? 0);
        $name           = trim($_POST['name'] ?? '');
        $school_year    = trim($_POST['school_year'] ?? '');
        $start_date     = trim($_POST['start_date'] ?? '') ?: null;
        $end_date       = trim($_POST['end_date'] ?? '') ?: null;
        $register_start = trim($_POST['register_start'] ?? '') ?: null;
        $register_end   = trim($_POST['register_end'] ?? '') ?: null;
        $status         = trim($_POST['status'] ?? 'closed');
        if ($id && $name) {
            $stmt = $conn->prepare("UPDATE semesters SET semester_name=?, school_year=?, start_date=?, end_date=?, register_start=?, register_end=?, status=? WHERE id=?");
            $stmt->bind_param('sssssssi', $name, $school_year, $start_date, $end_date, $register_start, $register_end, $status, $id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM semesters WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    // Bật/tắt trạng thái học kỳ
    if ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("UPDATE semesters SET status = IF(status='open','closed','open') WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Đã cập nhật trạng thái học kỳ!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    // Mở đăng ký môn học ngay lập tức (đặt register_start = now, register_end = +N ngày)
    if ($action === 'open_registration') {
        $id   = intval($_POST['id'] ?? 0);
        $days = intval($_POST['reg_days'] ?? 7);
        if ($id && $days > 0) {
            // Kiểm tra học kỳ có lớp học phần không
            $hasSection = $conn->query("SELECT COUNT(*) as c FROM course_sections WHERE semester_id=$id")->fetch_assoc()['c'];

            $reg_start = date('Y-m-d H:i:s');
            $reg_end   = date('Y-m-d H:i:s', strtotime("+$days days"));
            // Mở học kỳ + đặt thời gian đăng ký
            $stmt = $conn->prepare("UPDATE semesters SET register_start=?, register_end=?, status='open' WHERE id=?");
            $stmt->bind_param('ssi', $reg_start, $reg_end, $id);
            if ($stmt->execute()) {
                $msg = "Đã mở đăng ký môn học! Hạn: " . date('d/m/Y H:i', strtotime($reg_end));
                if ($hasSection == 0) {
                    $msg .= " ⚠️ Lưu ý: Học kỳ này chưa có lớp học phần nào!";
                }
                $success = $msg;
            } else {
                $error = 'Lỗi: ' . $conn->error;
            }
            $stmt->close();
        }
    }

    // Đóng đăng ký môn học ngay lập tức
    if ($action === 'close_registration') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $now = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE semesters SET register_end=? WHERE id=?");
            $stmt->bind_param('si', $now, $id);
            $stmt->execute() ? $success = 'Đã đóng đăng ký môn học!' : $error = 'Lỗi: ' . $conn->error;
            $stmt->close();
        }
    }

    // ── PRG: redirect sau POST để tránh F5 gửi lại form ──
    if (!empty($success) || !empty($error)) {
        $_SESSION['_flash'] = [
            'type'    => !empty($success) ? 'success' : 'danger',
            'message' => !empty($success) ? $success : $error,
        ];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($qs ? '?' . $qs : ''));
        exit();
    }
}

$semesters = $conn->query("
    SELECT sm.*, COUNT(cs.id) as section_count
    FROM semesters sm
    LEFT JOIN course_sections cs ON cs.semester_id = sm.id
    GROUP BY sm.id
    ORDER BY sm.created_at DESC
");
$now = date('Y-m-d H:i:s');

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Học kỳ & Đăng ký môn</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <!-- Hướng dẫn nhanh -->
        <div class="alert alert-info d-flex gap-3 align-items-start mb-4">
            <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
            <div>
                <strong>Hướng dẫn mở đăng ký môn học:</strong>
                <ul class="mb-0 mt-1 small">
                    <li>Nhấn <span class="badge bg-success">Mở đăng ký</span> để cho phép sinh viên đăng ký môn ngay lập tức.</li>
                    <li>Nhập số ngày muốn mở (mặc định 7 ngày), hệ thống tự đặt thời gian bắt đầu = bây giờ.</li>
                    <li>Nhấn <span class="badge bg-danger">Đóng đăng ký</span> để kết thúc sớm thời gian đăng ký.</li>
                    <li>Sinh viên chỉ đăng ký được khi học kỳ <strong>đang mở</strong> và <strong>trong thời gian đăng ký</strong>.</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar3 me-2"></i>Danh sách Học kỳ</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm học kỳ
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tên học kỳ</th>
                                <th>Năm học</th>
                                <th>Ngày bắt đầu</th>
                                <th>Ngày kết thúc</th>
                                <th>Lớp HP</th>
                                <th>Đăng ký môn học</th>
                                <th>Trạng thái ĐK</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($semesters && $semesters->num_rows > 0): $idx=1; while ($s = $semesters->fetch_assoc()):
                                $regOpen = $s['register_start'] && $s['register_end']
                                    && $now >= $s['register_start']
                                    && $now <= $s['register_end'];
                                $regFuture = $s['register_start'] && $now < $s['register_start'];
                                $regPast   = $s['register_end'] && $now > $s['register_end'];
                            ?>
                            <tr class="<?php echo $regOpen ? 'table-success' : ''; ?>">
                                <td><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold text-navy"><?php echo htmlspecialchars($s['semester_name']); ?></div>
                                    <span class="badge bg-<?php echo $s['status']=='open'?'success':'secondary'; ?>">
                                        <?php echo $s['status']=='open'?'Học kỳ mở':'Học kỳ đóng'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($s['school_year']); ?></td>
                                <td class="text-muted small">
                                    <?php echo $s['start_date'] ? date('d/m/Y', strtotime($s['start_date'])) : '<span class="text-danger">Chưa có</span>'; ?>
                                </td>
                                <td class="small">
                                    <?php if ($s['end_date']): ?>
                                        <span class="fw-bold text-navy"><?php echo date('d/m/Y', strtotime($s['end_date'])); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Chưa có
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['section_count'] > 0): ?>
                                    <span class="badge bg-success"><?php echo $s['section_count']; ?> lớp</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Chưa có</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($s['register_start'] && $s['register_end']): ?>
                                    <div><i class="bi bi-play-fill text-success"></i> <?php echo date('d/m/Y H:i', strtotime($s['register_start'])); ?></div>
                                    <div><i class="bi bi-stop-fill text-danger"></i> <?php echo date('d/m/Y H:i', strtotime($s['register_end'])); ?></div>
                                    <?php else: ?>
                                    <span class="text-muted">Chưa thiết lập</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($regOpen): ?>
                                        <span class="badge bg-success fs-6 px-3 py-2">
                                            <i class="bi bi-unlock-fill me-1"></i>Đang mở
                                        </span>
                                        <div class="text-muted small mt-1">
                                            Còn <?php
                                                $diff = strtotime($s['register_end']) - time();
                                                $days = floor($diff/86400);
                                                $hours = floor(($diff%86400)/3600);
                                                echo $days > 0 ? "$days ngày $hours giờ" : "$hours giờ";
                                            ?>
                                        </div>
                                    <?php elseif ($regFuture): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-clock me-1"></i>Sắp mở
                                        </span>
                                        <div class="text-muted small mt-1">
                                            <?php echo date('d/m/Y H:i', strtotime($s['register_start'])); ?>
                                        </div>
                                    <?php elseif ($regPast): ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-lock-fill me-1"></i>Đã đóng
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-dash-circle me-1"></i>Chưa mở
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (!$regOpen): ?>
                                        <!-- Nút mở đăng ký -->
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                            data-bs-target="#openRegModal"
                                            data-id="<?php echo $s['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($s['semester_name']); ?>"
                                            data-sections="<?php echo $s['section_count']; ?>"
                                            title="Mở đăng ký môn học">
                                            <i class="bi bi-unlock-fill me-1"></i>Mở ĐK
                                        </button>
                                        <?php else: ?>
                                        <!-- Nút đóng đăng ký -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Đóng đăng ký môn học ngay?')">
                                            <input type="hidden" name="action" value="close_registration">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Đóng đăng ký">
                                                <i class="bi bi-lock-fill me-1"></i>Đóng ĐK
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <!-- Nút bật/tắt học kỳ -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $s['status']=='open'?'warning':'outline-success'; ?>" title="<?php echo $s['status']=='open'?'Đóng học kỳ':'Mở học kỳ'; ?>">
                                                <i class="bi bi-<?php echo $s['status']=='open'?'pause':'play'; ?>-fill"></i>
                                            </button>
                                        </form>

                                        <!-- Nút sửa -->
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $s['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($s['semester_name']); ?>"
                                            data-year="<?php echo htmlspecialchars($s['school_year']); ?>"
                                            data-start="<?php echo $s['start_date']; ?>"
                                            data-end="<?php echo $s['end_date']; ?>"
                                            data-regstart="<?php echo $s['register_start'] ? date('Y-m-d\TH:i', strtotime($s['register_start'])) : ''; ?>"
                                            data-regend="<?php echo $s['register_end'] ? date('Y-m-d\TH:i', strtotime($s['register_end'])) : ''; ?>"
                                            data-status="<?php echo $s['status']; ?>">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>

                                        <!-- Nút xóa -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa học kỳ này?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </div>
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

<!-- Modal Mở đăng ký -->
<div class="modal fade" id="openRegModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-unlock-fill me-2"></i>Mở đăng ký môn học</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="open_registration">
                <input type="hidden" name="id" id="openRegId">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Bạn đang mở đăng ký môn học cho học kỳ: <strong id="openRegName"></strong>
                    </div>
                    <div id="openRegWarn" style="display:none"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Thời gian mở đăng ký (số ngày)</label>
                        <div class="input-group">
                            <input type="number" name="reg_days" class="form-control form-control-lg"
                                value="7" min="1" max="60" required>
                            <span class="input-group-text">ngày</span>
                        </div>
                        <div class="form-text">
                            Hệ thống sẽ đặt thời gian bắt đầu = <strong>ngay bây giờ</strong> và kết thúc sau số ngày bạn nhập.
                        </div>
                    </div>
                    <div class="bg-light rounded p-3">
                        <div class="small text-muted mb-1">Thời gian dự kiến:</div>
                        <div><i class="bi bi-play-fill text-success me-1"></i><strong>Bắt đầu:</strong> <?php echo date('d/m/Y H:i'); ?> (ngay bây giờ)</div>
                        <div id="openRegEndPreview"><i class="bi bi-stop-fill text-danger me-1"></i><strong>Kết thúc:</strong> sau 7 ngày</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-unlock-fill me-2"></i>Mở đăng ký ngay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Thêm học kỳ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Thêm Học kỳ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tên học kỳ <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="VD: Học kỳ 1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Năm học</label>
                            <input type="text" name="school_year" class="form-control" placeholder="VD: 2025-2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày bắt đầu học</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày kết thúc học</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-12"><hr class="my-1"><small class="text-muted fw-bold">Thời gian đăng ký môn học (có thể để trống, dùng nút "Mở ĐK" sau)</small></div>
                        <div class="col-md-6">
                            <label class="form-label">Bắt đầu đăng ký môn</label>
                            <input type="datetime-local" name="register_start" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kết thúc đăng ký môn</label>
                            <input type="datetime-local" name="register_end" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái học kỳ</label>
                            <select name="status" class="form-select">
                                <option value="open">Đang mở</option>
                                <option value="closed" selected>Đã đóng</option>
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

<!-- Modal Sửa học kỳ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Học kỳ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tên học kỳ <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Năm học</label>
                            <input type="text" name="school_year" id="editYear" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày bắt đầu học</label>
                            <input type="date" name="start_date" id="editStart" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ngày kết thúc học</label>
                            <input type="date" name="end_date" id="editEnd" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bắt đầu đăng ký môn</label>
                            <input type="datetime-local" name="register_start" id="editRegStart" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kết thúc đăng ký môn</label>
                            <input type="datetime-local" name="register_end" id="editRegEnd" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái học kỳ</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="open">Đang mở</option>
                                <option value="closed">Đã đóng</option>
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
// Edit modal
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value       = btn.dataset.id;
    document.getElementById('editName').value     = btn.dataset.name;
    document.getElementById('editYear').value     = btn.dataset.year;
    document.getElementById('editStart').value    = btn.dataset.start;
    document.getElementById('editEnd').value      = btn.dataset.end;
    document.getElementById('editRegStart').value = btn.dataset.regstart;
    document.getElementById('editRegEnd').value   = btn.dataset.regend;
    document.getElementById('editStatus').value   = btn.dataset.status;
});

// Open registration modal
document.getElementById('openRegModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('openRegId').value   = btn.dataset.id;
    document.getElementById('openRegName').textContent = btn.dataset.name;

    // Cảnh báo nếu không có lớp HP
    const sections = parseInt(btn.dataset.sections) || 0;
    const warnEl = document.getElementById('openRegWarn');
    if (sections === 0) {
        warnEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Cảnh báo:</strong> Học kỳ này chưa có lớp học phần nào! Sinh viên sẽ không thấy môn nào để đăng ký. Hãy thêm lớp học phần trước.';
        warnEl.className = 'alert alert-danger';
        warnEl.style.display = 'block';
    } else {
        warnEl.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Học kỳ này có <strong>' + sections + ' lớp học phần</strong>. Sinh viên sẽ thấy danh sách để đăng ký.';
        warnEl.className = 'alert alert-success';
        warnEl.style.display = 'block';
    }
});

// Preview end date when days change
document.querySelector('input[name="reg_days"]')?.addEventListener('input', function() {
    const days = parseInt(this.value) || 7;
    const end = new Date();
    end.setDate(end.getDate() + days);
    const fmt = end.toLocaleDateString('vi-VN', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    document.getElementById('openRegEndPreview').innerHTML =
        '<i class="bi bi-stop-fill text-danger me-1"></i><strong>Kết thúc:</strong> ' + fmt;
});
</script>
</body></html>
