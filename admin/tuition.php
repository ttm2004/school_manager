<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Học phí';

// ── Tự động tạo bảng ─────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `tuition_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `total_credits` INT NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gross_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `discount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `net_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `due_date` DATE NULL,
    `status` ENUM('unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'unpaid',
    `note` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ss` (`student_id`,`semester_id`),
    INDEX(`semester_id`), INDEX(`status`), INDEX(`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `tuition_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `amount` DECIMAL(14,2) NOT NULL,
    `method` ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash',
    `reference` VARCHAR(100) NULL,
    `note` VARCHAR(255) NULL,
    `paid_by` INT NULL,
    `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(`invoice_id`), INDEX(`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── AJAX: lịch sử thanh toán ──────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'view_payments') {
    header('Content-Type: application/json; charset=utf-8');
    $iid = intval($_GET['invoice_id'] ?? 0);
    $pays = [];
    if ($iid) {
        $r = $conn->prepare("SELECT tp.*, u.full_name AS paid_by_name
            FROM tuition_payments tp LEFT JOIN users u ON tp.paid_by=u.id
            WHERE tp.invoice_id=? ORDER BY tp.paid_at DESC");
        $r->bind_param('i', $iid); $r->execute();
        $res = $r->get_result();
        while ($row = $res->fetch_assoc()) $pays[] = $row;
        $r->close();
    }
    echo json_encode(['payments' => $pays]); exit();
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_invoices') {
        $sem_id   = intval($_POST['semester_id'] ?? 0);
        $due_date = trim($_POST['due_date'] ?? '');
        $created = $skipped = 0;
        if ($sem_id) {
            $stmt = $conn->prepare("
                SELECT ss.student_id,
                    SUM(sub.credits) AS total_credits,
                    m.tuition_per_credit AS unit_price
                FROM student_subjects ss
                JOIN course_sections cs ON ss.course_section_id=cs.id
                JOIN subjects sub ON cs.subject_id=sub.id
                JOIN students st ON ss.student_id=st.id
                JOIN classes cl ON st.class_id=cl.id
                JOIN majors m ON cl.major_id=m.id
                WHERE cs.semester_id=? AND ss.status!='cancelled'
                GROUP BY ss.student_id, m.tuition_per_credit");
            $stmt->bind_param('i', $sem_id); $stmt->execute();
            $rows = $stmt->get_result(); $stmt->close();
            $aid = (int)($_SESSION['user_id'] ?? 0);
            while ($row = $rows->fetch_assoc()) {
                $sid = (int)$row['student_id'];
                $tc  = (int)$row['total_credits'];
                $up  = (float)$row['unit_price'];
                $gross = $tc * $up;
                $chk = $conn->prepare("SELECT id FROM tuition_invoices WHERE student_id=? AND semester_id=?");
                $chk->bind_param('ii', $sid, $sem_id); $chk->execute();
                if ($chk->get_result()->num_rows > 0) { $chk->close(); $skipped++; continue; }
                $chk->close();
                $due = $due_date ?: null;
                $ins = $conn->prepare("INSERT INTO tuition_invoices
                    (student_id,semester_id,total_credits,unit_price,gross_amount,discount,net_amount,paid_amount,due_date,status,created_by)
                    VALUES (?,?,?,?,?,0,?,0,?,'unpaid',?)");
                $ins->bind_param('iiidddssi', $sid, $sem_id, $tc, $up, $gross, $gross, $due, $aid);
                if ($ins->execute()) $created++;
                $ins->close();
            }
            $_SESSION['_flash'] = ['type'=>'success','message'=>"Đã tạo $created hóa đơn. Bỏ qua $skipped (đã tồn tại)."];
        } else {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng chọn học kỳ.'];
        }
        header('Location: tuition.php' . ($sem_id ? "?semester_id=$sem_id" : '')); exit();
    }

    if ($action === 'record_payment') {
        $iid    = intval($_POST['invoice_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? 'cash');
        $ref    = trim($_POST['reference'] ?? '');
        $note   = trim($_POST['note'] ?? '');
        $aid    = (int)($_SESSION['user_id'] ?? 0);
        if ($iid && $amount > 0) {
            $inv = $conn->prepare("SELECT * FROM tuition_invoices WHERE id=?");
            $inv->bind_param('i', $iid); $inv->execute();
            $invoice = $inv->get_result()->fetch_assoc(); $inv->close();
            if ($invoice) {
                $pay = $conn->prepare("INSERT INTO tuition_payments (invoice_id,amount,method,reference,note,paid_by) VALUES (?,?,?,?,?,?)");
                $pay->bind_param('idsssi', $iid, $amount, $method, $ref, $note, $aid);
                $pay->execute(); $pay->close();
                $new_paid = $invoice['paid_amount'] + $amount;
                $net = $invoice['net_amount'];
                $st = $invoice['status'] === 'waived' ? 'waived' : ($new_paid >= $net ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid'));
                $upd = $conn->prepare("UPDATE tuition_invoices SET paid_amount=?,status=?,updated_at=NOW() WHERE id=?");
                $upd->bind_param('dsi', $new_paid, $st, $iid); $upd->execute(); $upd->close();
                $_SESSION['_flash'] = ['type'=>'success','message'=>'Ghi nhận thanh toán thành công!'];
            } else { $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không tìm thấy hóa đơn.']; }
        } else { $_SESSION['_flash'] = ['type'=>'danger','message'=>'Số tiền không hợp lệ.']; }
        $qs = http_build_query(array_filter(['semester_id'=>$_GET['semester_id']??'','status'=>$_GET['status']??'','search'=>$_GET['search']??'']));
        header('Location: tuition.php' . ($qs ? "?$qs" : '')); exit();
    }

    if ($action === 'update_discount') {
        $iid      = intval($_POST['invoice_id'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $note     = trim($_POST['note'] ?? '');
        if ($iid) {
            $inv = $conn->prepare("SELECT gross_amount,paid_amount,status FROM tuition_invoices WHERE id=?");
            $inv->bind_param('i', $iid); $inv->execute();
            $invoice = $inv->get_result()->fetch_assoc(); $inv->close();
            if ($invoice) {
                $net = max(0, $invoice['gross_amount'] - $discount);
                $st  = $invoice['status'] === 'waived' ? 'waived' : ($invoice['paid_amount'] >= $net && $net > 0 ? 'paid' : ($invoice['paid_amount'] > 0 ? 'partial' : 'unpaid'));
                $upd = $conn->prepare("UPDATE tuition_invoices SET discount=?,net_amount=?,note=?,status=?,updated_at=NOW() WHERE id=?");
                $upd->bind_param('ddssi', $discount, $net, $note, $st, $iid); $upd->execute(); $upd->close();
                $_SESSION['_flash'] = ['type'=>'success','message'=>'Cập nhật miễn giảm thành công!'];
            }
        }
        $qs = http_build_query(array_filter(['semester_id'=>$_GET['semester_id']??'','status'=>$_GET['status']??'','search'=>$_GET['search']??'']));
        header('Location: tuition.php' . ($qs ? "?$qs" : '')); exit();
    }

    if ($action === 'update_due_date') {
        $iid = intval($_POST['invoice_id'] ?? 0);
        $due = trim($_POST['due_date'] ?? '');
        if ($iid) {
            $upd = $conn->prepare("UPDATE tuition_invoices SET due_date=?,updated_at=NOW() WHERE id=?");
            $upd->bind_param('si', $due, $iid); $upd->execute(); $upd->close();
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Cập nhật hạn đóng thành công!'];
        }
        $qs = http_build_query(array_filter(['semester_id'=>$_GET['semester_id']??'','status'=>$_GET['status']??'','search'=>$_GET['search']??'']));
        header('Location: tuition.php' . ($qs ? "?$qs" : '')); exit();
    }

    if ($action === 'mark_overdue') {
        $conn->query("UPDATE tuition_invoices SET status='overdue',updated_at=NOW() WHERE status IN ('unpaid','partial') AND due_date IS NOT NULL AND due_date < CURDATE()");
        $n = $conn->affected_rows;
        $_SESSION['_flash'] = ['type'=>'success','message'=>"Đã đánh dấu $n hóa đơn quá hạn."];
        $qs = http_build_query(array_filter(['semester_id'=>$_GET['semester_id']??'','status'=>$_GET['status']??'','search'=>$_GET['search']??'']));
        header('Location: tuition.php' . ($qs ? "?$qs" : '')); exit();
    }
}

// ── FILTERS ───────────────────────────────────────────────────────────────────
$filter_sem    = intval($_GET['semester_id'] ?? 0);
$filter_status = trim($_GET['status'] ?? '');
$search        = trim($_GET['search'] ?? '');
$perPage       = 20;
$page          = max(1, intval($_GET['page'] ?? 1));
$offset        = ($page - 1) * $perPage;

$semesters = $conn->query("SELECT * FROM semesters ORDER BY school_year DESC, semester_name DESC");

// ── STATS ─────────────────────────────────────────────────────────────────────
$_sw = $filter_sem ? "WHERE semester_id=$filter_sem" : '';
$_sr = $conn->query("SELECT COUNT(*) total_invoices,
    COALESCE(SUM(status='paid'),0) total_paid,
    COALESCE(SUM(status='unpaid'),0) total_unpaid,
    COALESCE(SUM(status='partial'),0) total_partial,
    COALESCE(SUM(status='overdue'),0) total_overdue,
    COALESCE(SUM(status='waived'),0) total_waived,
    COALESCE(SUM(net_amount),0) sum_net,
    COALESCE(SUM(paid_amount),0) sum_paid,
    COALESCE(SUM(net_amount-paid_amount),0) sum_remaining
    FROM tuition_invoices $_sw");
$_tuitionStats = array_merge(
    ['total_invoices'=>0,'total_paid'=>0,'total_unpaid'=>0,'total_partial'=>0,'total_overdue'=>0,'total_waived'=>0,'sum_net'=>0,'sum_paid'=>0,'sum_remaining'=>0],
    ($_sr instanceof mysqli_result ? ($_sr->fetch_assoc() ?? []) : [])
);

// ── INVOICE LIST ──────────────────────────────────────────────────────────────
$conds = []; $params = []; $types = '';
if ($filter_sem)    { $conds[] = 'ti.semester_id=?'; $params[] = $filter_sem;    $types .= 'i'; }
if ($filter_status) { $conds[] = 'ti.status=?';      $params[] = $filter_status; $types .= 's'; }
if ($search) {
    $like = "%$search%";
    $conds[] = '(u.full_name LIKE ? OR st.student_code LIKE ? OR u.email LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
}
$where = $conds ? 'WHERE '.implode(' AND ',$conds) : '';
$base  = "FROM tuition_invoices ti
    JOIN students st ON ti.student_id=st.id
    JOIN users u ON st.user_id=u.id
    JOIN semesters sm ON ti.semester_id=sm.id
    LEFT JOIN classes cl ON st.class_id=cl.id
    LEFT JOIN majors m ON cl.major_id=m.id
    $where";

$total = 0;
$cs = $conn->prepare("SELECT COUNT(*) c $base");
if ($cs) {
    if ($types) $cs->bind_param($types, ...$params);
    $cs->execute(); $total = (int)($cs->get_result()->fetch_assoc()['c'] ?? 0); $cs->close();
}
$totalPages = max(1, (int)ceil($total / $perPage));

$invoices = null;
$ds = $conn->prepare("SELECT ti.*,u.full_name,u.email,st.student_code,sm.semester_name,sm.school_year,cl.class_name,m.major_name $base ORDER BY sm.school_year DESC,sm.semester_name DESC,u.full_name LIMIT ? OFFSET ?");
if ($ds) {
    $dp = $params; $dt = $types.'ii'; $dp[] = $perPage; $dp[] = $offset;
    $ds->bind_param($dt, ...$dp); $ds->execute(); $invoices = $ds->get_result(); $ds->close();
}

if (!function_exists('fmtVND')) { function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; } }
function tBadge($s) {
    $m = ['unpaid'=>['warning','Chưa đóng'],'partial'=>['info','Đóng một phần'],'paid'=>['success','Đã đóng'],'overdue'=>['danger','Quá hạn'],'waived'=>['secondary','Miễn học phí']];
    $v = $m[$s] ?? ['secondary',$s];
    return '<span class="badge bg-'.$v[0].'">'.$v[1].'</span>';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

// ── Tự động tạo bảng nếu chưa có ─────────────────────────────────────────────
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

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Tạo hóa đơn hàng loạt
    if ($action === 'generate_invoices') {
        $semester_id = intval($_POST['semester_id'] ?? 0);
        $due_date    = trim($_POST['due_date'] ?? '');
        $created = 0; $skipped = 0;
        if ($semester_id) {
            $sql = "SELECT ss.student_id,
                        SUM(sub.credits) AS total_credits,
                        m.tuition_per_credit AS unit_price
                    FROM student_subjects ss
                    JOIN course_sections cs ON ss.course_section_id = cs.id
                    JOIN subjects sub ON cs.subject_id = sub.id
                    JOIN students st ON ss.student_id = st.id
                    JOIN classes cl ON st.class_id = cl.id
                    JOIN majors m ON cl.major_id = m.id
                    WHERE cs.semester_id = ? AND ss.status != 'cancelled'
                    GROUP BY ss.student_id, m.tuition_per_credit";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $semester_id);
            $stmt->execute();
            $rows = $stmt->get_result();
            $stmt->close();
            $admin_id = (int)($_SESSION['user_id'] ?? 0);
            while ($row = $rows->fetch_assoc()) {
                $sid        = (int)$row['student_id'];
                $credits    = (int)$row['total_credits'];
                $unit_price = (float)($row['unit_price'] ?? 0);
                $gross      = $credits * $unit_price;
                $net        = $gross;
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
        $qs = http_build_query(array_filter(['semester_id' => $_POST['semester_id'] ?? '']));
        header('Location: tuition.php' . ($qs ? '?' . $qs : ''));
        exit();
    }

    // 2. Ghi nhận thanh toán
    if ($action === 'record_payment') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $amount     = floatval($_POST['amount'] ?? 0);
        $method     = trim($_POST['method'] ?? 'cash');
        $reference  = trim($_POST['reference'] ?? '');
        $note       = trim($_POST['note'] ?? '');
        $admin_id   = (int)($_SESSION['user_id'] ?? 0);
        if ($invoice_id && $amount > 0) {
            $inv = $conn->prepare("SELECT * FROM tuition_invoices WHERE id=?");
            $inv->bind_param('i', $invoice_id);
            $inv->execute();
            $invoice = $inv->get_result()->fetch_assoc();
            $inv->close();
            if ($invoice) {
                $pay = $conn->prepare("INSERT INTO tuition_payments (invoice_id, amount, method, reference, note, paid_by) VALUES (?,?,?,?,?,?)");
                $pay->bind_param('idsssi', $invoice_id, $amount, $method, $reference, $note, $admin_id);
                $pay->execute();
                $pay->close();
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
                $upd->execute(); $upd->close();
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

    // 3. Cập nhật miễn giảm
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
                $upd->execute(); $upd->close();
                $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật miễn giảm thành công!'];
            }
        }
        $qs = http_build_query(array_filter(['semester_id' => $_GET['semester_id'] ?? '', 'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '']));
        header('Location: tuition.php' . ($qs ? '?' . $qs : ''));
        exit();
    }

    // 4. Cập nhật hạn đóng
    if ($action === 'update_due_date') {
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $due_date   = trim($_POST['due_date'] ?? '');
        if ($invoice_id) {
            $upd = $conn->prepare("UPDATE tuition_invoices SET due_date=?, updated_at=NOW() WHERE id=?");
            $upd->bind_param('si', $due_date, $invoice_id);
            $upd->execute(); $upd->close();
            $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật hạn đóng học phí thành công!'];
        }
        $qs = http_build_query(array_filter(['semester_id' => $_GET['semester_id'] ?? '', 'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '']));
        header('Location: tuition.php' . ($qs ? '?' . $qs : ''));
        exit();
    }

    // 5. Đánh dấu quá hạn
    if ($action === 'mark_overdue') {
        $conn->query("UPDATE tuition_invoices SET status='overdue', updated_at=NOW()
            WHERE status IN ('unpaid','partial') AND due_date IS NOT NULL AND due_date < CURDATE()");
        $affected = $conn->affected_rows;
        $_SESSION['_flash'] = ['type' => 'success', 'message' => "Đã đánh dấu $affected hóa đơn quá hạn."];
        $qs = http_build_query(array_filter(['semester_id' => $_GET['semester_id'] ?? '', 'status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '']));
        header('Location: tuition.php' . ($qs ? '?' . $qs : ''));
        exit();
    }
}

// ── AJAX: xem lịch sử thanh toán ─────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'view_payments') {
    header('Content-Type: application/json; charset=utf-8');
    $invoice_id = intval($_GET['invoice_id'] ?? 0);
    $payments = [];
    if ($invoice_id) {
        $res = $conn->prepare("SELECT tp.*, u.full_name AS paid_by_name
            FROM tuition_payments tp
            LEFT JOIN users u ON tp.paid_by = u.id
            WHERE tp.invoice_id = ?
            ORDER BY tp.paid_at DESC");
        $res->bind_param('i', $invoice_id);
        $res->execute();
        $r = $res->get_result();
        while ($row = $r->fetch_assoc()) $payments[] = $row;
        $res->close();
    }
    echo json_encode(['payments' => $payments]);
    exit();
}

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
$_statsRes = $conn->query("
    SELECT
        COUNT(*) AS total_invoices,
        COALESCE(SUM(status='paid'),0)    AS total_paid,
        COALESCE(SUM(status='unpaid'),0)  AS total_unpaid,
        COALESCE(SUM(status='partial'),0) AS total_partial,
        COALESCE(SUM(status='overdue'),0) AS total_overdue,
        COALESCE(SUM(status='waived'),0)  AS total_waived,
        COALESCE(SUM(net_amount),0)       AS sum_net,
        COALESCE(SUM(paid_amount),0)      AS sum_paid,
        COALESCE(SUM(net_amount - paid_amount),0) AS sum_remaining
    FROM tuition_invoices ti $stats_where
");
$_statsRow = ($_statsRes instanceof mysqli_result) ? $_statsRes->fetch_assoc() : null;
$_tuitionStats = array_merge([
    'total_invoices' => 0, 'total_paid' => 0, 'total_unpaid' => 0,
    'total_partial'  => 0, 'total_overdue' => 0, 'total_waived' => 0,
    'sum_net' => 0, 'sum_paid' => 0, 'sum_remaining' => 0,
], $_statsRow ?? []);

// ── INVOICE LIST ──────────────────────────────────────────────────────────────
$conditions = []; $params = []; $types = '';
if ($filter_semester) { $conditions[] = 'ti.semester_id = ?'; $params[] = $filter_semester; $types .= 'i'; }
if ($filter_status)   { $conditions[] = 'ti.status = ?';      $params[] = $filter_status;   $types .= 's'; }
if ($search) {
    $like = "%$search%";
    $conditions[] = '(u.full_name LIKE ? OR st.student_code LIKE ? OR u.email LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$base_sql = "FROM tuition_invoices ti
    JOIN students st ON ti.student_id = st.id
    JOIN users u ON st.user_id = u.id
    JOIN semesters sm ON ti.semester_id = sm.id
    LEFT JOIN classes cl ON st.class_id = cl.id
    LEFT JOIN majors m ON cl.major_id = m.id
    $where_sql";

$count_stmt = $conn->prepare("SELECT COUNT(*) AS c $base_sql");
$total = 0;
if ($count_stmt) {
    if ($types) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total = (int)($count_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $count_stmt->close();
}
$totalPages = max(1, (int)ceil($total / $perPage));

$invoices = null;
$data_stmt = $conn->prepare("SELECT ti.*, u.full_name, u.email, st.student_code,
    sm.semester_name, sm.school_year, cl.class_name, m.major_name
    $base_sql ORDER BY ti.created_at DESC LIMIT ? OFFSET ?");
if ($data_stmt) {
    $dp = $params; $dt = $types . 'ii'; $dp[] = $perPage; $dp[] = $offset;
    $data_stmt->bind_param($dt, ...$dp);
    $data_stmt->execute();
    $invoices = $data_stmt->get_result();
    $data_stmt->close();
}

if (!function_exists('fmtVND')) {
    function fmtVND($n) { return number_format(floatval($n), 0, ',', '.') . ' ₫'; }
}
if (!function_exists('statusBadge')) {
    function statusBadge($status) {
        $map = [
            'unpaid'  => ['warning',   'Chưa đóng'],
            'partial' => ['info',      'Đóng một phần'],
            'paid'    => ['success',   'Đã đóng'],
            'overdue' => ['danger',    'Quá hạn'],
            'waived'  => ['secondary', 'Miễn học phí'],
        ];
        $s = $map[$status] ?? ['secondary', $status];
        return '<span class="badge bg-' . $s[0] . '">' . $s[1] . '</span>';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-cash-coin me-2 text-gold"></i>Quản lý Học phí</span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="mark_overdue">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Đánh dấu tất cả hóa đơn quá hạn?')">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>Đánh dấu quá hạn
            </button>
        </form>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
    </div>
</div>
<div class="admin-content">

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show">
    <i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i>
    <?php echo $flash['message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['bi-receipt','text-navy','var(--navy)','Tổng hóa đơn', number_format($_tuitionStats['total_invoices'])],
        ['bi-check-circle-fill','text-success','#28a745','Đã đóng', number_format($_tuitionStats['total_paid'])],
        ['bi-hourglass-split','text-warning','#ffc107','Chưa đóng', number_format($_tuitionStats['total_unpaid'])],
        ['bi-exclamation-triangle-fill','text-danger','#dc3545','Quá hạn', number_format($_tuitionStats['total_overdue'])],
        ['bi-cash-stack','text-gold','var(--gold)','Đã thu', fmtVND($_tuitionStats['sum_paid'])],
        ['bi-wallet2','','#6f42c1','Còn lại', fmtVND($_tuitionStats['sum_remaining'])],
    ];
    foreach ($statCards as [$icon,$cls,$color,$lbl,$val]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 border-0 shadow-sm" style="border-left:4px solid <?php echo $color; ?> !important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi <?php echo $icon; ?> fs-4 <?php echo $cls; ?>" style="<?php echo $cls?'':'color:'.$color; ?>"></i>
                    <span class="text-muted small"><?php echo $lbl; ?></span>
                </div>
                <div class="fw-bold" style="font-size:1.1rem;<?php echo $cls?'':'color:'.$color; ?>"><?php echo $val; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- FILTER -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-funnel me-2"></i>Lọc danh sách</span>
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
                    <?php if ($semesters) { $semesters->data_seek(0); while ($sem = $semesters->fetch_assoc()): ?>
                    <option value="<?php echo $sem['id']; ?>" <?php echo $filter_sem==$sem['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($sem['semester_name'].' '.$sem['school_year']); ?>
                    </option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <?php foreach (['unpaid'=>'Chưa đóng','partial'=>'Đóng một phần','paid'=>'Đã đóng','overdue'=>'Quá hạn','waived'=>'Miễn học phí'] as $v=>$l): ?>
                    <option value="<?php echo $v; ?>" <?php echo $filter_status===$v?'selected':''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" name="search" class="form-control" placeholder="Tên, mã SV, email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-navy flex-fill"><i class="bi bi-search me-1"></i>Lọc</button>
                <?php if ($filter_sem||$filter_status||$search): ?>
                <a href="tuition.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Danh sách hóa đơn học phí
            <span class="badge bg-gold text-dark ms-2"><?php echo number_format($total); ?></span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle" style="font-size:.85rem;">
                <thead><tr>
                    <th>#</th><th>Sinh viên</th><th>Học kỳ</th>
                    <th class="text-center">TC</th>
                    <th class="text-end">Học phí gốc</th>
                    <th class="text-end">Miễn giảm</th>
                    <th class="text-end">Phải đóng</th>
                    <th class="text-end">Đã đóng</th>
                    <th class="text-end text-danger">Còn nợ</th>
                    <th class="text-center">Hạn đóng</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center" style="width:160px;">Thao tác</th>
                </tr></thead>
                <tbody>
                <?php if ($invoices && $invoices->num_rows > 0):
                    $idx = $offset + 1;
                    while ($inv = $invoices->fetch_assoc()):
                        $remaining = max(0, $inv['net_amount'] - $inv['paid_amount']);
                ?>
                <tr class="<?php echo $inv['status']==='overdue'?'table-danger':''; ?>">
                    <td class="text-muted"><?php echo $idx++; ?></td>
                    <td>
                        <div class="fw-bold text-navy"><?php echo htmlspecialchars($inv['student_code']); ?></div>
                        <div><?php echo htmlspecialchars($inv['full_name']); ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?php echo htmlspecialchars($inv['class_name']??''); ?> &bull; <?php echo htmlspecialchars($inv['major_name']??''); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($inv['semester_name'].' '.$inv['school_year']); ?></td>
                    <td class="text-center fw-bold"><?php echo $inv['total_credits']; ?></td>
                    <td class="text-end"><?php echo fmtVND($inv['gross_amount']); ?></td>
                    <td class="text-end text-success"><?php echo $inv['discount']>0 ? '-'.fmtVND($inv['discount']) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-end fw-bold"><?php echo fmtVND($inv['net_amount']); ?></td>
                    <td class="text-end text-success"><?php echo fmtVND($inv['paid_amount']); ?></td>
                    <td class="text-end fw-bold text-danger"><?php echo $remaining > 0 ? fmtVND($remaining) : '<span class="text-success">—</span>'; ?></td>
                    <td class="text-center">
                        <?php if ($inv['due_date']): ?>
                        <span class="<?php echo $inv['status']==='overdue'?'text-danger fw-bold':''; ?>">
                            <?php echo date('d/m/Y', strtotime($inv['due_date'])); ?>
                        </span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo tBadge($inv['status']); ?></td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                            <?php if (!in_array($inv['status'],['paid','waived'])): ?>
                            <button class="btn btn-sm btn-gold" title="Ghi nhận thanh toán"
                                data-bs-toggle="modal" data-bs-target="#payModal"
                                data-id="<?php echo $inv['id']; ?>"
                                data-name="<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>"
                                data-code="<?php echo htmlspecialchars($inv['student_code'],ENT_QUOTES); ?>"
                                data-net="<?php echo $inv['net_amount']; ?>"
                                data-paid="<?php echo $inv['paid_amount']; ?>"
                                data-remaining="<?php echo $remaining; ?>">
                                <i class="bi bi-cash-coin"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-primary" title="Miễn giảm"
                                data-bs-toggle="modal" data-bs-target="#discModal"
                                data-id="<?php echo $inv['id']; ?>"
                                data-name="<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>"
                                data-gross="<?php echo $inv['gross_amount']; ?>"
                                data-discount="<?php echo $inv['discount']; ?>"
                                data-note="<?php echo htmlspecialchars($inv['note']??'',ENT_QUOTES); ?>">
                                <i class="bi bi-percent"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" title="Hạn đóng"
                                data-bs-toggle="modal" data-bs-target="#dueModal"
                                data-id="<?php echo $inv['id']; ?>"
                                data-name="<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>"
                                data-due="<?php echo $inv['due_date']??''; ?>">
                                <i class="bi bi-calendar-event"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info" title="Lịch sử thanh toán"
                                onclick="viewPayments(<?php echo $inv['id']; ?>,'<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="12" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có hóa đơn nào
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="px-3 py-2 border-top">
            <nav><ul class="pagination justify-content-center mb-0 pagination-sm">
                <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                <li class="page-item <?php echo $p===$page?'active':''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$p])); ?>"><?php echo $p; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /.admin-content -->
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div><!-- /.admin-main -->

<!-- MODAL: Tạo hóa đơn -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-lightning-fill me-2"></i>Tạo hóa đơn hàng loạt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="generate_invoices">
            <div class="modal-body">
                <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Tự động tính học phí = tín chỉ đã đăng ký × đơn giá/TC của ngành. Bỏ qua sinh viên đã có hóa đơn.</div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Học kỳ <span class="text-danger">*</span></label>
                    <select name="semester_id" class="form-select" required>
                        <option value="">-- Chọn học kỳ --</option>
                        <?php if ($semesters) { $semesters->data_seek(0); while ($sem = $semesters->fetch_assoc()): ?>
                        <option value="<?php echo $sem['id']; ?>" <?php echo $filter_sem==$sem['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($sem['semester_name'].' '.$sem['school_year']); ?>
                        </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Hạn đóng học phí</label>
                    <input type="date" name="due_date" class="form-control">
                    <div class="form-text">Để trống nếu chưa xác định.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-gold"><i class="bi bi-lightning-fill me-1"></i>Tạo hóa đơn</button>
            </div>
        </form>
    </div></div>
</div>

<!-- MODAL: Ghi nhận thanh toán -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Ghi nhận Thanh toán</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="invoice_id" id="payId">
            <div class="modal-body">
                <div class="alert alert-light border py-2 px-3 mb-3">
                    <div class="fw-bold" id="payName"></div>
                    <div class="small text-muted" id="payCode"></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4"><div class="text-muted small">Phải đóng</div><div class="fw-bold text-navy" id="payNet"></div></div>
                    <div class="col-4"><div class="text-muted small">Đã đóng</div><div class="fw-bold text-success" id="payPaid"></div></div>
                    <div class="col-4"><div class="text-muted small">Còn lại</div><div class="fw-bold text-danger" id="payRem"></div></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Số tiền thanh toán <span class="text-danger">*</span></label>
                    <input type="number" name="amount" id="payAmount" class="form-control" min="1000" step="1000" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Hình thức</label>
                    <select name="method" class="form-select">
                        <option value="cash">Tiền mặt</option>
                        <option value="bank_transfer">Chuyển khoản</option>
                        <option value="online">Online</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Mã giao dịch / Biên lai</label>
                    <input type="text" name="reference" class="form-control" placeholder="VD: TT20260101001">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Ghi chú</label>
                    <textarea name="note" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu thanh toán</button>
            </div>
        </form>
    </div></div>
</div>

<!-- MODAL: Miễn giảm -->
<div class="modal fade" id="discModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-percent me-2"></i>Cập nhật Miễn giảm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="update_discount">
            <input type="hidden" name="invoice_id" id="discId">
            <div class="modal-body">
                <div class="fw-bold mb-1" id="discName"></div>
                <div class="text-muted small mb-3">Học phí gốc: <span class="fw-bold text-navy" id="discGross"></span></div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Số tiền miễn giảm (₫)</label>
                    <input type="number" name="discount" id="discDiscount" class="form-control" min="0" step="1000" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Lý do miễn giảm</label>
                    <textarea name="note" id="discNote" class="form-control" rows="2" placeholder="VD: Học bổng khuyến khích học tập..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Cập nhật</button>
            </div>
        </form>
    </div></div>
</div>

<!-- MODAL: Hạn đóng -->
<div class="modal fade" id="dueModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Hạn đóng học phí</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="update_due_date">
            <input type="hidden" name="invoice_id" id="dueId">
            <div class="modal-body">
                <div class="fw-bold mb-3" id="dueName"></div>
                <input type="date" name="due_date" id="dueDate" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-navy btn-sm"><i class="bi bi-save me-1"></i>Lưu</button>
            </div>
        </form>
    </div></div>
</div>

<!-- MODAL: Lịch sử thanh toán -->
<div class="modal fade" id="histModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Lịch sử thanh toán — <span id="histName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="histBody">
            <div class="text-center py-4"><div class="spinner-border text-navy"></div></div>
        </div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
function fmtVND(n) { return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(n); }

document.getElementById('payModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('payId').value    = b.dataset.id;
    document.getElementById('payName').textContent = b.dataset.name;
    document.getElementById('payCode').textContent = b.dataset.code;
    const net = parseFloat(b.dataset.net)||0, paid = parseFloat(b.dataset.paid)||0, rem = parseFloat(b.dataset.remaining)||0;
    document.getElementById('payNet').textContent  = fmtVND(net);
    document.getElementById('payPaid').textContent = fmtVND(paid);
    document.getElementById('payRem').textContent  = fmtVND(rem);
    document.getElementById('payAmount').value = rem > 0 ? rem : '';
    document.getElementById('payAmount').max   = rem > 0 ? rem : '';
});

document.getElementById('discModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('discId').value       = b.dataset.id;
    document.getElementById('discName').textContent = b.dataset.name;
    document.getElementById('discGross').textContent = fmtVND(parseFloat(b.dataset.gross)||0);
    document.getElementById('discDiscount').value  = b.dataset.discount || 0;
    document.getElementById('discNote').value      = b.dataset.note || '';
});

document.getElementById('dueModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('dueId').value   = b.dataset.id;
    document.getElementById('dueName').textContent = b.dataset.name;
    document.getElementById('dueDate').value = b.dataset.due || '';
});

function viewPayments(invoiceId, name) {
    document.getElementById('histName').textContent = name;
    document.getElementById('histBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-navy"></div></div>';
    new bootstrap.Modal(document.getElementById('histModal')).show();
    fetch('tuition.php?action=view_payments&invoice_id=' + invoiceId, {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            const pays = data.payments || [];
            if (!pays.length) {
                document.getElementById('histBody').innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có lịch sử thanh toán</div>';
                return;
            }
            const methodMap = {cash:'Tiền mặt',bank_transfer:'Chuyển khoản',online:'Online',other:'Khác'};
            let html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Thời gian</th><th class="text-end">Số tiền</th><th>Hình thức</th><th>Mã giao dịch</th><th>Người ghi</th><th>Ghi chú</th></tr></thead><tbody>';
            pays.forEach(p => {
                html += `<tr>
                    <td class="small">${new Date(p.paid_at).toLocaleString('vi-VN')}</td>
                    <td class="text-end fw-bold text-success">${fmtVND(parseFloat(p.amount))}</td>
                    <td class="small">${methodMap[p.method]||p.method}</td>
                    <td class="small text-muted">${p.reference||'—'}</td>
                    <td class="small">${p.paid_by_name||'—'}</td>
                    <td class="small text-muted">${p.note||'—'}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('histBody').innerHTML = html;
        })
        .catch(() => { document.getElementById('histBody').innerHTML = '<div class="alert alert-danger">Lỗi tải dữ liệu.</div>'; });
}
</script>
</body></html>
