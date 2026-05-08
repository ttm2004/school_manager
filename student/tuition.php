<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');
$pageTitle = 'Hoc phi cua toi';
// PLACEHOLDER_STUDENT_TUITION

// ── Auto-create tables (schema mới) ──────────────────────────────────────────
// Không tạo lại ở đây — admin/tuition.php đã handle migration
// Chỉ kiểm tra bảng tồn tại

// ── Thông tin sinh viên ───────────────────────────────────────────────────────
$uid = (int)$_SESSION['user_id'];
$stRes = $conn->query("SELECT s.id, s.student_code, c.class_name, m.major_name, m.tuition_per_credit
    FROM students s LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN majors m ON c.major_id=m.id
    WHERE s.user_id=$uid LIMIT 1");
$student = $stRes ? $stRes->fetch_assoc() : null;
if (!$student) { header('Location: /university/student/index.php'); exit(); }
$sid = (int)$student['id'];
$unitPrice = (float)($student['tuition_per_credit'] ?? 0);

// ── Kiểm tra bảng tuition_invoices có cột period_id chưa ─────────────────────
$hasPeriodCol = false;
$chkTbl = $conn->query("SHOW TABLES LIKE 'tuition_invoices'");
if ($chkTbl && $chkTbl->num_rows > 0) {
    $chkCol = $conn->query("SHOW COLUMNS FROM `tuition_invoices` LIKE 'period_id'");
    $hasPeriodCol = ($chkCol && $chkCol->num_rows > 0);
}

// ── Học kỳ đã đăng ký ────────────────────────────────────────────────────────
$semRes = $conn->query("SELECT DISTINCT sm.id, sm.semester_name, sm.school_year
    FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id
    JOIN semesters sm ON cs.semester_id=sm.id
    WHERE ss.student_id=$sid AND ss.status!='cancelled'
    ORDER BY sm.school_year DESC, sm.semester_name DESC");
$semList = [];
if ($semRes) while ($r = $semRes->fetch_assoc()) $semList[] = $r;

// ── Tổng hợp từng học kỳ ─────────────────────────────────────────────────────
$semData = [];
$grandNet = $grandPaid = 0;
foreach ($semList as $sem) {
    $semId = (int)$sem['id'];

    // Tín chỉ đã đăng ký
    $tcRes = $conn->query("SELECT SUM(sub.credits) AS tc
        FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id
        JOIN subjects sub ON cs.subject_id=sub.id
        WHERE ss.student_id=$sid AND cs.semester_id=$semId AND ss.status!='cancelled'");
    $tc    = (int)(($tcRes ? $tcRes->fetch_assoc()['tc'] : 0) ?? 0);
    $gross = $tc * $unitPrice;

    // Hóa đơn — ưu tiên schema mới (có period_id), fallback schema cũ
    $invoice  = null;
    $period   = null;
    $payments = [];

    if ($hasPeriodCol) {
        // Schema mới: lấy hóa đơn đã published
        $invRes = $conn->query("
            SELECT ti.*, tp.title AS period_title, tp.open_date, tp.due_date AS period_due, tp.status AS period_status
            FROM tuition_invoices ti
            JOIN tuition_periods tp ON ti.period_id=tp.id
            WHERE ti.student_id=$sid AND ti.semester_id=$semId
              AND tp.status IN ('published','closed')
              AND ti.status != 'draft'
            ORDER BY ti.created_at DESC LIMIT 1");
        $invoice = $invRes ? $invRes->fetch_assoc() : null;
    } else {
        // Schema cũ
        $invRes = $conn->query("SELECT * FROM tuition_invoices WHERE student_id=$sid AND semester_id=$semId LIMIT 1");
        $invoice = $invRes ? $invRes->fetch_assoc() : null;
    }

    if ($invoice) {
        $iid  = (int)$invoice['id'];
        $pRes = $conn->query("SELECT tp.*, u.full_name AS paid_by_name
            FROM tuition_payments tp LEFT JOIN users u ON tp.paid_by=u.id
            WHERE tp.invoice_id=$iid ORDER BY tp.paid_at DESC");
        if ($pRes) while ($p = $pRes->fetch_assoc()) $payments[] = $p;
    }

    $net  = $invoice ? (float)$invoice['net_amount']  : $gross;
    $paid = $invoice ? (float)$invoice['paid_amount'] : 0;
    $grandNet  += ($invoice ? $net : 0); // Chỉ tính nếu đã có hóa đơn published
    $grandPaid += $paid;
    $semData[] = [
        'sem'      => $sem,
        'credits'  => $tc,
        'gross'    => $gross,
        'invoice'  => $invoice,
        'net'      => $net,
        'paid'     => $paid,
        'remaining'=> max(0, $net - $paid),
        'payments' => $payments,
        'published'=> $invoice !== null,
    ];
}
$grandRemaining = max(0, $grandNet - $grandPaid);
$hasDebt = $grandRemaining > 0;

if (!function_exists('fmtVND')) { function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; } }
$statusMap = ['unpaid'=>['warning','Chưa đóng'],'partial'=>['info','Đóng một phần'],'paid'=>['success','Đã đóng đủ'],'overdue'=>['danger','Quá hạn'],'waived'=>['secondary','Miễn học phí']];
$methodMap = ['cash'=>'Tiền mặt','bank_transfer'=>'Chuyển khoản','online'=>'Online','other'=>'Khác'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Học phí — TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        :root{--navy:#0d2d6b;--gold:#f5a623}
        body{background:#f0f2f7}
        .tp-bar{background:var(--navy);color:#fff;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
        .tp-bar .brand{font-weight:700;font-size:1rem;display:flex;align-items:center;gap:8px}
        .tp-bar .brand i{color:var(--gold)}
        .pw{max-width:1000px;margin:0 auto;padding:24px 16px}
        .sum-card{background:#fff;border-radius:14px;padding:20px 24px;box-shadow:0 2px 12px rgba(13,45,107,.08);margin-bottom:24px}
        .sp{background:#f8faff;border-radius:10px;padding:14px 16px;text-align:center;border:1px solid #e2e8f0}
        .sp .val{font-size:1.2rem;font-weight:800;line-height:1.2}
        .sp .lbl{font-size:.72rem;color:#6b7a99;margin-top:3px}
        .sc{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(13,45,107,.07);margin-bottom:16px;overflow:hidden}
        .sh{background:var(--navy);color:#fff;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none}
        .sh:hover{background:#1a4fa0}
        .sb{padding:16px 18px}
        .ir{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #f0f2f7;font-size:.87rem}
        .ir:last-child{border-bottom:none}
        .ir .lbl{color:#6b7a99}
        .ir .val{font-weight:600}
        .pb{height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-top:10px}
        .pb .fill{height:100%;border-radius:3px;transition:width .4s}
    </style>
</head>
<body>
<div class="tp-bar">
    <div class="brand"><i class="bi bi-cash-coin"></i> Học phí của tôi</div>
    <div class="d-flex align-items-center gap-3">
        <span class="small opacity-75"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
        <a href="/university/student/index.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Trang chủ</a>
    </div>
</div>
<div class="pw">

<?php if ($hasDebt): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
    <div><strong>Bạn đang nợ học phí <?php echo fmtVND($grandRemaining); ?></strong> — Vui lòng đến phòng Kế toán để đóng học phí. Đăng ký học phần có thể bị khóa nếu còn nợ.</div>
</div>
<?php endif; ?>

<!-- Tổng hợp -->
<div class="sum-card">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:50px;height:50px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:1.4rem;flex-shrink:0;"><i class="bi bi-person-fill"></i></div>
        <div>
            <div class="fw-bold fs-5"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></div>
            <div class="text-muted small"><?php echo htmlspecialchars($student['student_code']); ?> &bull; <?php echo htmlspecialchars($student['class_name']??''); ?> &bull; <?php echo htmlspecialchars($student['major_name']??''); ?></div>
            <div class="text-muted small">Đơn giá: <strong><?php echo fmtVND($unitPrice); ?>/tín chỉ</strong></div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-4"><div class="sp"><div class="val text-navy"><?php echo fmtVND($grandNet); ?></div><div class="lbl">Tổng cần nạp</div></div></div>
        <div class="col-4"><div class="sp"><div class="val text-success"><?php echo fmtVND($grandPaid); ?></div><div class="lbl">Đã nạp</div></div></div>
        <div class="col-4"><div class="sp"><div class="val <?php echo $grandRemaining>0?'text-danger':'text-success'; ?>"><?php echo fmtVND($grandRemaining); ?></div><div class="lbl">Còn nợ</div></div></div>
    </div>
    <?php if ($grandNet > 0): $pct = min(100,round($grandPaid/$grandNet*100)); ?>
    <div class="pb mt-3"><div class="fill" style="width:<?php echo $pct; ?>%;background:<?php echo $grandRemaining<=0?'#10b981':'var(--navy)'; ?>;"></div></div>
    <div class="d-flex justify-content-between mt-1" style="font-size:.72rem;color:#6b7a99;"><span>Đã đóng <?php echo $pct; ?>%</span><span><?php echo count($semData); ?> học kỳ</span></div>
    <?php endif; ?>
</div>

<!-- Chi tiết từng kỳ -->
<h6 class="fw-bold text-navy mb-3"><i class="bi bi-calendar3 me-2"></i>Chi tiết học phí theo học kỳ</h6>

<?php if (empty($semData)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Bạn chưa đăng ký học phần nào.</div>
<?php else: ?>
<?php foreach ($semData as $i => $sd):
    $inv = $sd['invoice'];
    $pct = $sd['net']>0 ? min(100,round($sd['paid']/$sd['net']*100)) : 0;
    $st  = $inv ? ($statusMap[$inv['status']] ?? ['secondary',$inv['status']]) : null;
?>
<div class="sc">
    <div class="sh" data-bs-toggle="collapse" data-bs-target="#sem<?php echo $i; ?>">
        <div>
            <div class="fw-bold"><?php echo htmlspecialchars($sd['sem']['semester_name'].' '.$sd['sem']['school_year']); ?></div>
            <div class="small opacity-75"><?php echo $sd['credits']; ?> TC &times; <?php echo fmtVND($unitPrice); ?>/TC = <?php echo fmtVND($sd['gross']); ?></div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($st): ?><span class="badge bg-<?php echo $st[0]; ?>"><?php echo $st[1]; ?></span>
            <?php else: ?><span class="badge bg-secondary">Chưa có hóa đơn</span><?php endif; ?>
            <div class="text-end">
                <div class="small">Còn nợ</div>
                <div class="fw-bold <?php echo $sd['remaining']>0?'text-warning':'text-success'; ?>"><?php echo fmtVND($sd['remaining']); ?></div>
            </div>
            <i class="bi bi-chevron-down"></i>
        </div>
    </div>
    <div class="collapse <?php echo $i===0?'show':''; ?>" id="sem<?php echo $i; ?>">
        <div class="sb">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="ir"><span class="lbl">Số tín chỉ đã đăng ký</span><span class="val"><?php echo $sd['credits']; ?> TC</span></div>
                    <div class="ir"><span class="lbl">Đơn giá / tín chỉ</span><span class="val"><?php echo fmtVND($unitPrice); ?></span></div>
                    <div class="ir"><span class="lbl">Học phí gốc</span><span class="val"><?php echo fmtVND($sd['gross']); ?></span></div>
                    <?php if ($inv && $inv['discount']>0): ?>
                    <div class="ir"><span class="lbl">Miễn giảm / Học bổng</span><span class="val text-success">- <?php echo fmtVND($inv['discount']); ?></span></div>
                    <?php endif; ?>
                    <div class="ir" style="border-top:2px solid #e2e8f0;margin-top:4px;padding-top:8px;">
                        <span class="lbl fw-bold text-navy">Phải đóng</span>
                        <span class="val fw-bold text-navy fs-6"><?php echo fmtVND($sd['net']); ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ir"><span class="lbl">Đã đóng</span><span class="val text-success"><?php echo fmtVND($sd['paid']); ?></span></div>
                    <div class="ir"><span class="lbl">Còn nợ</span><span class="val <?php echo $sd['remaining']>0?'text-danger':'text-success'; ?> fw-bold"><?php echo fmtVND($sd['remaining']); ?></span></div>
                    <?php if ($inv && $inv['due_date']): ?>
                    <div class="ir"><span class="lbl">Hạn đóng</span><span class="val <?php echo $inv['status']==='overdue'?'text-danger':''; ?>"><?php echo date('d/m/Y',strtotime($inv['due_date'])); ?><?php if ($inv['status']==='overdue'): ?> <i class="bi bi-exclamation-triangle-fill text-danger"></i><?php endif; ?></span></div>
                    <?php endif; ?>
                    <?php if ($inv && $inv['note']): ?>
                    <div class="ir"><span class="lbl">Ghi chú</span><span class="val text-muted small"><?php echo htmlspecialchars($inv['note']); ?></span></div>
                    <?php endif; ?>
                    <?php if (!$inv): ?><div class="alert alert-warning py-2 small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Hóa đơn chưa được tạo. Vui lòng liên hệ phòng Kế toán.</div><?php endif; ?>
                </div>
            </div>
            <?php if ($sd['net']>0): ?>
            <div class="pb"><div class="fill" style="width:<?php echo $pct; ?>%;background:<?php echo $pct>=100?'#10b981':'var(--navy)'; ?>;"></div></div>
            <div class="text-end text-muted mt-1" style="font-size:.7rem;">Đã đóng <?php echo $pct; ?>%</div>
            <?php endif; ?>
            <?php if (!empty($sd['payments'])): ?>
            <div class="mt-3">
                <div class="fw-semibold small text-navy mb-2"><i class="bi bi-clock-history me-1"></i>Lịch sử thanh toán (<?php echo count($sd['payments']); ?> lần)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">
                        <thead><tr><th>Thời gian</th><th class="text-end">Số tiền</th><th>Hình thức</th><th>Mã giao dịch</th><th>Ghi chú</th></tr></thead>
                        <tbody>
                        <?php foreach ($sd['payments'] as $p): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i',strtotime($p['paid_at'])); ?></td>
                            <td class="text-end fw-bold text-success"><?php echo fmtVND($p['amount']); ?></td>
                            <td><?php echo $methodMap[$p['method']]??$p['method']; ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($p['reference']??'—'); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($p['note']??'—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($inv): ?>
            <div class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i>Chưa có lịch sử thanh toán.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . '/../includes/analytics_widget.php'; ?>
</body></html>
