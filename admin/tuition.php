<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
requireRole('admin');
$pageTitle = 'Quản lý Học phí';
// TUITION_V3

// ── Auto-create tables ────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `tuition_periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `semester_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `open_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `status` ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    `note` TEXT NULL,
    `created_by` INT NULL,
    `published_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_semester` (`semester_id`),
    INDEX(`status`), INDEX(`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `tuition_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `total_credits` INT NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gross_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `discount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `net_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `paid_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `status` ENUM('draft','unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'draft',
    `note` TEXT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ps` (`period_id`,`student_id`),
    INDEX(`semester_id`), INDEX(`student_id`), INDEX(`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Migrate bảng cũ nếu thiếu cột ───────────────────────────────────────────
// Thêm period_id nếu chưa có (schema cũ không có cột này)
$chkCol = $conn->query("SHOW COLUMNS FROM `tuition_invoices` LIKE 'period_id'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `tuition_invoices`
        ADD COLUMN `period_id` INT NOT NULL DEFAULT 0 AFTER `id`,
        ADD COLUMN `status_new` ENUM('draft','unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'draft'");
    // Đổi tên cột status cũ nếu cần (bỏ qua nếu đã có enum đúng)
    $conn->query("ALTER TABLE `tuition_invoices` MODIFY COLUMN `status`
        ENUM('draft','unpaid','partial','paid','overdue','waived') NOT NULL DEFAULT 'draft'");
    $conn->query("ALTER TABLE `tuition_invoices` DROP COLUMN IF EXISTS `status_new`");
    // Xóa UNIQUE KEY cũ nếu có
    $conn->query("ALTER TABLE `tuition_invoices` DROP INDEX IF EXISTS `uq_student_semester`");
    // Xóa dữ liệu cũ không có period_id (không thể dùng được)
    $conn->query("DELETE FROM `tuition_invoices` WHERE period_id = 0");
    $conn->query("DELETE FROM `tuition_payments` WHERE invoice_id NOT IN (SELECT id FROM tuition_invoices)");
}
// Thêm UNIQUE KEY mới nếu chưa có
$chkIdx = $conn->query("SHOW INDEX FROM `tuition_invoices` WHERE Key_name='uq_ps'");
if ($chkIdx && $chkIdx->num_rows === 0) {
    $conn->query("ALTER TABLE `tuition_invoices` ADD UNIQUE KEY `uq_ps` (`period_id`,`student_id`)");
}
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

// ── AJAX ──────────────────────────────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxAction = $_GET['ajax'];

    if ($ajaxAction === 'view_payments') {
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

    if ($ajaxAction === 'preview_invoices') {
        $pid = intval($_GET['period_id'] ?? 0);
        if (!$pid) { echo json_encode(['error' => 'Thiếu period_id']); exit(); }
        $period = $conn->query("SELECT * FROM tuition_periods WHERE id=$pid")->fetch_assoc();
        if (!$period) { echo json_encode(['error' => 'Không tìm thấy đợt thu']); exit(); }
        $semId = (int)$period['semester_id'];
        $res = $conn->query("
            SELECT ss.student_id, u.full_name, st.student_code,
                SUM(sub.credits) AS total_credits,
                m.tuition_per_credit AS unit_price,
                SUM(sub.credits) * m.tuition_per_credit AS gross_amount
            FROM student_subjects ss
            JOIN course_sections cs ON ss.course_section_id=cs.id
            JOIN subjects sub ON cs.subject_id=sub.id
            JOIN students st ON ss.student_id=st.id
            JOIN users u ON st.user_id=u.id
            JOIN classes cl ON st.class_id=cl.id
            JOIN majors m ON cl.major_id=m.id
            WHERE cs.semester_id=$semId AND ss.status!='cancelled'
            GROUP BY ss.student_id, m.tuition_per_credit
            ORDER BY u.full_name
        ");
        $rows = [];
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['students' => $rows, 'count' => count($rows)]); exit();
    }

    echo json_encode(['error' => 'Unknown action']); exit();
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $aid = (int)($_SESSION['user_id'] ?? 0);

    // 1. Tạo đợt thu học phí (draft)
    if ($action === 'create_period') {
        $semId    = intval($_POST['semester_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $openDate = trim($_POST['open_date'] ?? '');
        $dueDate  = trim($_POST['due_date'] ?? '');
        $note     = trim($_POST['note'] ?? '');
        if (!$semId || !$title || !$openDate || !$dueDate) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng điền đầy đủ thông tin.'];
        } else {
            $chk = $conn->query("SELECT id FROM tuition_periods WHERE semester_id=$semId");
            if ($chk && $chk->num_rows > 0) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>'Học kỳ này đã có đợt thu học phí.'];
            } else {
                $demoContext = function_exists('academicPolicySemesterDemoContext') ? academicPolicySemesterDemoContext($conn, $semId) : ['data_mode'=>'system','demo_batch_id'=>''];
                $ins = $conn->prepare("INSERT INTO tuition_periods (semester_id,title,open_date,due_date,status,data_mode,demo_batch_id,note,created_by) VALUES (?,?,?,?,'draft',?,?,?,?)");
                $ins->bind_param('issssssi', $semId, $title, $openDate, $dueDate, $demoContext['data_mode'], $demoContext['demo_batch_id'], $note, $aid);
                if ($ins->execute()) {
                    $pid = $conn->insert_id;
                    // Tự động tạo hóa đơn draft
                    $res = $conn->query("
                        SELECT ss.student_id, SUM(sub.credits) AS tc, m.tuition_per_credit AS up,
                               st.data_mode, st.demo_batch_id
                        FROM student_subjects ss
                        JOIN course_sections cs ON ss.course_section_id=cs.id
                        JOIN subjects sub ON cs.subject_id=sub.id
                        JOIN students st ON ss.student_id=st.id
                        JOIN classes cl ON st.class_id=cl.id
                        JOIN majors m ON cl.major_id=m.id
                        WHERE cs.semester_id=$semId AND ss.status!='cancelled'
                        GROUP BY ss.student_id, m.tuition_per_credit, st.data_mode, st.demo_batch_id");
                    $created = 0;
                    if ($res) while ($row = $res->fetch_assoc()) {
                        $sid = (int)$row['student_id'];
                        $tc  = (int)$row['tc'];
                        $up  = (float)$row['up'];
                        $gross = $tc * $up;
                        $dataMode = (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
                        $demoBatchId = (string)($row['demo_batch_id'] ?? '');
                        $ins2 = $conn->prepare("INSERT IGNORE INTO tuition_invoices (period_id,student_id,semester_id,total_credits,unit_price,gross_amount,discount,net_amount,paid_amount,status,data_mode,demo_batch_id,created_by) VALUES (?,?,?,?,?,?,0,?,0,'draft',?,?,?)");
                        $ins2->bind_param('iiiidddssi', $pid, $sid, $semId, $tc, $up, $gross, $gross, $dataMode, $demoBatchId, $aid);
                        if ($ins2->execute()) $created++;
                        $ins2->close();
                    }
                    $_SESSION['_flash'] = ['type'=>'success','message'=>"Tạo đợt thu thành công! Đã tạo $created hóa đơn nháp. Xem xét và xác nhận công bố."];
                } else {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Lỗi: '.$conn->error];
                }
                $ins->close();
            }
        }
        header('Location: tuition.php'); exit();
    }

    // 2. Cập nhật đợt thu
    if ($action === 'update_period') {
        $pid      = intval($_POST['period_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $openDate = trim($_POST['open_date'] ?? '');
        $dueDate  = trim($_POST['due_date'] ?? '');
        $note     = trim($_POST['note'] ?? '');
        if ($pid && $title && $openDate && $dueDate) {
            $upd = $conn->prepare("UPDATE tuition_periods SET title=?,open_date=?,due_date=?,note=?,updated_at=NOW() WHERE id=? AND status='draft'");
            $upd->bind_param('ssssi', $title, $openDate, $dueDate, $note, $pid);
            $upd->execute(); $upd->close();
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Cập nhật đợt thu thành công!'];
        }
        header('Location: tuition.php?period_id='.$pid); exit();
    }

    // 3. Tái tạo hóa đơn (khi SV đăng ký thêm môn sau khi tạo đợt)
    if ($action === 'regenerate_invoices') {
        $pid = intval($_POST['period_id'] ?? 0);
        if ($pid) {
            $period = $conn->query("SELECT * FROM tuition_periods WHERE id=$pid AND status='draft'")->fetch_assoc();
            if ($period) {
                $semId = (int)$period['semester_id'];
                $res = $conn->query("
                    SELECT ss.student_id, SUM(sub.credits) AS tc, m.tuition_per_credit AS up,
                           st.data_mode, st.demo_batch_id
                    FROM student_subjects ss
                    JOIN course_sections cs ON ss.course_section_id=cs.id
                    JOIN subjects sub ON cs.subject_id=sub.id
                    JOIN students st ON ss.student_id=st.id
                    JOIN classes cl ON st.class_id=cl.id
                    JOIN majors m ON cl.major_id=m.id
                    WHERE cs.semester_id=$semId AND ss.status!='cancelled'
                    GROUP BY ss.student_id, m.tuition_per_credit, st.data_mode, st.demo_batch_id");
                $updated = $created = 0;
                if ($res) while ($row = $res->fetch_assoc()) {
                    $sid = (int)$row['student_id'];
                    $tc  = (int)$row['tc'];
                    $up  = (float)$row['up'];
                    $gross = $tc * $up;
                    $dataMode = (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
                    $demoBatchId = (string)($row['demo_batch_id'] ?? '');
                    $chk = $conn->query("SELECT id FROM tuition_invoices WHERE period_id=$pid AND student_id=$sid");
                    if ($chk && $chk->num_rows > 0) {
                        $stmtInv = $conn->prepare("UPDATE tuition_invoices SET total_credits=?,unit_price=?,gross_amount=?,net_amount=?,data_mode=?,demo_batch_id=?,updated_at=NOW() WHERE period_id=? AND student_id=? AND status='draft'");
                        $stmtInv->bind_param('idddssii', $tc, $up, $gross, $gross, $dataMode, $demoBatchId, $pid, $sid);
                        $stmtInv->execute();
                        $stmtInv->close();
                        $updated++;
                    } else {
                        $ins2 = $conn->prepare("INSERT INTO tuition_invoices (period_id,student_id,semester_id,total_credits,unit_price,gross_amount,discount,net_amount,paid_amount,status,data_mode,demo_batch_id,created_by) VALUES (?,?,?,?,?,?,0,?,0,'draft',?,?,?)");
                        $ins2->bind_param('iiiidddssi', $pid, $sid, $semId, $tc, $up, $gross, $gross, $dataMode, $demoBatchId, $aid);
                        if ($ins2->execute()) $created++;
                        $ins2->close();
                    }
                }
                $_SESSION['_flash'] = ['type'=>'success','message'=>"Đã cập nhật $updated, tạo mới $created hóa đơn nháp."];
            }
        }
        header('Location: tuition.php?period_id='.$pid); exit();
    }

    // 4. Xác nhận công bố đợt thu
    if ($action === 'publish_period') {
        $pid = intval($_POST['period_id'] ?? 0);
        if ($pid) {
            $conn->query("UPDATE tuition_periods SET status='published',published_at=NOW(),updated_at=NOW() WHERE id=$pid AND status='draft'");
            // Chuyển tất cả hóa đơn draft → unpaid
            $conn->query("UPDATE tuition_invoices SET status='unpaid',updated_at=NOW() WHERE period_id=$pid AND status='draft'");
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Đã công bố đợt thu học phí! Sinh viên có thể xem hóa đơn.'];
        }
        header('Location: tuition.php?period_id='.$pid); exit();
    }

    // 5. Đóng đợt thu
    if ($action === 'close_period') {
        $pid = intval($_POST['period_id'] ?? 0);
        if ($pid) {
            $conn->query("UPDATE tuition_periods SET status='closed',updated_at=NOW() WHERE id=$pid");
            // Đánh dấu quá hạn
            $conn->query("UPDATE tuition_invoices SET status='overdue',updated_at=NOW() WHERE period_id=$pid AND status IN ('unpaid','partial')");
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Đã đóng đợt thu. Hóa đơn chưa đóng được đánh dấu quá hạn.'];
        }
        header('Location: tuition.php?period_id='.$pid); exit();
    }

    // 6. Ghi nhận thanh toán
    if ($action === 'record_payment') {
        $iid    = intval($_POST['invoice_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? 'cash');
        $ref    = trim($_POST['reference'] ?? '');
        $note   = trim($_POST['note'] ?? '');
        $pid    = intval($_POST['period_id'] ?? 0);
        if ($iid && $amount > 0) {
            $inv = $conn->query("SELECT * FROM tuition_invoices WHERE id=$iid")->fetch_assoc();
            if ($inv) {
                $dataMode = (($inv['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
                $demoBatchId = (string)($inv['demo_batch_id'] ?? '');
                $pay = $conn->prepare("INSERT INTO tuition_payments (invoice_id,amount,method,reference,note,data_mode,demo_batch_id,paid_by) VALUES (?,?,?,?,?,?,?,?)");
                $pay->bind_param('idsssssi', $iid, $amount, $method, $ref, $note, $dataMode, $demoBatchId, $aid);
                $pay->execute(); $pay->close();
                $newPaid = $inv['paid_amount'] + $amount;
                $net = $inv['net_amount'];
                $st = $inv['status']==='waived' ? 'waived' : ($newPaid>=$net ? 'paid' : ($newPaid>0 ? 'partial' : 'unpaid'));
                $conn->query("UPDATE tuition_invoices SET paid_amount=$newPaid,status='$st',updated_at=NOW() WHERE id=$iid");
                $_SESSION['_flash'] = ['type'=>'success','message'=>'Ghi nhận thanh toán thành công!'];
            }
        }
        header('Location: tuition.php?period_id='.$pid); exit();
    }

    // 7. Cập nhật miễn giảm
    if ($action === 'update_discount') {
        $iid      = intval($_POST['invoice_id'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $note     = trim($_POST['note'] ?? '');
        $pid      = intval($_POST['period_id'] ?? 0);
        if ($iid) {
            $inv = $conn->query("SELECT gross_amount,paid_amount,status FROM tuition_invoices WHERE id=$iid")->fetch_assoc();
            if ($inv) {
                $net = max(0, $inv['gross_amount'] - $discount);
                $st  = $inv['status']==='waived' ? 'waived' : ($inv['paid_amount']>=$net&&$net>0 ? 'paid' : ($inv['paid_amount']>0 ? 'partial' : ($inv['status']==='draft'?'draft':'unpaid')));
                $upd = $conn->prepare("UPDATE tuition_invoices SET discount=?,net_amount=?,note=?,status=?,updated_at=NOW() WHERE id=?");
                $upd->bind_param('ddssi', $discount, $net, $note, $st, $iid);
                $upd->execute(); $upd->close();
                $_SESSION['_flash'] = ['type'=>'success','message'=>'Cập nhật miễn giảm thành công!'];
            }
        }
        header('Location: tuition.php?period_id='.$pid); exit();
    }

    // 8. Đánh dấu quá hạn thủ công
    if ($action === 'mark_overdue') {
        $pid = intval($_POST['period_id'] ?? 0);
        $conn->query("UPDATE tuition_invoices SET status='overdue',updated_at=NOW()
            WHERE period_id=$pid AND status IN ('unpaid','partial')");
        $n = $conn->affected_rows;
        $_SESSION['_flash'] = ['type'=>'success','message'=>"Đã đánh dấu $n hóa đơn quá hạn."];
        header('Location: tuition.php?period_id='.$pid); exit();
    }
}

// ── LOAD DATA ─────────────────────────────────────────────────────────────────
$semesters = $conn->query("SELECT * FROM semesters ORDER BY school_year DESC, semester_name DESC");
$periods   = $conn->query("SELECT tp.*, sm.semester_name, sm.school_year,
    (SELECT COUNT(*) FROM tuition_invoices WHERE period_id=tp.id) AS invoice_count,
    (SELECT COUNT(*) FROM tuition_invoices WHERE period_id=tp.id AND status='paid') AS paid_count,
    (SELECT COALESCE(SUM(net_amount),0) FROM tuition_invoices WHERE period_id=tp.id AND status!='draft') AS sum_net,
    (SELECT COALESCE(SUM(paid_amount),0) FROM tuition_invoices WHERE period_id=tp.id) AS sum_paid
    FROM tuition_periods tp
    JOIN semesters sm ON tp.semester_id=sm.id
    ORDER BY tp.created_at DESC");

// Đợt thu đang xem
$currentPeriodId = intval($_GET['period_id'] ?? 0);
$currentPeriod   = null;
$invoices        = null;
$periodStats     = [];

if ($currentPeriodId) {
    $currentPeriod = $conn->query("SELECT tp.*, sm.semester_name, sm.school_year
        FROM tuition_periods tp JOIN semesters sm ON tp.semester_id=sm.id
        WHERE tp.id=$currentPeriodId")->fetch_assoc();

    if ($currentPeriod) {
        // Stats
        $sr = $conn->query("SELECT
            COUNT(*) total, COALESCE(SUM(status='draft'),0) draft,
            COALESCE(SUM(status='unpaid'),0) unpaid, COALESCE(SUM(status='partial'),0) partial,
            COALESCE(SUM(status='paid'),0) paid, COALESCE(SUM(status='overdue'),0) overdue,
            COALESCE(SUM(status='waived'),0) waived,
            COALESCE(SUM(net_amount),0) sum_net, COALESCE(SUM(paid_amount),0) sum_paid,
            COALESCE(SUM(net_amount-paid_amount),0) sum_remaining
            FROM tuition_invoices WHERE period_id=$currentPeriodId");
        $periodStats = $sr ? ($sr->fetch_assoc() ?? []) : [];

        // Filter
        $fStatus = trim($_GET['status'] ?? '');
        $fSearch = trim($_GET['q'] ?? '');
        $perPage = 25; $page = max(1,intval($_GET['page']??1)); $offset = ($page-1)*$perPage;
        $conds = ["ti.period_id=$currentPeriodId"];
        if ($fStatus) $conds[] = "ti.status='".addslashes($fStatus)."'";
        if ($fSearch) {
            $like = addslashes($fSearch);
            $conds[] = "(u.full_name LIKE '%$like%' OR st.student_code LIKE '%$like%')";
        }
        $where = 'WHERE '.implode(' AND ',$conds);
        $total = (int)($conn->query("SELECT COUNT(*) c FROM tuition_invoices ti JOIN students st ON ti.student_id=st.id JOIN users u ON st.user_id=u.id $where")->fetch_assoc()['c'] ?? 0);
        $totalPages = max(1,(int)ceil($total/$perPage));
        $invoices = $conn->query("SELECT ti.*,u.full_name,st.student_code,cl.class_name,m.major_name
            FROM tuition_invoices ti
            JOIN students st ON ti.student_id=st.id
            JOIN users u ON st.user_id=u.id
            LEFT JOIN classes cl ON st.class_id=cl.id
            LEFT JOIN majors m ON cl.major_id=m.id
            $where ORDER BY u.full_name LIMIT $perPage OFFSET $offset");
    }
}

if (!function_exists('fmtVND')) { function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; } }
$statusLabels = ['draft'=>['secondary','Nháp'],'unpaid'=>['warning','Chưa đóng'],'partial'=>['info','Đóng một phần'],'paid'=>['success','Đã đóng'],'overdue'=>['danger','Quá hạn'],'waived'=>['secondary','Miễn']];
$periodStatusLabels = ['draft'=>['secondary','Nháp — chưa công bố'],'published'=>['success','Đã công bố'],'closed'=>['dark','Đã đóng']];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-cash-coin me-2 text-gold"></i>Quản lý Học phí</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
</div>
<div class="admin-content">

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show">
    <i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i>
    <?php echo $flash['message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<div class="row g-4">
<!-- LEFT: Danh sach dot thu -->
<div class="col-lg-4">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-range me-2"></i>Đợt thu học phí</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#createPeriodModal">
            <i class="bi bi-plus-lg me-1"></i>Tạo đợt thu
        </button>
    </div>
    <div class="card-body p-0">
        <?php if ($periods && $periods->num_rows > 0): while ($p = $periods->fetch_assoc()):
            $psl = $periodStatusLabels[$p['status']] ?? ['secondary',$p['status']];
            $isActive = $currentPeriodId == $p['id'];
        ?>
        <a href="tuition.php?period_id=<?php echo $p['id']; ?>"
           class="d-block p-3 border-bottom text-decoration-none <?php echo $isActive?'bg-navy text-white':''; ?>"
           style="transition:background .15s;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold <?php echo $isActive?'text-white':'text-navy'; ?>" style="font-size:.88rem;">
                        <?php echo htmlspecialchars($p['title']); ?>
                    </div>
                    <div class="<?php echo $isActive?'text-white-50':'text-muted'; ?>" style="font-size:.75rem;">
                        <?php echo htmlspecialchars($p['semester_name'].' '.$p['school_year']); ?>
                        &bull; Hạn: <?php echo date('d/m/Y',strtotime($p['due_date'])); ?>
                    </div>
                </div>
                <span class="badge bg-<?php echo $psl[0]; ?> ms-2 flex-shrink-0"><?php echo $psl[1]; ?></span>
            </div>
            <?php if ($p['invoice_count'] > 0): ?>
            <div class="mt-1 d-flex gap-2 flex-wrap" style="font-size:.72rem;">
                <span class="<?php echo $isActive?'text-white-50':'text-muted'; ?>">
                    <?php echo $p['invoice_count']; ?> HĐ &bull;
                    <?php echo $p['paid_count']; ?> đã đóng
                </span>
            </div>
            <?php endif; ?>
        </a>
        <?php endwhile; else: ?>
        <div class="text-center text-muted py-4 small">Chưa có đợt thu nào</div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- RIGHT: Chi tiet dot thu -->
<div class="col-lg-8">
<?php if ($currentPeriod): ?>

<!-- Period header -->
<?php $psl = $periodStatusLabels[$currentPeriod['status']] ?? ['secondary',$currentPeriod['status']]; ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-navy mb-1"><?php echo htmlspecialchars($currentPeriod['title']); ?></h5>
                <div class="text-muted small">
                    Học kỳ: <strong><?php echo htmlspecialchars($currentPeriod['semester_name'].' '.$currentPeriod['school_year']); ?></strong>
                    &bull; Mở: <strong><?php echo date('d/m/Y',strtotime($currentPeriod['open_date'])); ?></strong>
                    &bull; Hạn: <strong><?php echo date('d/m/Y',strtotime($currentPeriod['due_date'])); ?></strong>
                </div>
                <?php if ($currentPeriod['note']): ?>
                <div class="text-muted small mt-1"><?php echo htmlspecialchars($currentPeriod['note']); ?></div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-<?php echo $psl[0]; ?> fs-6"><?php echo $psl[1]; ?></span>
                <?php if ($currentPeriod['status'] === 'draft'): ?>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPeriodModal">
                    <i class="bi bi-pencil me-1"></i>Sửa
                </button>
                <form method="POST" class="d-inline" onsubmit="return confirm('Tái tạo hóa đơn từ dữ liệu đăng ký môn hiện tại?')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="regenerate_invoices">
                    <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-info"><i class="bi bi-arrow-clockwise me-1"></i>Tái tạo HĐ</button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Xác nhận công bố đợt thu? Sinh viên sẽ thấy hóa đơn ngay sau khi công bố.')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="publish_period">
                    <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-megaphone-fill me-1"></i>Công bố</button>
                </form>
                <?php elseif ($currentPeriod['status'] === 'published'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Đóng đợt thu? Hóa đơn chưa đóng sẽ bị đánh dấu quá hạn.')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="close_period">
                    <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-lock-fill me-1"></i>Đóng đợt thu</button>
                </form>
                <form method="POST" class="d-inline">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="mark_overdue">
                    <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Đánh dấu quá hạn?')">
                        <i class="bi bi-exclamation-triangle me-1"></i>Đánh dấu quá hạn
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<?php if (!empty($periodStats)): ?>
<div class="row g-2 mb-3">
    <?php
    $cards = [
        ['bi-receipt','var(--navy)','Tổng HĐ', number_format($periodStats['total']??0)],
        ['bi-check-circle-fill','#28a745','Đã đóng', number_format($periodStats['paid']??0)],
        ['bi-hourglass-split','#ffc107','Chưa đóng', number_format(($periodStats['unpaid']??0)+($periodStats['partial']??0))],
        ['bi-exclamation-triangle-fill','#dc3545','Quá hạn', number_format($periodStats['overdue']??0)],
        ['bi-cash-stack','var(--gold)','Đã thu', fmtVND($periodStats['sum_paid']??0)],
        ['bi-wallet2','#6f42c1','Còn lại', fmtVND($periodStats['sum_remaining']??0)],
    ];
    foreach ($cards as [$icon,$color,$lbl,$val]): ?>
    <div class="col-4 col-md-2">
        <div class="card border-0 shadow-sm h-100" style="border-left:3px solid <?php echo $color; ?> !important;">
            <div class="card-body py-2 px-2 text-center">
                <div class="fw-bold" style="font-size:.9rem;color:<?php echo $color; ?>;"><?php echo $val; ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?php echo $lbl; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filter + Table -->
<div class="card">
    <div class="card-header">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Tìm tên, mã SV..." value="<?php echo htmlspecialchars($fSearch??''); ?>" style="width:180px;">
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Tất cả</option>
                <?php foreach ($statusLabels as $v=>[$c,$l]): ?>
                <option value="<?php echo $v; ?>" <?php echo ($fStatus??'')===$v?'selected':''; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
            <?php if (($fStatus??'')||($fSearch??'')): ?>
            <a href="tuition.php?period_id=<?php echo $currentPeriodId; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle" style="font-size:.83rem;">
                <thead><tr>
                    <th>Sinh viên</th>
                    <th class="text-center">TC</th>
                    <th class="text-end">Phải đóng</th>
                    <th class="text-end">Đã đóng</th>
                    <th class="text-end text-danger">Còn nợ</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center" style="width:120px;">Thao tác</th>
                </tr></thead>
                <tbody>
                <?php if ($invoices && $invoices->num_rows > 0):
                    while ($inv = $invoices->fetch_assoc()):
                        $sl = $statusLabels[$inv['status']] ?? ['secondary',$inv['status']];
                        $rem = max(0, $inv['net_amount'] - $inv['paid_amount']);
                ?>
                <tr class="<?php echo $inv['status']==='overdue'?'table-danger':''; ?>">
                    <td>
                        <div class="fw-bold text-navy"><?php echo htmlspecialchars($inv['student_code']); ?></div>
                        <div><?php echo htmlspecialchars($inv['full_name']); ?></div>
                        <div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($inv['class_name']??''); ?></div>
                    </td>
                    <td class="text-center fw-bold"><?php echo $inv['total_credits']; ?></td>
                    <td class="text-end"><?php echo fmtVND($inv['net_amount']); ?></td>
                    <td class="text-end text-success"><?php echo fmtVND($inv['paid_amount']); ?></td>
                    <td class="text-end fw-bold <?php echo $rem>0?'text-danger':'text-success'; ?>">
                        <?php echo $rem>0 ? fmtVND($rem) : '—'; ?>
                    </td>
                    <td class="text-center"><span class="badge bg-<?php echo $sl[0]; ?>"><?php echo $sl[1]; ?></span></td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <?php if (!in_array($inv['status'],['paid','waived','draft'])): ?>
                            <button class="btn btn-xs btn-gold" title="Ghi nhận thanh toán"
                                style="padding:2px 7px;font-size:.75rem;"
                                data-bs-toggle="modal" data-bs-target="#payModal"
                                data-id="<?php echo $inv['id']; ?>"
                                data-name="<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>"
                                data-net="<?php echo $inv['net_amount']; ?>"
                                data-paid="<?php echo $inv['paid_amount']; ?>"
                                data-rem="<?php echo $rem; ?>">
                                <i class="bi bi-cash-coin"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-xs btn-outline-primary" title="Miễn giảm"
                                style="padding:2px 7px;font-size:.75rem;"
                                data-bs-toggle="modal" data-bs-target="#discModal"
                                data-id="<?php echo $inv['id']; ?>"
                                data-name="<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>"
                                data-gross="<?php echo $inv['gross_amount']; ?>"
                                data-discount="<?php echo $inv['discount']; ?>"
                                data-note="<?php echo htmlspecialchars($inv['note']??'',ENT_QUOTES); ?>">
                                <i class="bi bi-percent"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-info" title="Lịch sử"
                                style="padding:2px 7px;font-size:.75rem;"
                                onclick="viewPayments(<?php echo $inv['id']; ?>,'<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có hóa đơn nào
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (($totalPages??1) > 1): ?>
        <div class="px-3 py-2 border-top">
            <nav><ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($p=max(1,($page??1)-2);$p<=min($totalPages,($page??1)+2);$p++): ?>
                <li class="page-item <?php echo $p===($page??1)?'active':''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$p])); ?>"><?php echo $p; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="card"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-arrow-left-circle fs-2 d-block mb-2"></i>
    Chọn một đợt thu học phí để xem chi tiết
</div></div>
<?php endif; ?>
</div><!-- /.col-lg-8 -->
</div><!-- /.row -->

</div><!-- /.admin-content -->
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div><!-- /.admin-main -->

<!-- ══ MODAL: Tạo đợt thu ══════════════════════════════════════════════════ -->
<div class="modal fade" id="createPeriodModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Tạo đợt thu học phí</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_period">
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Hệ thống sẽ tự động tính học phí = tín chỉ đã đăng ký × đơn giá/TC cho từng sinh viên.
                    Hóa đơn sẽ ở trạng thái <strong>Nháp</strong> cho đến khi bạn xác nhận công bố.
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Học kỳ <span class="text-danger">*</span></label>
                        <select name="semester_id" class="form-select" required>
                            <option value="">-- Chọn học kỳ --</option>
                            <?php if ($semesters) { $semesters->data_seek(0); while ($sem = $semesters->fetch_assoc()): ?>
                            <option value="<?php echo $sem['id']; ?>"><?php echo htmlspecialchars($sem['semester_name'].' '.$sem['school_year']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tiêu đề đợt thu <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="VD: Thu học phí HK1 2025-2026">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Ngày bắt đầu thu (công bố) <span class="text-danger">*</span></label>
                        <input type="date" name="open_date" class="form-control" required>
                        <div class="form-text">Ngày sinh viên bắt đầu thấy hóa đơn.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hạn đóng học phí <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" class="form-control" required>
                        <div class="form-text">Quá hạn này → khóa chức năng sinh viên.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú thêm (nếu có)..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-gold"><i class="bi bi-lightning-fill me-1"></i>Tạo & tính hóa đơn nháp</button>
            </div>
        </form>
    </div></div>
</div>

<!-- ══ MODAL: Sửa đợt thu ══════════════════════════════════════════════════ -->
<div class="modal fade" id="editPeriodModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Sửa đợt thu học phí</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_period">
            <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($currentPeriod['title'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Ngày bắt đầu thu</label>
                        <input type="date" name="open_date" class="form-control" value="<?php echo $currentPeriod['open_date'] ?? ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hạn đóng học phí</label>
                        <input type="date" name="due_date" class="form-control" value="<?php echo $currentPeriod['due_date'] ?? ''; ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="2"><?php echo htmlspecialchars($currentPeriod['note'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Lưu thay đổi</button>
            </div>
        </form>
    </div></div>
</div>

<!-- ══ MODAL: Ghi nhận thanh toán ══════════════════════════════════════════ -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Ghi nhận Thanh toán</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="invoice_id" id="payId">
            <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
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
                    <label class="form-label fw-bold">Số tiền <span class="text-danger">*</span></label>
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
                    <input type="text" name="reference" class="form-control" placeholder="VD: TT20260601001">
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

<!-- ══ MODAL: Miễn giảm ════════════════════════════════════════════════════ -->
<div class="modal fade" id="discModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-percent me-2"></i>Cập nhật Miễn giảm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_discount">
            <input type="hidden" name="invoice_id" id="discId">
            <input type="hidden" name="period_id" value="<?php echo $currentPeriodId; ?>">
            <div class="modal-body">
                <div class="fw-bold mb-1" id="discName"></div>
                <div class="text-muted small mb-3">Học phí gốc: <span class="fw-bold text-navy" id="discGross"></span></div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Số tiền miễn giảm (₫)</label>
                    <input type="number" name="discount" id="discDiscount" class="form-control" min="0" step="1000" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Lý do</label>
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

<!-- ══ MODAL: Lịch sử thanh toán ══════════════════════════════════════════ -->
<div class="modal fade" id="histModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Lịch sử thanh toán — <span id="histName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="histBody"><div class="text-center py-4"><div class="spinner-border text-navy"></div></div></div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
function fmtVND(n) { return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(n); }

// Payment modal
document.getElementById('payModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('payId').value = b.dataset.id;
    document.getElementById('payName').textContent = b.dataset.name;
    document.getElementById('payCode').textContent = b.dataset.code || '';
    const net = parseFloat(b.dataset.net)||0, paid = parseFloat(b.dataset.paid)||0, rem = parseFloat(b.dataset.remaining)||0;
    document.getElementById('payNet').textContent  = fmtVND(net);
    document.getElementById('payPaid').textContent = fmtVND(paid);
    document.getElementById('payRem').textContent  = fmtVND(rem);
    document.getElementById('payAmount').value = rem > 0 ? rem : '';
    document.getElementById('payAmount').max   = rem > 0 ? rem : '';
});

// Discount modal
document.getElementById('discModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('discId').value = b.dataset.id;
    document.getElementById('discName').textContent = b.dataset.name;
    document.getElementById('discGross').textContent = fmtVND(parseFloat(b.dataset.gross)||0);
    document.getElementById('discDiscount').value = b.dataset.discount || 0;
    document.getElementById('discNote').value = b.dataset.note || '';
});

// History modal
function viewPayments(invoiceId, name) {
    document.getElementById('histName').textContent = name;
    document.getElementById('histBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-navy"></div></div>';
    new bootstrap.Modal(document.getElementById('histModal')).show();
    fetch('tuition.php?ajax=view_payments&invoice_id=' + invoiceId, {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            const pays = data.payments || [];
            if (!pays.length) {
                document.getElementById('histBody').innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có lịch sử thanh toán</div>';
                return;
            }
            const mMap = {cash:'Tiền mặt',bank_transfer:'Chuyển khoản',online:'Online',other:'Khác'};
            let html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Thời gian</th><th class="text-end">Số tiền</th><th>Hình thức</th><th>Mã GD</th><th>Người ghi</th><th>Ghi chú</th></tr></thead><tbody>';
            pays.forEach(p => {
                html += `<tr><td class="small">${new Date(p.paid_at).toLocaleString('vi-VN')}</td><td class="text-end fw-bold text-success">${fmtVND(parseFloat(p.amount))}</td><td class="small">${mMap[p.method]||p.method}</td><td class="small text-muted">${p.reference||'—'}</td><td class="small">${p.paid_by_name||'—'}</td><td class="small text-muted">${p.note||'—'}</td></tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('histBody').innerHTML = html;
        })
        .catch(() => { document.getElementById('histBody').innerHTML = '<div class="alert alert-danger">Lỗi tải dữ liệu.</div>'; });
}
</script>
</body></html>
