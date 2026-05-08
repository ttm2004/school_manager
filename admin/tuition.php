<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Học phí';

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. Tạo hóa đơn hàng loạt cho học kỳ ─────────────────────────────────
    if ($action === 'generate_invoices') {
        $semester_id = intval($_POST['semester_id'] ?? 0);
        $due_date    = trim($_POST['due_date'] ?? '');
        $created     = 0;
        $skipped     = 0;

        if ($semester_id) {
            // Lấy tất cả sinh viên đã đăng ký môn trong học kỳ này
            $sql = "SELECT
                        ss.student_id,
                        SUM(sub.credits) AS total_credits,
                        m.tuition_per_credit AS unit_price
                    FROM student_subjects ss
                    JOIN course_sections cs ON ss.course_section_id = cs.id
                    JOIN subjects sub ON cs.subject_id = sub.id
                    JOIN students st ON ss.student_id = st.id
                    JOIN classes cl ON st.class_id = cl.id
                    JOIN majors m ON cl.major_id = m.id
                    WHERE cs.semester_id = ?
                      AND ss.status != 'cancelled'
                    GROUP BY ss.student_id, m.tuition_per_credit";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $semester_id);
            $stmt->execute();
            $rows = $stmt->get_result();
            $stmt->close();

            $admin_id = $_SESSION['user_id'] ?? null;
            while ($row = $rows->fetch_assoc()) {
                $sid        = $row['student_id'];
                $credits    = intval($row['total_credits']);
                $unit_price = floatval($row['unit_price'] ?? 0);
                $gross      = $credits * $unit_price;
                $net        = $gross; // discount = 0 ban đầu

                // Kiểm tra đã có hóa đơn chưa
                $chk = $conn->prepare("SELECT id FROM tuition_invoices WHERE student_id=? AND semester_id=?");
                $chk->bind_param('ii', $sid, $semester_id);
                $chk->execute();
                $exists = $chk->get_result()->fetch_assoc();
                $chk->close();

                if ($exists) { $skipped++; continue; }

                $ins = $conn->prepare("INSERT INTO tuition_invoices
                    (student_id, semester_id, total_credits, unit_price, gross_amount, discount, net_amount, paid_amount, due_date, status, created_by)
                    VALUES (?,?,?,?,?,0,?,0,?,'unpaid',?)");
                $due = $due_date ?: null;
                $ins->bind_param('iiidddssi', $sid, $semester_id, $credits, $unit_price, $gross, $net, $due, $admin_id);
                if ($ins->execute()) $created++;
                $ins->close();
            }
            $_SESSION['_flash'] = ['type' => 'success', 'message' => "Đã tạo $created hóa đơn mới. Bỏ qua $skipped (đã tồn tại)."];
        } else {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng chọn học kỳ.'];
        }
        header('Location: tuition.php' . (isset($_GET['semester_id']) ? '?semester_id=' . intval($_GET['semester_id']) : ''));
        exit();
    }

    // ── 2. Ghi nhận thanh toán ────────────────────────────────────────────────
    if ($action === 'record_payment') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $amount     = floatval($_POST['amount'] ?? 0);
        $method     = trim($_POST['method'] ?? 'cash');
        $reference  = trim($_POST['reference'] ?? '');
        $note       = trim($_POST['note'] ?? '');
        $admin_id   = $_SESSION['user_id'] ?? null;

        if ($invoice_id && $amount > 0) {
            // Lấy hóa đơn hiện tại
            $inv = $conn->prepare("SELECT * FROM tuition_invoices WHERE id=?");
            $inv->bind_param('i', $invoice_id);
            $inv->execute();
            $invoice = $inv->get_result()->fetch_assoc();
            $inv->close();

            if ($invoice) {
                // Ghi thanh toán
                $pay = $conn->prepare("INSERT INTO tuition_payments (invoice_id, amount, method, reference, note, paid_by) VALUES (?,?,?,?,?,?)");
                $pay->bind_param('idsssi', $invoice_id, $amount, $method, $reference, $note, $admin_id);
                $pay->execute();
                $pay->close();

                // Cập nhật paid_amount và status
                $new_paid = $invoice['paid_amount'] + $amount;
                $net      = $invoice['net_amount'];
                if ($invoice['status'] === 'waived') {
                    $new_status = 'waived';
                } elseif ($new_paid >= $net) {
                    $new_status = 'paid';
                } elseif ($new_paid > 0) {
                    $new_status = 'partial';
                } else {
                    $new_status = 'unpaid';
                }
                $upd = $conn->prepare("UPDATE tuition_invoices SET paid_amount=?, status=?, updated_at=NOW() WHERE id=?");
                $upd->bind_param('dsi', $new_paid, $new_status, $invoice_id);
                $upd->execute();
                $upd->close();

                $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Ghi nhận thanh toán thành công!'];
            } else {
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không tìm thấy hóa đơn.'];
            }
        } else {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Số tiền không hợp lệ.'];
        }
        $qs = http_build_query(array_filter(['semester_id' => $_GET['semester_id'] ?? '', 'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '']));
        header('Location: tuition.php' . ($qs ? '?' . $qs : ''));
        exit();
    }

    // ── 3. Cập nhật miễn giảm ─────────────────────────────────────────────────
    if ($action === 'update_discount') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $discount   = floatval($_POST['discount'] ?? 0);
        $note       = trim($_POST['note'] ?? '');

        if ($invoice_id) {
            $inv = $conn->prepare("SELECT gross_amount, paid_amount, status FROM tuition_invoices WHERE id=?");
            $inv->bind_param('i', $invoice_id);
            $inv->execute();
            $invoice = $inv->get_result()->fetch_assoc();
            $inv->close();

            if ($invoice) {
                $net = max(0, $invoice['gross_amount'] - $discount);
                if ($invoice['status'] === 'waived') {
                    $new_status = 'waived';
                } elseif ($invoice['paid_amount'] >= $net && $net > 0) {
                    $new_status = 'paid';
                } elseif ($invoice['paid_amount'] > 0) {
                    $new_status = 'partial';
                } else {
                    $new_status = 'unpaid';
                }
                $upd = $conn->prepare("UPDATE tuition_invoices SET discount=?, net_amount=?, note=?, status=?, updated_at=NOW() WHERE id=?");
                $upd->bind_param('ddssi', $discount, $net, $note, $new_status, $invoice_id);
                $upd->execute();
                $upd->close();
                $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật miễn giảm thành công!'];
            }
        }
        $qs = http_build_query(array_filter(['semester_id' => $_GET['semester_id'] ?? '', 'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '']));
        header('Location: tuition.php' . ($qs ? '?' . $qs : ''));
        exit();
    }

    // ── 4. Cập nhật hạn đóng ─────────────────────────────────────────────────
    if ($action === 'update_due_date') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $due_date   = trim($_POST['due_date'] ?? '');
        if ($invoice_id) {
            $upd = $conn->prepare("UPDATE tuition_invoices SET due_date=?, updated_at=NOW() WHERE id=?");
            $upd->bind_param('si', $due_date, $invoice_id);
            $upd->execute();
            $upd->close();
            $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật hạn đóng học phí thành công!'];
        }
        $qs = http_build_query(array_filter(['semester_id' => $_GET['semester_id'] ?? '', 'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '']));
        header('Location: tuition.php' . ($qs ? '?' . $qs : ''));
        exit();
    }
}

// ── Tự động tạo bảng nếu chưa có (chạy migration tự động) ───────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `tuition_invoices` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`    INT NOT NULL,
    `semester_id`   INT NOT NULL,
    `total_credits` INT NOT NULL DEFAULT 0,
    `unit_price`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gross_amount`  DECIMAL(14,2) NOT NULL DEFAULT 0,
    `discount`      DECIMAL(14,2) NOT NULL DEFAULT 0,
    `net_amount`    DECIMAL(14,2) NOT NULL DEFAULT 0,
    `paid_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0,
    `due_date`      DATE NULL,
    `status`        ENUM('unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'unpaid',
    `note`          TEXT NULL,
    `created_by`    INT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_student_semester` (`student_id`, `semester_id`),
    INDEX (`semester_id`), INDEX (`status`), INDEX (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `tuition_payments` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `amount`     DECIMAL(14,2) NOT NULL,
    `method`     ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash',
    `reference`  VARCHAR(100) NULL,
    `note`       VARCHAR(255) NULL,
    `paid_by`    INT NULL,
    `paid_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`invoice_id`), INDEX (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── FILTERS ───────────────────────────────────────────────────────────────────
$filter_semester = intval($_GET['semester_id'] ?? 0);
$filter_status   = trim($_GET['status'] ?? '');
$search          = trim($_GET['search'] ?? '');
$perPage         = 20;
$page            = max(1, intval($_GET['page'] ?? 1));
$offset          = ($page - 1) * $perPage;

// Danh sách học kỳ
$semesters = $conn->query("SELECT * FROM semesters ORDER BY school_year DESC, semester_name DESC");

// ── STATS ─────────────────────────────────────────────────────────────────────
$stats_where = $filter_semester ? "WHERE ti.semester_id = $filter_semester" : '';
$stats = $conn->query("
    SELECT
        COUNT(*) AS total_invoices,
        SUM(status='paid') AS total_paid,
        SUM(status='unpaid') AS total_unpaid,
        SUM(status='partial') AS total_partial,
        SUM(status='overdue') AS total_overdue,
        SUM(status='waived') AS total_waived,
        COALESCE(SUM(net_amount),0) AS sum_net,
        COALESCE(SUM(paid_amount),0) AS sum_paid,
        COALESCE(SUM(net_amount - paid_amount),0) AS sum_remaining
    FROM tuition_invoices ti $stats_where
");
$stats = ($stats && $stats->num_rows > 0) ? $stats->fetch_assoc() : [];
$stats = array_merge([
    'total_invoices' => 0,
    'total_paid'     => 0,
    'total_unpaid'   => 0,
    'total_partial'  => 0,
    'total_overdue'  => 0,
    'total_waived'   => 0,
    'sum_net'        => 0,
    'sum_paid'       => 0,
    'sum_remaining'  => 0,
], $stats ?: []);

// ── INVOICE LIST ──────────────────────────────────────────────────────────────
$conditions = [];
$params     = [];
$types      = '';

if ($filter_semester) {
    $conditions[] = 'ti.semester_id = ?';
    $params[]     = $filter_semester;
    $types       .= 'i';
}
if ($filter_status) {
    $conditions[] = 'ti.status = ?';
    $params[]     = $filter_status;
    $types       .= 's';
}
if ($search) {
    $like         = "%$search%";
    $conditions[] = '(u.full_name LIKE ? OR st.student_code LIKE ? OR u.email LIKE ?)';
    $params[]     = $like; $params[] = $like; $params[] = $like;
    $types       .= 'sss';
}

$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$base_sql = "FROM tuition_invoices ti
    JOIN students st ON ti.student_id = st.id
    JOIN users u ON st.user_id = u.id
    JOIN semesters sm ON ti.semester_id = sm.id
    LEFT JOIN classes cl ON st.class_id = cl.id
    LEFT JOIN majors m ON cl.major_id = m.id
    $where_sql";

// Count
$count_stmt = $conn->prepare("SELECT COUNT(*) AS c $base_sql");
if (!$count_stmt) { $total = 0; }
else {
    if ($types) { $count_stmt->bind_param($types, ...$params); }
    $count_stmt->execute();
    $total = (int)($count_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $count_stmt->close();
}
$totalPages = ceil($total / $perPage);

// Data
$invoices = null;
$data_stmt = $conn->prepare("SELECT
    ti.*,
    u.full_name, u.email,
    st.student_code,
    sm.semester_name, sm.school_year,
    cl.class_name,
    m.major_name
    $base_sql
    ORDER BY ti.created_at DESC
    LIMIT ? OFFSET ?");
if ($data_stmt) {
    $data_params   = $params;
    $data_types    = $types . 'ii';
    $data_params[] = $perPage;
    $data_params[] = $offset;
    $data_stmt->bind_param($data_types, ...$data_params);
    $data_stmt->execute();
    $invoices = $data_stmt->get_result();
    $data_stmt->close();
}

// Helper: format tiền VND
function fmtVND($n) {
    return number_format(floatval($n), 0, ',', '.') . ' ₫';
}

// Helper: badge trạng thái
function statusBadge($status) {
    $map = [
        'unpaid'  => ['warning',  'Chưa đóng'],
        'partial' => ['info',     'Đóng một phần'],
        'paid'    => ['success',  'Đã đóng'],
        'overdue' => ['danger',   'Quá hạn'],
        'waived'  => ['secondary','Miễn học phí'],
    ];
    $s = $map[$status] ?? ['secondary', $status];
    return '<span class="badge bg-' . $s[0] . '">' . $s[1] . '</span>';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="admin-main">
    <!-- TOPBAR -->
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <span class="admin-topbar-title"><i class="bi bi-cash-coin me-2 text-gold"></i>Quản lý Học phí</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
        </div>
    </div>

    <div class="admin-content">
        <!-- FLASH MESSAGE -->
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show">
            <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'; ?> me-2"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm" style="border-left:4px solid var(--navy) !important;">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-receipt fs-4 text-navy"></i>
                            <span class="text-muted small">Tổng hóa đơn</span>
                        </div>
                        <div class="fw-bold fs-4 text-navy"><?php echo number_format($stats['total_invoices']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm" style="border-left:4px solid #28a745 !important;">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                            <span class="text-muted small">Đã đóng</span>
                        </div>
                        <div class="fw-bold fs-4 text-success"><?php echo number_format($stats['total_paid']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm" style="border-left:4px solid #ffc107 !important;">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                            <span class="text-muted small">Chưa đóng</span>
                        </div>
                        <div class="fw-bold fs-4 text-warning"><?php echo number_format($stats['total_unpaid']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm" style="border-left:4px solid #dc3545 !important;">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
                            <span class="text-muted small">Quá hạn</span>
                        </div>
                        <div class="fw-bold fs-4 text-danger"><?php echo number_format($stats['total_overdue']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm" style="border-left:4px solid var(--gold) !important;">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-cash-stack fs-4 text-gold"></i>
                            <span class="text-muted small">Đã thu</span>
                        </div>
                        <div class="fw-bold" style="font-size:1rem;color:var(--gold);"><?php echo fmtVND($stats['sum_paid']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100 border-0 shadow-sm" style="border-left:4px solid #6f42c1 !important;">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-wallet2 fs-4" style="color:#6f42c1;"></i>
                            <span class="text-muted small">Còn lại</span>
                        </div>
                        <div class="fw-bold" style="font-size:1rem;color:#6f42c1;"><?php echo fmtVND($stats['sum_remaining']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER + GENERATE CARD -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-funnel me-2"></i>Lọc & Tạo hóa đơn</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#generateModal">
                    <i class="bi bi-lightning-fill me-1"></i>Tạo hóa đơn hàng loạt
                </button>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Học kỳ</label>
                        <select name="semester_id" class="form-select">
                            <option value="">-- Tất cả học kỳ --</option>
                            <?php
                            if ($semesters) {
                                $semesters->data_seek(0);
                                while ($sem = $semesters->fetch_assoc()):
                            ?>
                            <option value="<?php echo $sem['id']; ?>" <?php echo $filter_semester == $sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' ' . $sem['school_year']); ?>
                            </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <option value="unpaid"  <?php echo $filter_status === 'unpaid'  ? 'selected' : ''; ?>>Chưa đóng</option>
                            <option value="partial" <?php echo $filter_status === 'partial' ? 'selected' : ''; ?>>Đóng một phần</option>
                            <option value="paid"    <?php echo $filter_status === 'paid'    ? 'selected' : ''; ?>>Đã đóng</option>
                            <option value="overdue" <?php echo $filter_status === 'overdue' ? 'selected' : ''; ?>>Quá hạn</option>
                            <option value="waived"  <?php echo $filter_status === 'waived'  ? 'selected' : ''; ?>>Miễn học phí</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="search" class="form-control" placeholder="Tên, mã SV, email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-navy flex-fill"><i class="bi bi-search me-1"></i>Lọc</button>
                        <?php if ($filter_semester || $filter_status || $search): ?>
                        <a href="tuition.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- INVOICE TABLE -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2"></i>Danh sách Hóa đơn Học phí
                    <span class="badge bg-gold text-dark ms-2"><?php echo number_format($total); ?></span>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Sinh viên</th>
                                <th>Học kỳ</th>
                                <th class="text-center">TC</th>
                                <th class="text-end">Học phí gốc</th>
                                <th class="text-end">Miễn giảm</th>
                                <th class="text-end">Phải đóng</th>
                                <th class="text-end">Đã đóng</th>
                                <th class="text-center">Hạn đóng</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center" style="width:130px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($invoices && $invoices->num_rows > 0):
                            $idx = $offset + 1;
                            while ($inv = $invoices->fetch_assoc()):
                                $remaining = $inv['net_amount'] - $inv['paid_amount'];
                                $is_overdue = ($inv['status'] === 'overdue');
                        ?>
                            <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                <td class="text-muted small"><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold text-navy"><?php echo htmlspecialchars($inv['student_code']); ?></div>
                                    <div><?php echo htmlspecialchars($inv['full_name']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($inv['class_name'] ?? ''); ?> &bull; <?php echo htmlspecialchars($inv['major_name'] ?? ''); ?></div>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($inv['semester_name'] . ' ' . $inv['school_year']); ?></td>
                                <td class="text-center fw-bold"><?php echo $inv['total_credits']; ?></td>
                                <td class="text-end small"><?php echo fmtVND($inv['gross_amount']); ?></td>
                                <td class="text-end small text-success">
                                    <?php echo $inv['discount'] > 0 ? '-' . fmtVND($inv['discount']) : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td class="text-end fw-bold"><?php echo fmtVND($inv['net_amount']); ?></td>
                                <td class="text-end text-success fw-bold"><?php echo fmtVND($inv['paid_amount']); ?></td>
                                <td class="text-center small">
                                    <?php if ($inv['due_date']): ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('d/m/Y', strtotime($inv['due_date'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo statusBadge($inv['status']); ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                                        <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'waived'): ?>
                                        <button class="btn btn-sm btn-gold"
                                            title="Ghi nhận thanh toán"
                                            data-bs-toggle="modal" data-bs-target="#paymentModal"
                                            data-id="<?php echo $inv['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($inv['full_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($inv['student_code']); ?>"
                                            data-net="<?php echo $inv['net_amount']; ?>"
                                            data-paid="<?php echo $inv['paid_amount']; ?>"
                                            data-remaining="<?php echo max(0, $remaining); ?>">
                                            <i class="bi bi-cash-coin"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-primary"
                                            title="Cập nhật miễn giảm"
                                            data-bs-toggle="modal" data-bs-target="#discountModal"
                                            data-id="<?php echo $inv['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($inv['full_name']); ?>"
                                            data-gross="<?php echo $inv['gross_amount']; ?>"
                                            data-discount="<?php echo $inv['discount']; ?>"
                                            data-note="<?php echo htmlspecialchars($inv['note'] ?? ''); ?>">
                                            <i class="bi bi-percent"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary"
                                            title="Cập nhật hạn đóng"
                                            data-bs-toggle="modal" data-bs-target="#dueDateModal"
                                            data-id="<?php echo $inv['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($inv['full_name']); ?>"
                                            data-due="<?php echo $inv['due_date'] ?? ''; ?>">
                                            <i class="bi bi-calendar-event"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="11" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Chưa có hóa đơn nào<?php echo ($filter_semester || $filter_status || $search) ? ' phù hợp với bộ lọc' : ''; ?>
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                <div class="px-3 py-2 border-top">
                    <nav><ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
                        </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul></nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /.admin-content -->

    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div><!-- /.admin-main -->

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Tạo hóa đơn hàng loạt
══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-lightning-fill me-2"></i>Tạo hóa đơn hàng loạt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="generate_invoices">
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-1"></i>
                        Hệ thống sẽ tự động tạo hóa đơn cho tất cả sinh viên đã đăng ký môn học trong học kỳ được chọn.
                        Sinh viên đã có hóa đơn sẽ được bỏ qua.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Học kỳ <span class="text-danger">*</span></label>
                        <select name="semester_id" class="form-select" required>
                            <option value="">-- Chọn học kỳ --</option>
                            <?php
                            if ($semesters) {
                                $semesters->data_seek(0);
                                while ($sem = $semesters->fetch_assoc()):
                            ?>
                            <option value="<?php echo $sem['id']; ?>" <?php echo $filter_semester == $sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' ' . $sem['school_year']); ?>
                            </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Hạn đóng học phí</label>
                        <input type="date" name="due_date" class="form-control">
                        <div class="form-text">Để trống nếu chưa xác định hạn đóng.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold">
                        <i class="bi bi-lightning-fill me-1"></i>Tạo hóa đơn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Ghi nhận thanh toán
══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Ghi nhận Thanh toán</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="invoice_id" id="payInvoiceId">
                <div class="modal-body">
                    <div class="alert alert-light border mb-3 py-2 px-3" id="payStudentInfo">
                        <div class="fw-bold" id="payStudentName"></div>
                        <div class="small text-muted" id="payStudentCode"></div>
                    </div>
                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <label class="form-label small text-muted">Phải đóng</label>
                            <div class="fw-bold text-navy" id="payNetAmount"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">Còn lại</label>
                            <div class="fw-bold text-danger" id="payRemaining"></div>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Số tiền thanh toán <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="payAmount" class="form-control" min="1000" step="1000" required placeholder="VD: 5000000">
                        <div class="form-text">Nhập số tiền đóng lần này (VNĐ).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Hình thức thanh toán</label>
                        <select name="method" class="form-select">
                            <option value="cash">Tiền mặt</option>
                            <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                            <option value="online">Thanh toán online</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mã giao dịch / Số biên lai</label>
                        <input type="text" name="reference" class="form-control" placeholder="VD: TT20240101001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú thêm (nếu có)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold">
                        <i class="bi bi-save me-1"></i>Lưu thanh toán
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Cập nhật miễn giảm
══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-percent me-2"></i>Cập nhật Miễn giảm / Học bổng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_discount">
                <input type="hidden" name="invoice_id" id="discInvoiceId">
                <div class="modal-body">
                    <div class="alert alert-light border mb-3 py-2 px-3">
                        <div class="fw-bold" id="discStudentName"></div>
                        <div class="small text-muted">Học phí gốc: <span class="fw-bold text-navy" id="discGrossAmount"></span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Số tiền miễn giảm (VNĐ) <span class="text-danger">*</span></label>
                        <input type="number" name="discount" id="discDiscount" class="form-control" min="0" step="1000" required placeholder="0">
                        <div class="form-text">Nhập 0 nếu không có miễn giảm.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ghi chú / Lý do miễn giảm</label>
                        <textarea name="note" id="discNote" class="form-control" rows="2" placeholder="VD: Học bổng khuyến khích học tập, Chính sách ưu tiên..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-save me-1"></i>Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Cập nhật hạn đóng
══════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="dueDateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Cập nhật Hạn đóng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_due_date">
                <input type="hidden" name="invoice_id" id="dueInvoiceId">
                <div class="modal-body">
                    <div class="mb-2 fw-bold" id="dueStudentName"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Hạn đóng học phí</label>
                        <input type="date" name="due_date" id="dueDateInput" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy btn-sm">
                        <i class="bi bi-save me-1"></i>Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
// ── Payment Modal ─────────────────────────────────────────────────────────────
document.getElementById('paymentModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('payInvoiceId').value   = btn.dataset.id;
    document.getElementById('payStudentName').textContent = btn.dataset.name;
    document.getElementById('payStudentCode').textContent = btn.dataset.code;

    const net       = parseFloat(btn.dataset.net)       || 0;
    const remaining = parseFloat(btn.dataset.remaining) || 0;

    document.getElementById('payNetAmount').textContent  = formatVND(net);
    document.getElementById('payRemaining').textContent  = formatVND(remaining);
    document.getElementById('payAmount').value           = remaining > 0 ? remaining : '';
    document.getElementById('payAmount').max             = remaining > 0 ? remaining : '';
});

// ── Discount Modal ────────────────────────────────────────────────────────────
document.getElementById('discountModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('discInvoiceId').value      = btn.dataset.id;
    document.getElementById('discStudentName').textContent = btn.dataset.name;
    document.getElementById('discGrossAmount').textContent = formatVND(parseFloat(btn.dataset.gross) || 0);
    document.getElementById('discDiscount').value        = btn.dataset.discount || 0;
    document.getElementById('discNote').value            = btn.dataset.note || '';
});

// ── Due Date Modal ────────────────────────────────────────────────────────────
document.getElementById('dueDateModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('dueInvoiceId').value       = btn.dataset.id;
    document.getElementById('dueStudentName').textContent = btn.dataset.name;
    document.getElementById('dueDateInput').value        = btn.dataset.due || '';
});

// ── Format VND ────────────────────────────────────────────────────────────────
function formatVND(n) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(n);
}
</script>
</body>
</html>
